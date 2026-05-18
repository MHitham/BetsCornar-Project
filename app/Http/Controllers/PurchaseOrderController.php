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

    // قائمة فواتير الشراء
    public function index(): View
    {
        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->latest()
            ->paginate(20);

        return view('purchases.index', compact('orders'));
    }

    // فورم إضافة فاتورة شراء
    public function create(): View
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products  = Product::where('is_active', true)
                            ->orderBy('name')
                            ->get(['id', 'name', 'type', 'price']);

        return view('purchases.create', compact('suppliers', 'products'));
    }

    // حفظ فاتورة الشراء الجديدة
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // تحديث سعر بيع المنتج لو المستخدم اختار ذلك
        foreach ($data['items'] as $index => $item) {
            $updatePrice = $data['update_selling_price'][$index] ?? false;
            if ($updatePrice) {
                Product::where('id', $item['product_id'])
                       ->update(['price' => $item['selling_price_per_unit']]);
            }
        }

        $order = $this->purchaseService->createPurchaseOrder($data);

        return redirect()->route('purchases.show', $order)
            ->with('success', 'تم إضافة فاتورة الشراء بنجاح — ' . $order->order_number);
    }

    // عرض تفاصيل فاتورة الشراء
    public function show(PurchaseOrder $purchase): View
    {
        $purchase->load(['supplier', 'items.product', 'items.batch', 'payments.creator']);

        return view('purchases.show', compact('purchase'));
    }

    // تسجيل دفعة جديدة
    public function pay(PurchaseOrder $purchase): RedirectResponse
    {
        $amount = (float) request()->input('amount', 0);
        $notes  = request()->input('notes');

        if ($amount <= 0) {
            return back()->with('error', 'المبلغ يجب أن يكون أكبر من صفر');
        }

        $remaining = (float) $purchase->total_cost - (float) $purchase->amount_paid;

        if ($amount > $remaining) {
            return back()->with('error', 'المبلغ المدخل أكبر من المتبقي (' . number_format($remaining, 2) . ' ج)');
        }

        $this->purchaseService->addPayment($purchase, [
            'amount'  => $amount,
            'notes'   => $notes,
            'paid_at' => now()->toDateString(),
        ]);

        return back()->with('success', 'تم تسجيل الدفعة بنجاح');
    }
}
