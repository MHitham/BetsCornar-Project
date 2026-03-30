<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuickSaleRequest;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
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
        $q = $request->input('q', '');
        $source = $request->input('source', '');
        // فلتر الحالة — مؤكدة أو ملغية
        $status = $request->input('status', '');

        $invoices = Invoice::query()
            ->with('customer')
            ->when($q, function ($query) use ($q) {
                $query->where('invoice_number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%");
            })
            ->when($source, fn ($query) => $query->where('source', $source))
            // تطبيق فلتر الحالة إذا تم اختياره
            ->when($status, fn($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('invoices.index', compact('invoices', 'q', 'source', 'status'));
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
        $invoice->load(['customer', 'items.product', 'vaccinations']);

        return view('invoices.show', compact('invoice'));
    }

    /**
     * إلغاء فاتورة مع إرجاع الستوك — يستدعي InvoiceService::cancelInvoice()
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
}
