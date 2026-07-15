<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelInvoiceRequest;
use App\Http\Requests\StoreQuickSaleRequest;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Mpdf\Mpdf;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function index(Request $request)
    {
        $isEmployee = auth()->user()->hasRole('employee');

        $q = $request->input('q', '');
        $source = $request->input('source', '');
        $status = $request->input('status', '');
        $period = $request->input('period', 'today');
        $date = $request->input('date');

        $baseQuery = Invoice::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($subQuery) use ($q) {
                    $subQuery->where('invoice_number', 'like', "%{$q}%")
                        ->orWhere('customer_name', 'like', "%{$q}%");
                });
            })
            ->when($source, fn ($query) => $query->where('source', $source));

        $countToday = (clone $baseQuery)->whereDate('created_at', today())->count();
        $countMonth = (clone $baseQuery)
            ->whereMonth('created_at', today()->month)
            ->whereYear('created_at', today()->year)
            ->count();
        $countAll = (clone $baseQuery)->count();

        $query = clone $baseQuery;
        if ($date) {
            $query->whereDate('created_at', $date);
        } elseif ($period === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($period === 'month') {
            $query->whereMonth('created_at', today()->month)
                ->whereYear('created_at', today()->year);
        }

        $invoices = $query

            ->select(['id', 'invoice_number', 'customer_name', 'source', 'total', 'status', 'created_at', 'cancelled_at'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $hasFilters = $request->hasAny(['q', 'source', 'period', 'date', 'page']);

        $showRevenueBar = true;
        $revenueSummary = $this->resolveRevenueSummary($date, $period);

        return view('invoices.index', compact(
            'invoices',
            'q',
            'source',
            'status',
            'date',
            'period',
            'countToday',
            'countMonth',
            'countAll',
            'hasFilters',
            'isEmployee',
            'showRevenueBar',
            'revenueSummary',
        ));
    }

    public function create()
    {
        $products = Product::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'stock_status', 'quantity', 'track_stock', 'type']);

        return view('invoices.create', compact('products'));
    }

    public function store(StoreQuickSaleRequest $request)
    {
        try {
            $invoice = $this->invoiceService->saveQuickSale($request->validated());

            return redirect()
                ->route('invoices.index')
                ->with('success', __('invoices.messages.created'));
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['stock' => $e->getMessage()]);
        }
    }

    public function show(Invoice $invoice)
    {

        $invoice->load([
            'items.product',
            'items.vaccineBatches',
            'vaccinations.product',
            'payments',
            'returns.items.product',
        ]);

        $showRevenueBar = true;
        $revenueSummary = $showRevenueBar
            ? $this->invoiceService->getDailyRevenueSummary($invoice->created_at->toDateString())
            : null;

        return view('invoices.show', compact('invoice', 'revenueSummary', 'showRevenueBar'));
    }

    private function resolveRevenueSummary(?string $date, string $period): array
    {
        if ($date) {
            $summary = $this->invoiceService->getDailyRevenueSummary($date);
            $summary['period_type'] = 'day';

            return $summary;
        }

        if ($period === 'month') {
            $summary = $this->invoiceService->getMonthlyRevenueSummary(
                (int) today()->year,
                (int) today()->month,
            );
            $summary['period_type'] = 'month';
            $summary['label'] = ReportController::arabicMonthName((int) today()->month).' '.today()->year;

            return $summary;
        }

        if ($period === 'all') {
            return [
                'date' => '',
                'label' => 'الكل',
                'gross_revenue' => (float) Invoice::confirmed()->sum('total'),
                'invoice_count' => (int) Invoice::confirmed()->count(),
                'cancelled_count' => (int) Invoice::cancelled()->count(),
                'customer_visits' => (int) Invoice::confirmed()->where('source', 'customer')->count(),
                'quick_sales' => (int) Invoice::confirmed()->where('source', '!=', 'customer')->count(),
                'period_type' => 'all',
            ];
        }

        $summary = $this->invoiceService->getDailyRevenueSummary(today()->toDateString());
        $summary['period_type'] = 'day';

        return $summary;
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice)
    {
        $validated = $request->validated();

        try {

            $this->invoiceService->cancelInvoice(
                $invoice,
                $validated['cancellation_reason'] ?? null
            );

            return redirect()
                ->route('invoices.show', $invoice)
                ->with('success', __('invoices.messages.cancelled'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load(['items.product', 'customer', 'vaccinations.product']);

        $html = view('invoices.pdf', compact('invoice'))->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'sans-serif',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);

        $filename = 'فاتورة-'.$invoice->invoice_number.'.pdf';

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
