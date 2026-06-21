<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
