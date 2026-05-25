<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        $revenueByMonth = Invoice::confirmed()
            ->whereYear('created_at', $year)
            ->selectRaw('
                MONTH(created_at) as month,
                COUNT(*) as visit_count,
                COALESCE(SUM(total), 0) as revenue
            ')
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month');

        $expensesByMonth = Expense::query()
            ->whereYear('expense_date', $year)
            ->selectRaw('MONTH(expense_date) as month, COALESCE(SUM(amount), 0) as expenses')
            ->groupByRaw('MONTH(expense_date)')
            ->orderByRaw('MONTH(expense_date)')
            ->get()
            ->keyBy('month');

        $newCustomersByMonth = Customer::query()
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as new_customers')
            ->groupByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month');

        $cogsByMonth = \App\Models\InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'confirmed')
            ->whereYear('invoices.created_at', $year)
            ->whereNotNull('invoice_items.cost_price_at_sale')
            ->selectRaw('MONTH(invoices.created_at) as month, SUM(invoice_items.cost_price_at_sale * invoice_items.quantity) as total_cogs')
            ->groupByRaw('MONTH(invoices.created_at)')
            ->get()
            ->keyBy('month');

        $monthlyData = collect(range(1, 12))->map(function (int $month) use ($revenueByMonth, $expensesByMonth, $cogsByMonth, $newCustomersByMonth) {
            $revenue = (float) ($revenueByMonth[$month]->revenue ?? 0);
            $visitCount = (int) ($revenueByMonth[$month]->visit_count ?? 0);
            $expenses = (float) ($expensesByMonth[$month]->expenses ?? 0);

            $cogs = (float) ($cogsByMonth[$month]->total_cogs ?? 0);
            $grossProfit = $revenue - $cogs;
            $netProfit = $grossProfit - $expenses;
            $margin = $revenue > 0
                ? round(($netProfit / $revenue) * 100, 1)
                : 0;

            return [
                'month' => $month,
                'month_name' => self::arabicMonthName($month),
                'revenue' => $revenue,
                'visit_count' => $visitCount,
                'expenses' => $expenses,
                'net_profit' => $revenue - $expenses,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'margin' => $margin,
                'new_customers' => (int) ($newCustomersByMonth[$month]->new_customers ?? 0),
            ];
        });

        $topProducts = Invoice::query()
            ->confirmed()
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->whereYear('invoices.created_at', $year)
            ->select(
                'products.id',
                'products.name',
                'products.type',
                DB::raw('SUM(invoice_items.quantity) as total_quantity'),
                DB::raw('SUM(invoice_items.line_total) as total_sales')
            )
            ->groupBy('products.id', 'products.name', 'products.type')
            ->orderByDesc('total_sales')
            ->limit(15)
            ->get();

        $yearlyTotals = [
            'revenue' => $monthlyData->sum('revenue'),
            'expenses' => $monthlyData->sum('expenses'),
            'net_profit' => $monthlyData->sum('net_profit'),
            'visit_count' => $monthlyData->sum('visit_count'),
        ];

        $bestMonth = $monthlyData->sortByDesc('revenue')->first()['month'] ?? null;

        $maxRevenue = $monthlyData->max('revenue') ?: 1;
        $monthlyData = $monthlyData->map(function ($row) use ($maxRevenue) {
            $row['has_data'] = $row['revenue'] > 0 || $row['expenses'] > 0;
            $row['revenue_percent'] = round(($row['revenue'] / $maxRevenue) * 100);

            return $row;
        });

        $profitPositive = $yearlyTotals['net_profit'] >= 0;

        return view('reports.index', compact(
            'year', 'monthlyData', 'topProducts', 'yearlyTotals',
            'profitPositive', 'bestMonth'
        ));
    }

    public function showMonth(Request $request, int $year, int $month): \Illuminate\View\View
    {
        abort_if($month < 1 || $month > 12, 404);

        $periodStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $monthName = self::arabicMonthName($month);

        $invoices = Invoice::confirmed()
            ->with(['items.product'])
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->latest()
            ->get();

        $revenueSummary = [
            'total' => $invoices->sum('total'),
            'count' => $invoices->count(),
            'avg' => $invoices->avg('total') ?? 0,
            'customer_visits' => $invoices->where('source', 'customer')->count(),
            'quick_sales' => $invoices->where('source', '!=', 'customer')->count(),
        ];

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderByDesc('expense_date')
            ->get();

        $expensesSummary = [
            'total' => $expenses->sum('amount'),
            'count' => $expenses->count(),
        ];

        $topProducts = Invoice::query()
            ->confirmed()
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->whereBetween('invoices.created_at', [$periodStart, $periodEnd])
            ->select(
                'products.id',
                'products.name',
                'products.type',
                DB::raw('SUM(invoice_items.quantity) as total_quantity'),
                DB::raw('SUM(invoice_items.line_total) as total_sales')
            )
            ->groupBy('products.id', 'products.name', 'products.type')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get();

        $stockAdditions = \App\Models\VaccineBatch::query()
            ->with('product:id,name,type')
            ->whereBetween('received_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderByDesc('received_date')
            ->get();

        $vaccinations = \App\Models\Vaccination::query()
            ->with(['customer:id,name', 'product:id,name'])
            ->where('is_completed', true)
            ->whereBetween('updated_at', [$periodStart, $periodEnd])
            ->orderByDesc('updated_at')
            ->get();

        $netProfit = $revenueSummary['total'] - $expensesSummary['total'];

        $cogs = InvoiceItem::whereHas('invoice', function ($q) use ($periodStart, $periodEnd) {
            $q->confirmed()->whereBetween('created_at', [$periodStart, $periodEnd]);
        })
            ->whereNotNull('cost_price_at_sale')
            ->selectRaw('SUM(cost_price_at_sale * quantity) as total_cogs')
            ->value('total_cogs') ?? 0;

        $grossProfit = $revenueSummary['total'] - $cogs;

        $newCustomers = Customer::query()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->latest()
            ->get();

        $profitPositive = $netProfit >= 0;
        $grossPositive = $grossProfit >= 0;

        return view('reports.month', compact(
            'year', 'month', 'monthName',
            'invoices', 'revenueSummary',
            'expenses', 'expensesSummary',
            'topProducts', 'stockAdditions',
            'vaccinations', 'netProfit', 'cogs', 'grossProfit', 'newCustomers',
            'profitPositive', 'grossPositive'
        ));
    }

    public static function arabicMonthName(int $month): string
    {
        return [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ][$month] ?? '';
    }

    public function profitability(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        $availableYears = Invoice::confirmed()
            ->selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        if (! in_array(now()->year, $availableYears)) {
            $availableYears[] = now()->year;
            rsort($availableYears);
        }

        $revenue = Invoice::confirmed()
            ->whereYear('created_at', $year)
            ->sum('total');

        $cogs = InvoiceItem::whereHas('invoice', function ($q) use ($year) {
            $q->confirmed()->whereYear('created_at', $year);
        })
            ->whereNotNull('cost_price_at_sale')
            ->selectRaw('SUM(cost_price_at_sale * quantity) as total_cogs')
            ->value('total_cogs') ?? 0;

        $expenses = Expense::whereYear('created_at', $year)
            ->whereNull('deleted_at')
            ->sum('amount');

        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $expenses;
        $margin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0;

        $months = Cache::remember("report-profitability-months-{$year}", now()->addHours(2), function () use ($year) {
            $result = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthRevenue = Invoice::confirmed()
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $m)
                    ->sum('total');

                $monthCogs = InvoiceItem::whereHas('invoice', function ($q) use ($year, $m) {
                    $q->confirmed()
                        ->whereYear('created_at', $year)
                        ->whereMonth('created_at', $m);
                })
                    ->whereNotNull('cost_price_at_sale')
                    ->selectRaw('SUM(cost_price_at_sale * quantity) as total')
                    ->value('total') ?? 0;

                $monthExpenses = Expense::whereYear('created_at', $year)
                    ->whereMonth('created_at', $m)
                    ->whereNull('deleted_at')
                    ->sum('amount');

                $monthGross = $monthRevenue - $monthCogs;
                $monthNet = $monthGross - $monthExpenses;
                $monthMargin = $monthRevenue > 0
                    ? round(($monthNet / $monthRevenue) * 100, 1)
                    : 0;

                $result[$m] = [
                    'revenue' => $monthRevenue,
                    'cogs' => $monthCogs,
                    'gross_profit' => $monthGross,
                    'expenses' => $monthExpenses,
                    'net_profit' => $monthNet,
                    'margin' => $monthMargin,
                ];
            }

            return $result;
        });

        $supplierDebts = \App\Models\PurchaseOrder::with('supplier')
            ->whereColumn('amount_paid', '<', 'total_cost')
            ->orderByDesc('purchased_at')
            ->get();

        $customerDebts = Invoice::confirmed()
            ->with('customer')
            ->whereColumn('amount_paid', '<', 'total')
            ->where('amount_paid', '>=', 0)
            ->orderByDesc('created_at')
            ->get();

        $totalSupplierDebts = $supplierDebts->sum(fn ($po) => $po->total_cost - $po->amount_paid);
        $totalCustomerDebts = $customerDebts->sum(fn ($inv) => $inv->total - $inv->amount_paid);

        $grossPositive = $grossProfit >= 0;
        $netPositive = $netProfit >= 0;

        return view('reports.profitability', compact(
            'year', 'availableYears',
            'revenue', 'cogs', 'expenses',
            'grossProfit', 'netProfit', 'margin',
            'months',
            'supplierDebts', 'customerDebts',
            'totalSupplierDebts', 'totalCustomerDebts',
            'grossPositive', 'netPositive'
        ));
    }
}
