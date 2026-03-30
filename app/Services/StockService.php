<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\InvoiceItemVaccineBatch;
use App\Models\Product;
use App\Models\VaccineBatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    public function increaseStock(Product $product, float $quantity): void
    {
        if ($quantity <= 0 || ! $product->track_stock) {
            return;
        }

        if ($product->type === 'vaccination') {
            // Vaccine stock is derived from batches only.
            $this->recalculateVaccineStock($product);

            return;
        }

        $currentQuantity = (float) $product->quantity;
        $newQuantity = $this->normalizeDecimal($currentQuantity + $quantity);

        $product->update([
            'quantity' => $newQuantity,
            'stock_status' => $this->resolveStockStatus($newQuantity, (float) $product->low_stock_threshold),
        ]);
    }

    /**
     * إرجاع ستوك التطعيم عند إلغاء فاتورة.
     * بيرجع الكميات المخصومة لكل batch عبر invoice_item_vaccine_batches،
     * ثم يعيد حساب products.quantity من الـ batches الصالحة.
     */
    public function restoreVaccineStock(InvoiceItem $invoiceItem): void
    {
        DB::transaction(function () use ($invoiceItem) {
            // جلب كل الـ batches المرتبطة بهذا البند مع lock
            $batches = InvoiceItemVaccineBatch::query()
                ->where('invoice_item_id', $invoiceItem->id)
                ->with('vaccineBatch')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $link) {
                $batch = $link->vaccineBatch;

                if (! $batch) {
                    continue;
                }

                // إرجاع الكمية المخصومة لكل batch
                $batch->update([
                    'quantity_remaining' => $this->normalizeDecimal(
                        (float) $batch->quantity_remaining + (float) $link->quantity
                    ),
                ]);
            }

            // إعادة حساب products.quantity من الـ batches الصالحة فقط
            $product = $invoiceItem->product;

            if ($product) {
                $this->recalculateVaccineStock($product->fresh());
            }
        });
    }

    public function decreaseStock(Product $product, float $quantity): void
    {
        if ($quantity <= 0 || ! $product->track_stock) {
            return;
        }

        if ($product->type === 'vaccination') {
            $this->deductVaccineStockFefo($product, $quantity);

            return;
        }

        $currentQuantity = (float) $product->quantity;

        if ($currentQuantity < $quantity) {
            throw new RuntimeException(__('products.messages.insufficient_stock'));
        }

        $newQuantity = $this->normalizeDecimal($currentQuantity - $quantity);

        $product->update([
            'quantity' => $newQuantity,
            'stock_status' => $this->resolveStockStatus($newQuantity, (float) $product->low_stock_threshold),
        ]);
    }

    public function createVaccineBatch(array $data): VaccineBatch
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $this->guardVaccineProduct($product);

            $received = $this->normalizeDecimal((float) $data['quantity_received']);
            $remaining = array_key_exists('quantity_remaining', $data)
                ? $this->normalizeDecimal((float) $data['quantity_remaining'])
                : $received;

            if ($remaining > $received) {
                throw new RuntimeException(__('vaccine_batches.messages.remaining_exceeds_received'));
            }

            $batch = $product->vaccineBatches()->create([
                'batch_code' => $data['batch_code'] ?? null,
                'received_date' => $data['received_date'],
                'expiry_date' => $data['expiry_date'],
                'quantity_received' => $received,
                'quantity_remaining' => $remaining,
            ]);

            $this->recalculateVaccineStock($product);

            return $batch;
        });
    }

    public function updateVaccineBatch(VaccineBatch $batch, array $data): VaccineBatch
    {
        return DB::transaction(function () use ($batch, $data) {
            $lockedBatch = VaccineBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $originalProduct = Product::query()->lockForUpdate()->findOrFail($lockedBatch->product_id);

            $targetProductId = (int) ($data['product_id'] ?? $lockedBatch->product_id);
            $targetProduct = Product::query()->lockForUpdate()->findOrFail($targetProductId);
            $this->guardVaccineProduct($targetProduct);

            $received = $this->normalizeDecimal((float) $data['quantity_received']);
            $remaining = $this->normalizeDecimal((float) $data['quantity_remaining']);

            if ($remaining > $received) {
                throw new RuntimeException(__('vaccine_batches.messages.remaining_exceeds_received'));
            }

            $lockedBatch->update([
                'product_id' => $targetProduct->id,
                'batch_code' => $data['batch_code'] ?? null,
                'received_date' => $data['received_date'],
                'expiry_date' => $data['expiry_date'],
                'quantity_received' => $received,
                'quantity_remaining' => $remaining,
            ]);

            $this->recalculateVaccineStock($originalProduct);

            if ($originalProduct->id !== $targetProduct->id) {
                $this->recalculateVaccineStock($targetProduct);
            }

            return $lockedBatch->fresh();
        });
    }

    public function deleteVaccineBatch(VaccineBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $lockedBatch = VaccineBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBatch->invoiceItemVaccineBatches()->exists()) {
                throw new RuntimeException(__('vaccine_batches.messages.delete_referenced_error'));
            }

            $product = Product::query()->lockForUpdate()->findOrFail($lockedBatch->product_id);

            $lockedBatch->delete();

            $this->recalculateVaccineStock($product);
        });
    }

    public function deductVaccineStockFefo(Product $vaccineProduct, float $quantity, ?InvoiceItem $invoiceItem = null): array
    {
        $this->guardVaccineProduct($vaccineProduct);

        return DB::transaction(function () use ($vaccineProduct, $quantity, $invoiceItem) {
            $requestedQuantity = $this->normalizeDecimal($quantity);

            $validBatches = VaccineBatch::query()
                ->where('product_id', $vaccineProduct->id)
                ->usable()
                ->fefo()
                ->lockForUpdate()
                ->get();

            $usableQuantity = (float) $validBatches->sum('quantity_remaining');

            if ($usableQuantity < $requestedQuantity) {
                throw new RuntimeException(__('vaccine_batches.messages.insufficient_stock'));
            }

            $remainingToDeduct = $requestedQuantity;
            $deductions = [];

            foreach ($validBatches as $batch) {
                if ($remainingToDeduct <= 0) {
                    break;
                }

                $available = (float) $batch->quantity_remaining;
                $usedQuantity = $this->normalizeDecimal(min($available, $remainingToDeduct));

                if ($usedQuantity <= 0) {
                    continue;
                }

                $batch->update([
                    'quantity_remaining' => $this->normalizeDecimal($available - $usedQuantity),
                ]);

                if ($invoiceItem) {
                    InvoiceItemVaccineBatch::query()->create([
                        'invoice_item_id' => $invoiceItem->id,
                        'vaccine_batch_id' => $batch->id,
                        'quantity' => $usedQuantity,
                    ]);
                }

                $deductions[] = [
                    'vaccine_batch_id' => $batch->id,
                    'quantity' => $usedQuantity,
                ];

                $remainingToDeduct = $this->normalizeDecimal($remainingToDeduct - $usedQuantity);
            }

            $this->recalculateVaccineStock($vaccineProduct->fresh());

            return $deductions;
        });
    }

    public function recalculateVaccineStock(Product $vaccineProduct): void
    {
        $this->guardVaccineProduct($vaccineProduct);

        $usableQuantity = (float) VaccineBatch::query()
            ->where('product_id', $vaccineProduct->id)
            ->usable()
            ->sum('quantity_remaining');

        $usableQuantity = $this->normalizeDecimal($usableQuantity);

        $vaccineProduct->update([
            'track_stock' => true,
            'quantity' => $usableQuantity,
            'stock_status' => $this->resolveStockStatus($usableQuantity, (float) $vaccineProduct->low_stock_threshold),
        ]);
    }

    private function resolveStockStatus(float $quantity, float $threshold): string
    {
        if ($quantity <= 0) {
            return 'out_of_stock';
        }

        if ($quantity <= $threshold) {
            return 'low';
        }

        return 'available';
    }

    private function guardVaccineProduct(Product $product): void
    {
        if ($product->type !== 'vaccination' || ! $product->track_stock) {
            throw new RuntimeException(__('vaccine_batches.messages.invalid_vaccine_product'));
        }
    }

    private function normalizeDecimal(float $value): float
    {
        return round($value, 2);
    }
}