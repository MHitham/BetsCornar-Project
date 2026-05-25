<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Vaccination;
use App\Models\VaccineBatch;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    public function getAlerts(): array
    {

        return Cache::remember('notifications.alerts', 60, function (): array {
            return array_merge(
                $this->getLowStockAlerts(),
                $this->getOutOfStockAlerts(),
                $this->getExpiredVaccineAlerts(),
                $this->getExpiringVaccineAlerts(),
                $this->getVaccinationTodayAlerts(),
                $this->getVaccinationOverdueAlerts(),
                $this->getUnpaidInvoiceAlerts(),
                $this->getSupplierDebtAlerts(),
            );
        });
    }

    private function getLowStockAlerts(): array
    {

        $products = Product::query()
            ->where('stock_status', 'low')
            ->where('track_stock', true)
            ->where('is_active', true)
            ->get(['id', 'name']);

        $alerts = [];

        foreach ($products as $product) {
            $alerts[] = [
                'id' => 'low-stock-'.$product->id,
                'type' => 'low_stock',
                'title' => 'مخزون منخفض',
                'message' => 'منتج '.$product->name.' وصل للحد الأدنى',
                'severity' => 'warning',
                'url' => '/products',
            ];
        }

        return $alerts;
    }

    private function getOutOfStockAlerts(): array
    {

        $products = Product::query()
            ->where('stock_status', 'out_of_stock')
            ->where('track_stock', true)
            ->where('is_active', true)
            ->get(['id', 'name']);

        $alerts = [];

        foreach ($products as $product) {
            $alerts[] = [
                'id' => 'out-stock-'.$product->id,
                'type' => 'out_of_stock',
                'title' => 'نفد المخزون',
                'message' => 'منتج '.$product->name.' نفد من المخزون',
                'severity' => 'danger',
                'url' => '/products',
            ];
        }

        return $alerts;
    }

    private function getExpiredVaccineAlerts(): array
    {

        $batches = VaccineBatch::query()
            ->whereDate('expiry_date', '<', today())
            ->where('quantity_remaining', '>', 0)
            ->with('product:id,name')
            ->get();

        $alerts = [];

        foreach ($batches as $batch) {
            $productName = $batch->product?->name ?? 'غير معروف';

            $alerts[] = [
                'id' => 'expired-batch-'.$batch->id,
                'type' => 'vaccine_expired',
                'title' => 'لقاح منتهي الصلاحية',
                'message' => 'باتش '.$batch->batch_code.' من '.$productName.' منتهي الصلاحية',
                'severity' => 'danger',
                'url' => '/vaccine-batches',
            ];
        }

        return $alerts;
    }

    private function getExpiringVaccineAlerts(): array
    {

        $batches = VaccineBatch::query()
            ->whereBetween('expiry_date', [today(), today()->addDays(5)])
            ->where('quantity_remaining', '>', 0)
            ->with('product:id,name')
            ->get();

        $alerts = [];

        foreach ($batches as $batch) {
            $productName = $batch->product?->name ?? 'غير معروف';
            $expiryDate = $batch->expiry_date->format('Y-m-d');

            $alerts[] = [
                'id' => 'expiring-batch-'.$batch->id,
                'type' => 'vaccine_expiring',
                'title' => 'لقاح قرب ينتهي',
                'message' => 'باتش '.$productName.' ينتهي في '.$expiryDate,
                'severity' => 'warning',
                'url' => '/vaccine-batches',
            ];
        }

        return $alerts;
    }

    private function getVaccinationTodayAlerts(): array
    {

        $count = Vaccination::query()
            ->whereDate('next_dose_date', today())
            ->where('is_completed', false)
            ->count();

        if ($count === 0) {
            return [];
        }

        $todayDate = today()->toDateString();

        return [[
            'id' => 'vaccination-today-'.$todayDate,
            'type' => 'vaccination_today',
            'title' => 'مواعيد تطعيم اليوم',
            'message' => 'عندك '.$count.' موعد تطعيم اليوم',
            'severity' => 'info',
            'url' => '/vaccinations',
        ]];
    }

    private function getVaccinationOverdueAlerts(): array
    {

        $count = Vaccination::query()
            ->whereDate('next_dose_date', '<', today())
            ->where('is_completed', false)
            ->count();

        if ($count === 0) {
            return [];
        }

        $todayDate = today()->toDateString();

        return [[
            'id' => 'vaccination-overdue-'.$todayDate,
            'type' => 'vaccination_overdue',
            'title' => 'تطعيمات متأخرة',
            'message' => 'عندك '.$count.' تطعيم فات موعده',
            'severity' => 'danger',
            'url' => '/vaccinations',
        ]];
    }

    private function getUnpaidInvoiceAlerts(): array
    {

        $count = Invoice::confirmed()
            ->whereColumn('amount_paid', '<', 'total')
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        if ($count === 0) {
            return [];
        }

        $todayDate = today()->toDateString();

        return [[
            'id' => 'unpaid-invoices-'.$todayDate,
            'type' => 'unpaid_invoice',
            'title' => 'فواتير غير مدفوعة',
            'message' => 'عندك '.$count.' فاتورة عليها دين من أكتر من 7 أيام',
            'severity' => 'warning',
            'url' => '/invoices',
        ]];
    }

    private function getSupplierDebtAlerts(): array
    {

        $count = PurchaseOrder::query()
            ->whereColumn('amount_paid', '<', 'total_cost')
            ->count();

        if ($count === 0) {
            return [];
        }

        $todayDate = today()->toDateString();

        return [[
            'id' => 'supplier-debt-'.$todayDate,
            'type' => 'supplier_debt',
            'title' => 'ديون موردين',
            'message' => 'عندك '.$count.' طلب شراء غير مدفوع بالكامل',
            'severity' => 'info',
            'url' => '/purchases',
        ]];
    }
}
