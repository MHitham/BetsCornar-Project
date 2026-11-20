<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseService $purchaseService) {}

    public function index(): View
    {
        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->latest()
            ->paginate(20);

        return view('purchases.index', compact('orders'));
    }

    public function create(): View
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'price']);

        return view('purchases.create', compact('suppliers', 'products'));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $order = $this->purchaseService->createPurchaseOrder($request->validated());

        return redirect()->route('purchases.show', $order)
            ->with('success', 'تم إضافة فاتورة الشراء بنجاح — '.$order->order_number);
    }

    public function show(PurchaseOrder $purchase): View
    {
        $purchase->load(['supplier', 'items.product', 'items.batch', 'payments.creator']);

        return view('purchases.show', compact('purchase'));
    }

    public function pay(PurchaseOrder $purchase): RedirectResponse
    {
        $amount = (float) request()->input('amount', 0);
        $notes = request()->input('notes');
        $isFromClinicCash = filter_var(request()->input('is_from_clinic_cash', false), FILTER_VALIDATE_BOOLEAN);

        if ($amount <= 0) {
            return back()->with('error', 'المبلغ يجب أن يكون أكبر من صفر');
        }

        $remaining = (float) $purchase->total_cost - (float) $purchase->amount_paid;

        if ($amount > $remaining) {
            return back()->with('error', 'المبلغ المدخل أكبر من المتبقي ('.number_format($remaining, 2).' ج)');
        }

        $this->purchaseService->addPayment($purchase, [
            'amount' => $amount,
            'notes' => $notes,
            'paid_at' => now()->toDateString(),
            'is_from_clinic_cash' => $isFromClinicCash,
        ]);

        return back()->with('success', 'تم تسجيل الدفعة بنجاح');
    }

    public function cancel(PurchaseOrder $purchase): RedirectResponse
    {
        $validated = request()->validate(['reason' => ['nullable', 'string', 'max:255']]);
        try {
            $this->purchaseService->cancelPurchase($purchase, $validated['reason'] ?? null);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', 'تم إلغاء فاتورة الشراء بنجاح.');
    }
}
