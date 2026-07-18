<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;

class ShiftController extends Controller
{
    /**
     * Get the active shift for the current user.
     */
    public function active(Request $request): JsonResponse
    {
        $shift = Shift::active()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($shift) {
            $cashSales = \App\Models\Transaction::where('shift_id', $shift->id)
                ->where('payment_method', 'cash')
                ->sum('total');
            $cashRefunds = \App\Models\ReturnTransaction::where('shift_id', $shift->id)
                ->sum('refund_amount');
            $cashExpenses = \App\Models\Expense::where('shift_id', $shift->id)
                ->sum('amount');

            $shift->live_expected_cash = $shift->cash_start + $cashSales - $cashRefunds - $cashExpenses;
        }

        return response()->json([
            'shift' => $shift,
        ]);
    }

    /**
     * Open a new shift.
     */
    public function open(Request $request): JsonResponse
    {
        $request->validate([
            'cash_start' => 'required|numeric|min:0',
        ]);

        // Check if user already has an active shift
        $existingShift = Shift::active()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existingShift) {
            return response()->json([
                'message' => 'Anda masih memiliki shift yang aktif. Tutup shift terlebih dahulu.',
                'shift' => $existingShift,
            ], 422);
        }

        $shift = Shift::create([
            'user_id' => $request->user()->id,
            'started_at' => now(),
            'cash_start' => $request->cash_start,
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Shift berhasil dibuka.',
            'shift' => $shift,
        ], 201);
    }

    /**
     * Close an active shift with cash reconciliation.
     */
    public function close(Request $request, Shift $shift): JsonResponse
    {
        $request->validate([
            'cash_end' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        // Verify this shift belongs to the current user
        if ($shift->user_id !== $request->user()->id && !$request->user()->isOwner()) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke shift ini.',
            ], 403);
        }

        if (!$shift->isOpen()) {
            return response()->json([
                'message' => 'Shift ini sudah ditutup.',
            ], 422);
        }

        // Calculate expected cash: cash_start + cash sales (net) - refunds - cash expenses
        // Note: cashSales (sum of 'total') is exactly the net cash entering the drawer.
        // We do NOT subtract cash_change, because the total is the exact revenue kept in the drawer.
        $cashSales = \App\Models\Transaction::where('shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->sum('total');

        $cashRefunds = \App\Models\ReturnTransaction::where('shift_id', $shift->id)
            ->sum('refund_amount');

        $cashExpenses = \App\Models\Expense::where('shift_id', $shift->id)
            ->sum('amount');

        $cashExpected = $shift->cash_start + $cashSales - $cashRefunds - $cashExpenses;
        $cashDifference = $request->cash_end - $cashExpected;

        $shift->update([
            'ended_at' => now(),
            'cash_end' => $request->cash_end,
            'cash_expected' => $cashExpected,
            'cash_difference' => $cashDifference,
            'status' => 'closed',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Shift berhasil ditutup.',
            'shift' => $shift->fresh(),
        ]);
    }

    /**
     * List shift history (owner only, with optional date filter).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shift::with('user:id,name,username')
            ->orderBy('created_at', 'desc');

        if ($request->has('date_from')) {
            $query->whereDate('started_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('started_at', '<=', $request->date_to);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $shifts = $query->paginate(20);

        return response()->json($shifts);
    }

    /**
     * Get shift report data formatted for preview.
     */
    public function receipt(Request $request, Shift $shift): JsonResponse
    {
        $shift->load('user');

        $cashSales = \App\Models\Transaction::where('shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->sum('total');

        $cashRefunds = \App\Models\ReturnTransaction::where('shift_id', $shift->id)
            ->sum('refund_amount');

        $cashExpenses = \App\Models\Expense::where('shift_id', $shift->id)
            ->sum('amount');

        $cashStart = $shift->cash_start;
        $cash1 = $cashStart + $cashSales;
        $cash2 = $cash1 - $cashExpenses;
        $cashAkhir = $cash2 - $cashRefunds;

        $dateStr = \Carbon\Carbon::parse($shift->created_at)->locale('id')->translatedFormat('d F Y');
        $timeStr = $shift->created_at->format('H:i:s');

        return response()->json([
            'receipt' => [
                'cashier' => strtoupper($shift->user->name),
                'date' => $dateStr,
                'time' => $timeStr,
                'cash_start' => $cashStart,
                'cash_sales' => $cashSales,
                'cash_expenses' => $cashExpenses,
                'cash_refunds' => $cashRefunds,
                'cash_expected' => $cashAkhir,
                'status' => $shift->status,
                'cash_end' => $shift->cash_end,
                'cash_difference' => $shift->cash_difference,
            ]
        ]);
    }

    /**
     * Print shift balance report via thermal printer.
     */
    public function printReport(Request $request, Shift $shift): JsonResponse
    {
        $shift->load('user');

        // Retrieve the data exactly like close() calculates
        $cashSales = \App\Models\Transaction::where('shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->sum('total');

        $cashRefunds = \App\Models\ReturnTransaction::where('shift_id', $shift->id)
            ->sum('refund_amount');

        $cashExpenses = \App\Models\Expense::where('shift_id', $shift->id)
            ->sum('amount');

        $cashStart = $shift->cash_start;
        $cash1 = $cashStart + $cashSales;
        $cash2 = $cash1 - $cashExpenses;
        $cashAkhir = $cash2 - $cashRefunds; // live expected cash

        // Format dates in Indonesian
        $dateStr = \Carbon\Carbon::parse($shift->created_at)->locale('id')->translatedFormat('d F Y'); // e.g., 22 Juni 2026
        $timeStr = $shift->created_at->format('H:i:s');

        try {
            $connector = new DummyPrintConnector();
            $printer = new Printer($connector);

            try {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text("LAPORAN SALDO KASIR\n");
                $printer->text("================================\n");

                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->text(sprintf("%-11s: %s\n", "KASIR", strtoupper($shift->user->name)));
                $printer->text(sprintf("%-11s: %s\n", "TANGGAL", $dateStr));
                $printer->text(sprintf("%-11s: %s\n", "JAM", $timeStr));
                $printer->text("--------------------------------\n");

                $formatNumber = fn($num) => number_format($num, 0, ',', '.');

                $printer->text(sprintf("%-11s: %s\n", "MODAL AWAL", $formatNumber($cashStart)));
                $printer->text(sprintf("%-11s: %s (+)\n", "PENJUALAN", $formatNumber($cashSales)));
                if ($cashExpenses > 0) {
                    $printer->text(sprintf("%-11s: %s (-)\n", "PENGELUARAN", $formatNumber($cashExpenses)));
                }
                if ($cashRefunds > 0) {
                    $printer->text(sprintf("%-11s: %s (-)\n", "RETUR JUAL", $formatNumber($cashRefunds)));
                }
                $printer->text("================================\n");

                $printer->text(sprintf("%-11s: %s\n", "TOTAL KAS", $formatNumber($cashAkhir)));
                
                if ($shift->status === 'closed') {
                    $printer->text("--------------------------------\n");
                    $printer->text(sprintf("%-11s: %s\n", "UANG FISIK", $formatNumber($shift->cash_end)));
                    
                    $diffStr = ($shift->cash_difference > 0 ? '+' : '') . $formatNumber($shift->cash_difference);
                    $printer->text(sprintf("%-11s: %s\n", "SELISIH", $diffStr));
                }

                $printer->feed(4);
                $printer->cut();
            } finally {
                $data = $connector->getData();
                $printer->close();
            }

            return response()->json([
                'message' => 'Laporan saldo kasir berhasil digenerate.',
                'receipt_base64' => base64_encode($data)
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal print: ' . $e->getMessage()], 500);
        }
    }
}
