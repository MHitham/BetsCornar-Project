<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\VaccineBatch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    /**
     * إنشاء فاتورة شراء جديدة مع بنودها ومعالجة المخزون
     * كل العملية داخل transaction واحدة
     *
     * @param array $data
     * @return PurchaseOrder
     */
    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {

            // حساب إجمالي التكلفة من البنود
            $totalCost = collect($data['items'])->sum(function ($item) {
                return $item['quantity'] * $item['purchase_price_per_unit'];
            });

            // إنشاء فاتورة الشراء
            $order = PurchaseOrder::create([
                'supplier_id'  => $data['supplier_id'] ?? null,
                'order_number' => $this->generateOrderNumber(),
                'total_cost'   => $totalCost,
                'amount_paid'  => 0,
                'notes'        => $data['notes'] ?? null,
                'purchased_at' => $data['purchased_at'],
            ]);

            // معالجة كل بند في فاتورة الشراء
            foreach ($data['items'] as $itemData) {
                $this->processItem($order, $itemData);
            }

            // تسجيل الدفعة الأولى لو فيه مبلغ مدفوع
            if (!empty($data['amount_paid']) && $data['amount_paid'] > 0) {
                $this->addPayment($order, [
                    'amount' => $data['amount_paid'],
                    'notes'  => 'دفعة أولى عند الشراء',
                    'paid_at' => $data['purchased_at'],
                ]);
            }

            return $order->fresh(['items.product', 'items.batch', 'supplier', 'payments']);
        });
    }

    /**
     * معالجة بند واحد من فاتورة الشراء
     * يحدد نوع المنتج ويتصرف بناءً عليه
     */
    private function processItem(PurchaseOrder $order, array $itemData): void
    {
        $product = Product::findOrFail($itemData['product_id']);

        // إنشاء بند فاتورة الشراء (batch_id سيُضاف لو كان لقاح)
        $item = PurchaseOrderItem::create([
            'purchase_order_id'       => $order->id,
            'product_id'              => $product->id,
            'quantity'                => $itemData['quantity'],
            'purchase_price_per_unit' => $itemData['purchase_price_per_unit'],
            'selling_price_per_unit'  => $itemData['selling_price_per_unit'],
            'expiry_date'             => $itemData['expiry_date'] ?? null,
            'batch_id'                => null,
        ]);

        if ($product->type === 'vaccination') {
            // معالجة اللقاحات: إنشاء باتش جديد
            $this->processVaccinationItem($item, $product, $itemData);
        } else {
            // معالجة المنتجات العادية: تحديث average_cost والكمية
            $this->processRegularItem($item, $product, $itemData);
        }
    }

    /**
     * معالجة بند اللقاح: إنشاء vaccine_batch جديد
     */
    private function processVaccinationItem(
        PurchaseOrderItem $item,
        Product $product,
        array $itemData
    ): void {
        // إنشاء باتش جديد للقاح
        $batch = VaccineBatch::create([
            'product_id'         => $product->id,
            'batch_code'         => $this->generateBatchCode($product->id),
            'received_date'      => $item->purchaseOrder->purchased_at,
            'expiry_date'        => $itemData['expiry_date'],
            'quantity_received'  => $itemData['quantity'],
            'quantity_remaining' => $itemData['quantity'],
            'purchase_price'     => $itemData['purchase_price_per_unit'],
            'selling_price'      => $itemData['selling_price_per_unit'],
        ]);

        // ربط الباتش بالبند
        $item->update(['batch_id' => $batch->id]);

        // إعادة حساب كمية المنتج من الباتشات الصالحة
        $this->recalculateVaccineStock($product);
    }

    /**
     * معالجة المنتج العادي: تحديث average_cost والكمية
     */
    private function processRegularItem(
        PurchaseOrderItem $item,
        Product $product,
        array $itemData
    ): void {
        $newQty   = (float) $itemData['quantity'];
        $newPrice = (float) $itemData['purchase_price_per_unit'];
        $oldQty   = (float) $product->quantity;
        $oldAvg   = (float) $product->average_cost;

        // حساب التكلفة المتوسطة المرجحة الجديدة
        // (الكمية القديمة × متوسط القديم + الكمية الجديدة × السعر الجديد) ÷ إجمالي الكمية
        if (($oldQty + $newQty) > 0) {
            $newAvgCost = (($oldQty * $oldAvg) + ($newQty * $newPrice)) / ($oldQty + $newQty);
        } else {
            $newAvgCost = $newPrice;
        }

        // تحديث المنتج
        $product->update([
            'average_cost' => round($newAvgCost, 2),
            'quantity'     => $oldQty + $newQty,
        ]);

        // تحديث stock_status
        $this->updateStockStatus($product);
    }

    /**
     * تسجيل دفعة جديدة لفاتورة شراء
     */
    public function addPayment(PurchaseOrder $order, array $data): PurchasePayment
    {
        return DB::transaction(function () use ($order, $data) {

            // إنشاء سجل الدفعة
            $payment = PurchasePayment::create([
                'purchase_order_id' => $order->id,
                'amount'            => $data['amount'],
                'notes'             => $data['notes'] ?? null,
                'paid_at'           => $data['paid_at'] ?? now()->toDateString(),
                'created_by'        => Auth::id(),
            ]);

            // تحديث إجمالي المدفوع في فاتورة الشراء
            $totalPaid = $order->payments()->sum('amount');
            $order->update(['amount_paid' => $totalPaid]);

            return $payment;
        });
    }

    /**
     * إعادة حساب كمية اللقاح من الباتشات الصالحة فقط
     */
    private function recalculateVaccineStock(Product $product): void
    {
        $usableQty = VaccineBatch::where('product_id', $product->id)
            ->where('expiry_date', '>=', now()->toDateString())
            ->where('quantity_remaining', '>', 0)
            ->sum('quantity_remaining');

        $product->update(['quantity' => $usableQty]);
        $this->updateStockStatus($product);
    }

    /**
     * تحديث حالة المخزون بناءً على الكمية والحد الأدنى
     */
    private function updateStockStatus(Product $product): void
    {
        $product->refresh();
        $qty       = (float) $product->quantity;
        $threshold = (float) $product->low_stock_threshold;

        $status = match (true) {
            $qty <= 0              => 'out_of_stock',
            $qty <= $threshold     => 'low',
            default                => 'available',
        };

        $product->update(['stock_status' => $status]);
    }

    /**
     * توليد رقم فاتورة شراء تسلسلي (PO-000001)
     */
    public function generateOrderNumber(): string
    {
        $last = PurchaseOrder::orderBy('id', 'desc')->first();
        $next = $last ? ((int) str_replace('PO-', '', $last->order_number)) + 1 : 1;
        return 'PO-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * توليد كود باتش تلقائي للقاح
     */
    private function generateBatchCode(int $productId): string
    {
        $count = VaccineBatch::where('product_id', $productId)->count() + 1;
        return 'BATCH-' . $productId . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
