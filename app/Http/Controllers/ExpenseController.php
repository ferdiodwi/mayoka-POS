<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    /**
     * List expenses with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with('user:id,name')
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $expenses = $query->paginate(20);

        // Add totals
        $totalsQuery = Expense::query();
        if ($request->filled('date_from')) {
            $totalsQuery->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $totalsQuery->whereDate('expense_date', '<=', $request->date_to);
        }

        $totalAmount = $totalsQuery->sum('amount');
        $byCategory = (clone $totalsQuery)->select('category', \DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->pluck('total', 'category');

        return response()->json([
            'expenses' => $expenses,
            'summary' => [
                'total' => (float) $totalAmount,
                'by_category' => $byCategory,
            ],
            'category_labels' => Expense::categoryLabels(),
        ]);
    }

    /**
     * Store a new expense.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|in:listrik,sewa,gaji,operasional,bahan_baku,lainnya',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Check for active shift
        $activeShift = \App\Models\Shift::active()->where('user_id', auth()->id())->first();

        $expense = Expense::create([
            'user_id' => auth()->id(),
            'shift_id' => $activeShift ? $activeShift->id : null,
            ...$request->only(['expense_date', 'category', 'amount', 'description', 'notes']),
        ]);

        event(new \App\Events\DashboardUpdated());

        return response()->json([
            'message' => 'Pengeluaran berhasil disimpan.',
            'expense' => $expense,
        ], 201);
    }

    /**
     * Update an expense.
     */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|in:listrik,sewa,gaji,operasional,bahan_baku,lainnya',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $expense->update($request->only(['expense_date', 'category', 'amount', 'description', 'notes']));

        return response()->json([
            'message' => 'Pengeluaran berhasil diperbarui.',
            'expense' => $expense,
        ]);
    }

    /**
     * Delete an expense.
     */
    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(['message' => 'Pengeluaran dihapus.']);
    }
}
