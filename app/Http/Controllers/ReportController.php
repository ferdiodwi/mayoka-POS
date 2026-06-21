<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\StockMovement;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\ReturnTransaction;
use App\Models\ReturnItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\SalesExport;

class ReportController extends Controller
{
    /**
     * Dashboard stats (owner).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $chartFilter = $request->get('chart_filter', 'week');


        // Today's revenue (minus returns)
        $todayGrossRevenue = Transaction::whereDate('created_at', $today)->sum('total');
        $todayReturns = ReturnTransaction::whereDate('created_at', $today)->sum('refund_amount');
        $todayRevenue = (float) $todayGrossRevenue - (float) $todayReturns;
        $todayTransactions = Transaction::whereDate('created_at', $today)->count();

        // Today's cost (HPP minus returned HPP)
        $todayGrossCost = TransactionItem::whereHas('transaction', fn ($q) => $q->whereDate('created_at', $today))
            ->selectRaw('SUM(qty * cost_price) as total_cost')
            ->value('total_cost') ?? 0;
        $todayReturnedCost = ReturnItem::whereHas('returnTransaction', fn ($q) => $q->whereDate('created_at', $today))
            ->join('transaction_items', 'return_items.transaction_item_id', '=', 'transaction_items.id')
            ->selectRaw('SUM(return_items.qty * transaction_items.cost_price) as total_cost')
            ->value('total_cost') ?? 0;
        $todayCost = (float) $todayGrossCost - (float) $todayReturnedCost;

        $todayProfit = $todayRevenue - $todayCost;

        // Monthly revenue (minus returns)
        $monthGrossRevenue = Transaction::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');
        $monthReturns = ReturnTransaction::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('refund_amount');
        $monthRevenue = (float) $monthGrossRevenue - (float) $monthReturns;

        // Top selling products (this month)
        $topProducts = TransactionItem::whereHas('transaction', fn ($q) =>
                $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year))
            ->where('item_type', '!=', 'addon')
            ->select('description', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(subtotal) as total_revenue'))
            ->groupBy('description')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        // Low stock products
        $lowStock = Product::where('type', 'barang')
            ->where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->select('id', 'name', 'stock', 'min_stock', 'unit')
            ->orderBy('stock')
            ->get();

        // Recent transactions
        $recentTransactions = Transaction::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'invoice_number', 'user_id', 'total', 'payment_method', 'created_at']);

        // Revenue chart (filtered: week or month, minus returns)
        $chartData = [];
        $daysToFetch = $chartFilter === 'month' ? 30 : 7;
        
        for ($i = $daysToFetch - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $grossRev = (float) Transaction::whereDate('created_at', $date)->sum('total');
            $dayReturns = (float) ReturnTransaction::whereDate('created_at', $date)->sum('refund_amount');
            $chartData[] = [
                'date' => now()->subDays($i)->format('d/m'),
                'revenue' => $grossRev - $dayReturns,
            ];
        }

        return response()->json([
            'today_revenue' => (float) $todayRevenue,
            'today_transactions' => $todayTransactions,
            'today_cost' => (float) $todayCost,
            'today_profit' => (float) $todayProfit,
            'month_revenue' => (float) $monthRevenue,
            'top_products' => $topProducts,
            'low_stock' => $lowStock,
            'recent_transactions' => $recentTransactions,
            'chart_data' => $chartData,
        ]);
    }

    /**
     * Sales report.
     */
    public function salesReport(Request $request): JsonResponse
    {
        $from = $request->get('date_from', now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', now()->toDateString());

        $dailyData = Transaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as tx_count'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('AVG(total) as avg_per_tx')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Calculate HPP per day (minus returned HPP)
        $dailyReport = $dailyData->map(function ($day) {
            $cost = TransactionItem::whereHas('transaction', fn ($q) =>
                    $q->whereDate('created_at', $day->date))
                ->selectRaw('SUM(qty * cost_price) as total_cost')
                ->value('total_cost') ?? 0;

            $dayReturns = (float) ReturnTransaction::whereDate('created_at', $day->date)
                ->sum('refund_amount');

            $dayReturnedCost = (float) (ReturnItem::whereHas('returnTransaction', fn ($q) =>
                    $q->whereDate('created_at', $day->date))
                ->join('transaction_items', 'return_items.transaction_item_id', '=', 'transaction_items.id')
                ->selectRaw('SUM(return_items.qty * transaction_items.cost_price) as total_cost')
                ->value('total_cost') ?? 0);

            $netRevenue = (float) $day->revenue - $dayReturns;
            $netCost = (float) $cost - $dayReturnedCost;

            // Breakdown per payment method
            $methodBreakdown = Transaction::whereDate('created_at', $day->date)
                ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
                ->groupBy('payment_method')
                ->get()
                ->keyBy('payment_method')
                ->map(fn ($v) => ['count' => $v->count, 'total' => (float) $v->total]);

            return [
                'date' => $day->date,
                'tx_count' => $day->tx_count,
                'revenue' => $netRevenue,
                'cost' => $netCost,
                'profit' => $netRevenue - $netCost,
                'returns' => $dayReturns,
                'avg_per_tx' => round((float) $day->avg_per_tx),
                'by_method' => $methodBreakdown,
            ];
        });

        // Totals
        $totals = [
            'tx_count' => $dailyReport->sum('tx_count'),
            'revenue' => $dailyReport->sum('revenue'),
            'cost' => $dailyReport->sum('cost'),
            'profit' => $dailyReport->sum('profit'),
        ];

        return response()->json([
            'daily' => $dailyReport,
            'totals' => $totals,
            'date_from' => $from,
            'date_to' => $to,
        ]);
    }

    /**
     * Export Sales Report (Excel/PDF).
     */
    public function exportSales(Request $request)
    {
        $from = $request->get('date_from', now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', now()->toDateString());
        $format = $request->get('format', 'excel'); // 'excel' or 'pdf'

        if ($format === 'pdf') {
            $transactions = \App\Models\Transaction::with('user')
                ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(created_at)'), [$from, $to])
                ->orderBy('created_at', 'asc')
                ->get();
                
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.sales_pdf', compact('transactions', 'from', 'to'));
            return $pdf->download('Laporan_Penjualan_MAYOKA_' . $from . '_sd_' . $to . '.pdf');
        }

        // Default to Excel
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\SalesExport($from, $to), 'Laporan_Penjualan_MAYOKA_' . $from . '_sd_' . $to . '.xlsx');
    }

    /**
     * Cashier performance report.
     */
    public function cashierReport(Request $request): JsonResponse
    {
        $from = $request->get('date_from', now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', now()->toDateString());

        $data = DB::table('transactions')
            ->join('users', 'transactions.user_id', '=', 'users.id')
            ->whereBetween(DB::raw('DATE(transactions.created_at)'), [$from, $to])
            ->select(
                'users.id as user_id',
                'users.name',
                DB::raw('COUNT(*) as tx_count'),
                DB::raw('SUM(transactions.total) as total_revenue')
            )
            ->groupBy('users.id', 'users.name')
            ->get();

        // Add shift count, avg cash difference, and deduct returns
        $report = $data->map(function ($row) use ($from, $to) {
            $shifts = Shift::where('user_id', $row->user_id)
                ->where('status', 'closed')
                ->whereBetween(DB::raw('DATE(started_at)'), [$from, $to])
                ->get();

            $userReturns = (float) ReturnTransaction::where('user_id', $row->user_id)
                ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
                ->sum('refund_amount');

            // Revenue per payment method for this cashier
            $methodBreakdown = DB::table('transactions')
                ->where('user_id', $row->user_id)
                ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
                ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
                ->groupBy('payment_method')
                ->get()
                ->keyBy('payment_method')
                ->map(fn ($v) => ['count' => $v->count, 'total' => (float) $v->total]);

            return [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'shift_count' => $shifts->count(),
                'tx_count' => $row->tx_count,
                'total_revenue' => (float) $row->total_revenue - $userReturns,
                'total_returns' => $userReturns,
                'avg_cash_diff' => $shifts->count() > 0
                    ? round($shifts->avg('cash_difference'))
                    : 0,
                'by_method' => $methodBreakdown,
            ];
        });

        return response()->json(['report' => $report]);
    }

    /**
     * Shift history report.
     */
    public function shiftReport(Request $request): JsonResponse
    {
        $from = $request->get('date_from', now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', now()->toDateString());

        $shifts = Shift::with('user:id,name')
            ->whereBetween(DB::raw('DATE(started_at)'), [$from, $to])
            ->orderByDesc('started_at')
            ->get()
            ->map(function ($s) {
                $txCount = Transaction::where('shift_id', $s->id)->count();
                $txTotal = Transaction::where('shift_id', $s->id)->sum('total');

                // Breakdown per payment method for this shift
                $methodBreakdown = Transaction::where('shift_id', $s->id)
                    ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
                    ->groupBy('payment_method')
                    ->get()
                    ->keyBy('payment_method')
                    ->map(fn ($v) => ['count' => $v->count, 'total' => (float) $v->total]);

                return [
                    'id' => $s->id,
                    'cashier' => $s->user->name,
                    'started_at' => $s->started_at?->format('d/m/Y H:i'),
                    'ended_at' => $s->ended_at?->format('d/m/Y H:i'),
                    'cash_start' => (float) $s->cash_start,
                    'cash_end' => (float) ($s->cash_end ?? 0),
                    'cash_expected' => (float) ($s->cash_expected ?? 0),
                    'cash_difference' => (float) ($s->cash_difference ?? 0),
                    'status' => $s->status,
                    'tx_count' => $txCount,
                    'tx_total' => (float) $txTotal,
                    'by_method' => $methodBreakdown,
                ];
            });

        return response()->json(['shifts' => $shifts]);
    }

    /**
     * Stock movement report.
     */
    public function stockReport(Request $request): JsonResponse
    {
        $from = $request->get('date_from', now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', now()->toDateString());
        $categoryId = $request->get('category_id');

        $query = Product::where('type', 'barang')->where('is_active', true)->with('category:id,name');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('name')->get()->map(function ($p) use ($from, $to) {
            $movements = StockMovement::where('product_id', $p->id)
                ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
                ->get();

            $stockIn = $movements->where('type', 'in')->sum('qty');
            $stockOut = abs($movements->where('type', 'out')->sum('qty'));
            $adjustment = $movements->where('type', 'adjustment')->sum('qty');

            return [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category->name,
                'stock_current' => $p->stock,
                'min_stock' => $p->min_stock,
                'unit' => $p->unit,
                'stock_in' => $stockIn,
                'stock_out' => $stockOut,
                'adjustment' => $adjustment,
                'is_low' => $p->stock <= $p->min_stock,
            ];
        });

        return response()->json(['products' => $products]);
    }

    /**
     * Profit & Loss report.
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $from = $request->get('date_from', now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', now()->toDateString());

        // Revenue from sales
        $grossRevenue = (float) Transaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->sum('total');

        $txCount = Transaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->count();

        // Calculate returns
        $returnedRevenue = (float) ReturnTransaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->sum('refund_amount');

        $revenue = $grossRevenue - $returnedRevenue;

        // HPP (Cost of Goods Sold) from transactions
        $grossHpp = (float) (TransactionItem::whereHas('transaction', fn ($q) =>
                $q->whereBetween(DB::raw('DATE(created_at)'), [$from, $to]))
            ->selectRaw('SUM(qty * cost_price) as total_cost')
            ->value('total_cost') ?? 0);

        // Deduct returned HPP
        $returnedHpp = (float) (ReturnItem::whereHas('returnTransaction', fn ($q) =>
                $q->whereBetween(DB::raw('DATE(created_at)'), [$from, $to]))
            ->join('transaction_items', 'return_items.transaction_item_id', '=', 'transaction_items.id')
            ->selectRaw('SUM(return_items.qty * transaction_items.cost_price) as total_returned_cost')
            ->value('total_returned_cost') ?? 0);

        $hpp = $grossHpp - $returnedHpp;

        $grossProfit = $revenue - $hpp;

        // Total purchases
        $totalPurchases = (float) Purchase::whereBetween('purchase_date', [$from, $to])
            ->sum('total_amount');

        $purchaseCount = Purchase::whereBetween('purchase_date', [$from, $to])
            ->count();

        // Total expenses
        $totalExpenses = (float) Expense::whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        // Expenses by category
        $expensesByCategory = Expense::whereBetween('expense_date', [$from, $to])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($v) => (float) $v);

        // Net profit
        $netProfit = $grossProfit - $totalExpenses;

        // Revenue by payment method
        $revenueByMethod = Transaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->select('payment_method', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method')
            ->map(fn ($v) => ['total' => (float) $v->total, 'count' => $v->count]);

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'gross_revenue' => $grossRevenue,
            'returned_revenue' => $returnedRevenue,
            'revenue' => $revenue,
            'tx_count' => $txCount,
            'hpp' => $hpp,
            'gross_profit' => $grossProfit,
            'total_purchases' => $totalPurchases,
            'purchase_count' => $purchaseCount,
            'total_expenses' => $totalExpenses,
            'expenses_by_category' => $expensesByCategory,
            'net_profit' => $netProfit,
            'revenue_by_method' => $revenueByMethod,
            'category_labels' => Expense::categoryLabels(),
        ]);
    }

    /**
     * Cash Flow Report (Laporan Arus Kas).
     */
    public function cashFlow(Request $request): JsonResponse
    {
        $from = $request->get('date_from', now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', now()->toDateString());

        // === KAS MASUK ===

        // 1. Penjualan Tunai
        $cashSales = (float) Transaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->where('payment_method', 'cash')
            ->sum('total');

        // 2. Penjualan QRIS / Non-Tunai
        $nonCashSales = (float) Transaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->where('payment_method', '!=', 'cash')
            ->sum('total');

        // 3. Modal Awal Shift (kas yang dimasukkan ke laci)
        $shiftCapital = (float) Shift::whereBetween(DB::raw('DATE(started_at)'), [$from, $to])
            ->sum('cash_start');

        $totalCashIn = $cashSales + $nonCashSales + $shiftCapital;

        // === KAS KELUAR ===

        // 1. Pembelian Barang (kulakan stok)
        $purchasesPaid = (float) Purchase::whereBetween('purchase_date', [$from, $to])
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // 2. Pengeluaran Operasional
        $expenses = (float) Expense::whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        // 3. Refund / Retur ke pelanggan
        $refunds = (float) ReturnTransaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->sum('refund_amount');

        $totalCashOut = $purchasesPaid + $expenses + $refunds;

        // === ARUS KAS BERSIH ===
        $netCashFlow = $totalCashIn - $totalCashOut;

        // Pengeluaran per kategori
        $expensesByCategory = Expense::whereBetween('expense_date', [$from, $to])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($v) => (float) $v);

        // Penjualan per metode bayar
        $salesByMethod = Transaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->select('payment_method', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method')
            ->map(fn ($v) => ['total' => (float) $v->total, 'count' => $v->count]);

        // Arus kas harian (untuk grafik)
        $dailyCashFlow = DB::select("
            SELECT dates.date,
                COALESCE(cash_in.total, 0) as cash_in,
                COALESCE(cash_out.total, 0) as cash_out
            FROM (
                SELECT DATE(created_at) as date FROM transactions
                WHERE DATE(created_at) BETWEEN ? AND ?
                UNION
                SELECT purchase_date as date FROM purchases
                WHERE purchase_date BETWEEN ? AND ?
                UNION
                SELECT expense_date as date FROM expenses
                WHERE expense_date BETWEEN ? AND ?
                GROUP BY date
            ) dates
            LEFT JOIN (
                SELECT DATE(created_at) as date, SUM(total) as total
                FROM transactions
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY date
            ) cash_in ON dates.date = cash_in.date
            LEFT JOIN (
                SELECT date, SUM(total) as total FROM (
                    SELECT purchase_date as date, SUM(total_amount) as total
                    FROM purchases WHERE purchase_date BETWEEN ? AND ? AND payment_status = 'paid'
                    GROUP BY purchase_date
                    UNION ALL
                    SELECT expense_date as date, SUM(amount) as total
                    FROM expenses WHERE expense_date BETWEEN ? AND ?
                    GROUP BY expense_date
                    UNION ALL
                    SELECT DATE(created_at) as date, SUM(refund_amount) as total
                    FROM returns WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY date
                ) combined GROUP BY date
            ) cash_out ON dates.date = cash_out.date
            ORDER BY dates.date
        ", [$from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to]);

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'cash_in' => [
                'cash_sales' => $cashSales,
                'non_cash_sales' => $nonCashSales,
                'shift_capital' => $shiftCapital,
                'total' => $totalCashIn,
            ],
            'cash_out' => [
                'purchases' => $purchasesPaid,
                'expenses' => $expenses,
                'refunds' => $refunds,
                'total' => $totalCashOut,
            ],
            'net_cash_flow' => $netCashFlow,
            'expenses_by_category' => $expensesByCategory,
            'sales_by_method' => $salesByMethod,
            'daily_cash_flow' => $dailyCashFlow,
            'category_labels' => Expense::categoryLabels(),
        ]);
    }

    /**
     * Export Cash Flow Report (Excel/PDF).
     */
    public function exportCashFlow(Request $request)
    {
        $from = $request->get('date_from', now()->startOfMonth()->toDateString());
        $to = $request->get('date_to', now()->toDateString());
        $format = $request->get('format', 'excel');

        // Use the same raw SQL query from CashFlowExport to get daily cash flow
        $dailyCashFlow = DB::select("
            SELECT dates.date,
                COALESCE(cash_in.total, 0) as cash_in,
                COALESCE(cash_out.total, 0) as cash_out
            FROM (
                SELECT DATE(created_at) as date FROM transactions
                WHERE DATE(created_at) BETWEEN ? AND ?
                UNION
                SELECT purchase_date as date FROM purchases
                WHERE purchase_date BETWEEN ? AND ?
                UNION
                SELECT expense_date as date FROM expenses
                WHERE expense_date BETWEEN ? AND ?
                GROUP BY date
            ) dates
            LEFT JOIN (
                SELECT DATE(created_at) as date, SUM(total) as total
                FROM transactions
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY date
            ) cash_in ON dates.date = cash_in.date
            LEFT JOIN (
                SELECT date, SUM(total) as total FROM (
                    SELECT purchase_date as date, SUM(total_amount) as total
                    FROM purchases WHERE purchase_date BETWEEN ? AND ? AND payment_status = 'paid'
                    GROUP BY purchase_date
                    UNION ALL
                    SELECT expense_date as date, SUM(amount) as total
                    FROM expenses WHERE expense_date BETWEEN ? AND ?
                    GROUP BY expense_date
                    UNION ALL
                    SELECT DATE(created_at) as date, SUM(refund_amount) as total
                    FROM returns WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY date
                ) combined GROUP BY date
            ) cash_out ON dates.date = cash_out.date
            ORDER BY dates.date
        ", [$from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to]);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.cashflow_pdf', compact('dailyCashFlow', 'from', 'to'));
            return $pdf->download('Laporan_ArusKas_MAYOKA_' . $from . '_sd_' . $to . '.pdf');
        }

        return Excel::download(new \App\Exports\CashFlowExport($from, $to), 'Laporan_ArusKas_MAYOKA_' . $from . '_sd_' . $to . '.xlsx');
    }
}
