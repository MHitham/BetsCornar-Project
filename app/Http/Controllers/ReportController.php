<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
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
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as visit_count, COALESCE(SUM(total), 0) as revenue')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // تم الإضافة: استعلام واحد للمصروفات مجمع بالشهر
        $expensesByMonth = Expense::query()
            ->whereYear('expense_date', $year)
            ->selectRaw('MONTH(expense_date) as month, COALESCE(SUM(amount), 0) as expenses')
            ->groupBy('month')
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

    // تم الإضافة: أسماء الأشهر العربية لعرض التقرير السنوي
    private static function arabicMonthName(int $month): string
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
}