<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\StockMovement;
use App\Models\Purchase;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Dashboard stats (owner).
     */
    public function dashboard(): JsonResponse
    {
        $today = now()->toDateString();

        // Today's revenue
        $todayRevenue = Transaction::whereDate('created_at', $today)->sum('total');
        $todayTransactions = Transaction::whereDate('created_at', $today)->count();

        // Today's cost (HPP)
        $todayCost = TransactionItem::whereHas('transaction', fn ($q) => $q->whereDate('created_at', $today))
            ->selectRaw('SUM(qty * cost_price) as total_cost')
            ->value('total_cost') ?? 0;

        $todayProfit = $todayRevenue - $todayCost;

        // Monthly revenue
        $monthRevenue = Transaction::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');

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

        // Revenue chart (last 7 days)
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $revenue = Transaction::whereDate('created_at', $date)->sum('total');
            $chartData[] = [
                'date' => now()->subDays($i)->format('d/m'),
                'revenue' => (float) $revenue,
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

        // Calculate HPP per day
        $dailyReport = $dailyData->map(function ($day) {
            $cost = TransactionItem::whereHas('transaction', fn ($q) =>
                    $q->whereDate('created_at', $day->date))
                ->selectRaw('SUM(qty * cost_price) as total_cost')
                ->value('total_cost') ?? 0;

            return [
                'date' => $day->date,
                'tx_count' => $day->tx_count,
                'revenue' => (float) $day->revenue,
                'cost' => (float) $cost,
                'profit' => (float) $day->revenue - (float) $cost,
                'avg_per_tx' => round((float) $day->avg_per_tx),
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

        // Add shift count and avg cash difference
        $report = $data->map(function ($row) use ($from, $to) {
            $shifts = Shift::where('user_id', $row->user_id)
                ->where('status', 'closed')
                ->whereBetween(DB::raw('DATE(started_at)'), [$from, $to])
                ->get();

            return [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'shift_count' => $shifts->count(),
                'tx_count' => $row->tx_count,
                'total_revenue' => (float) $row->total_revenue,
                'avg_cash_diff' => $shifts->count() > 0
                    ? round($shifts->avg('cash_difference'))
                    : 0,
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
        $revenue = (float) Transaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->sum('total');

        $txCount = Transaction::whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->count();

        // HPP (Cost of Goods Sold) from transactions
        $hpp = (float) (TransactionItem::whereHas('transaction', fn ($q) =>
                $q->whereBetween(DB::raw('DATE(created_at)'), [$from, $to]))
            ->selectRaw('SUM(qty * cost_price) as total_cost')
            ->value('total_cost') ?? 0);

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
}
