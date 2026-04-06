<?php

namespace App\Http\Controllers;

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

    /**
     * Show invoice list with optional search/filter.
     */
    public function index(Request $request)
    {
        $isEmployee = auth()->user()->hasRole('employee');

        if ($isEmployee) {
            $now = \Carbon\Carbon::now();
            $businessDayStart = $now->copy()->startOfDay()->addHours(2);

            if ($now->lt($businessDayStart)) {
                $periodStart = $businessDayStart->copy()->subDay();
                $periodEnd = $businessDayStart->copy()->subSecond();
            } else {
                $periodStart = $businessDayStart;
                $periodEnd = $businessDayStart->copy()->addDay()->subSecond();
            }

            $invoices = Invoice::query()
                // تم التعديل: إزالة eager loading غير المستخدم للعلاقة customer من فهرس الفواتير للموظف
                ->where('created_by', auth()->id())
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                // تم التعديل: تحديد أعمدة قائمة الفواتير المطلوبة فقط في فهرس الموظف
                ->select(['id', 'invoice_number', 'customer_name', 'source', 'total', 'status', 'created_at'])
                ->latest()
                ->paginate(25)
                ->withQueryString();

            return view('invoices.index', [
                'invoices' => $invoices,
                'q' => '',
                'source' => '',
                'status' => '',
                'date' => null,
                'period' => 'today',
                'countToday' => 0,
                'countMonth' => 0,
                'countAll' => 0,
                'hasFilters' => true,
                'isEmployee' => true,
            ]);
        }

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
            // تم التعديل: إزالة eager loading غير المستخدم للعلاقة customer من فهرس الفواتير للأدمن
            // تم التعديل: تحديد أعمدة قائمة الفواتير المطلوبة فقط في فهرس الأدمن
            ->select(['id', 'invoice_number', 'customer_name', 'source', 'total', 'status', 'created_at', 'cancelled_at'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $hasFilters = $request->hasAny(['q', 'source', 'period', 'date', 'page']);

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
            'isEmployee'
        ));
    }
    /**
     * Show the quick-sale form.
     */
    public function create()
    {
        $products = Product::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'stock_status', 'quantity', 'track_stock', 'type']);

        return view('invoices.create', compact('products'));
    }

    /**
     * Save a quick-sale invoice (transactional).
     */
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

    /**
     * Show a single invoice with its items.
     */
    public function show(Invoice $invoice)
    {
        // تم التعديل: تحميل منتجات البنود مع منتجات التطعيمات لتجنب N+1 داخل صفحة الفاتورة
        $invoice->load(['items.product', 'vaccinations.product']);

        return view('invoices.show', compact('invoice'));
    }

    /**
     * إلغاء فاتورة مع إرجاع الستوك - يستدعي InvoiceService::cancelInvoice()
     */
    public function cancel(Request $request, Invoice $invoice)
    {
        try {
            // تمرير سبب الإلغاء إن وُجد
            $this->invoiceService->cancelInvoice(
                $invoice,
                $request->input('cancellation_reason')
            );

            return redirect()
                ->route('invoices.show', $invoice)
                ->with('success', __('invoices.messages.cancelled'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }
    }

    /**
     * Download Invoice as PDF using mPDF natively
     */
    public function pdf(Invoice $invoice)
    {
        $invoice->load(['items.product', 'customer']);

        // Render the view to HTML string
        $html = view('invoices.pdf', compact('invoice'))->render();

        // Initialize native mPDF
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