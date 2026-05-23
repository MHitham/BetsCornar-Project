<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        // تم الإضافة: استعلام واحد للإيرادات المؤكدة مجمع بالشهر
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

        // تم الإضافة: استعلام واحد للمصروفات مجمع بالشهر
        $expensesByMonth = Expense::query()
            ->whereYear('expense_date', $year)
            ->selectRaw('MONTH(expense_date) as month, COALESCE(SUM(amount), 0) as expenses')
            ->groupByRaw('MONTH(expense_date)')
            ->orderByRaw('MONTH(expense_date)')
            ->get()
            ->keyBy('month');

        // تم الإضافة: تجميع بيانات 12 شهرًا في Collection واحدة للعرض
        $monthlyData = collect(range(1, 12))->map(function (int $month) use ($revenueByMonth, $expensesByMonth) {
            $revenue = (float) ($revenueByMonth[$month]->revenue ?? 0);
            $visitCount = (int) ($revenueByMonth[$month]->visit_count ?? 0);
            $expenses = (float) ($expensesByMonth[$month]->expenses ?? 0);

            return [
                'month' => $month,
                'month_name' => self::arabicMonthName($month),
                'revenue' => $revenue,
                'visit_count' => $visitCount,
                'expenses' => $expenses,
                'net_profit' => $revenue - $expenses,
            ];
        });

        // تم الإضافة: أعلى المنتجات والخدمات مبيعًا خلال السنة من الفواتير المؤكدة فقط
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

        // تم الإضافة: إجماليات السنة محسوبة من نفس البيانات المجمعة
        $yearlyTotals = [
            'revenue' => $monthlyData->sum('revenue'),
            'expenses' => $monthlyData->sum('expenses'),
            'net_profit' => $monthlyData->sum('net_profit'),
            'visit_count' => $monthlyData->sum('visit_count'),
        ];

        return view('reports.index', compact('year', 'monthlyData', 'topProducts', 'yearlyTotals'));
    }

    public function showMonth(Request $request, int $year, int $month): \Illuminate\View\View
    {
        abort_if($month < 1 || $month > 12, 404);

        $periodStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $monthName = self::arabicMonthName($month);

        // ===== الفواتير المؤكدة =====
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

        // ===== المصروفات =====
        $expenses = Expense::query()
            ->whereBetween('expense_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderByDesc('expense_date')
            ->get();

        $expensesSummary = [
            'total' => $expenses->sum('amount'),
            'count' => $expenses->count(),
        ];

        // ===== المنتجات / الخدمات الأعلى مبيعاً في الشهر =====
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

        // ===== إضافات المخزون (التطعيمات الواردة) =====
        $stockAdditions = \App\Models\VaccineBatch::query()
            ->with('product:id,name,type')
            ->whereBetween('received_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderByDesc('received_date')
            ->get();

        // ===== التطعيمات المنجزة في الشهر =====
        $vaccinations = \App\Models\Vaccination::query()
            ->with(['customer:id,name', 'product:id,name'])
            ->where('is_completed', true)
            ->whereBetween('updated_at', [$periodStart, $periodEnd])
            ->orderByDesc('updated_at')
            ->get();

        $netProfit = $revenueSummary['total'] - $expensesSummary['total'];

        return view('reports.month', compact(
            'year', 'month', 'monthName',
            'invoices', 'revenueSummary',
            'expenses', 'expensesSummary',
            'topProducts', 'stockAdditions',
            'vaccinations', 'netProfit'
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

    // تقرير الربحية - إيرادات وتكاليف وأرباح وديون
    public function profitability(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        // السنوات المتاحة للفلتر
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

        // ── KPIs السنوية ──────────────────────────────────────────

        // إجمالي الإيرادات (فواتير مؤكدة فقط)
        $revenue = Invoice::confirmed()
            ->whereYear('created_at', $year)
            ->sum('total');

        // تكلفة البضاعة المباعة COGS
        $cogs = InvoiceItem::whereHas('invoice', function ($q) use ($year) {
            $q->confirmed()->whereYear('created_at', $year);
        })
            ->whereNotNull('cost_price_at_sale')
            ->selectRaw('SUM(cost_price_at_sale * quantity) as total_cogs')
            ->value('total_cogs') ?? 0;

        // إجمالي المصروفات
        $expenses = Expense::whereYear('created_at', $year)
            ->whereNull('deleted_at')
            ->sum('amount');

        // الأرباح
        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $expenses;
        $margin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0;

        // ── التفصيل الشهري ────────────────────────────────────────
        $months = [];
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

            $months[$m] = [
                'revenue' => $monthRevenue,
                'cogs' => $monthCogs,
                'gross_profit' => $monthGross,
                'expenses' => $monthExpenses,
                'net_profit' => $monthNet,
                'margin' => $monthMargin,
            ];
        }

        // ── ديون الموردين ─────────────────────────────────────────
        $supplierDebts = \App\Models\PurchaseOrder::with('supplier')
            ->whereColumn('amount_paid', '<', 'total_cost')
            ->orderByDesc('purchased_at')
            ->get();

        // ── ديون العملاء ──────────────────────────────────────────
        $customerDebts = Invoice::confirmed()
            ->with('customer')
            ->whereColumn('amount_paid', '<', 'total')
            ->where('amount_paid', '>=', 0)
            ->orderByDesc('created_at')
            ->get();

        return view('reports.profitability', compact(
            'year', 'availableYears',
            'revenue', 'cogs', 'expenses',
            'grossProfit', 'netProfit', 'margin',
            'months',
            'supplierDebts',
            'customerDebts'
        ));
    }
}
