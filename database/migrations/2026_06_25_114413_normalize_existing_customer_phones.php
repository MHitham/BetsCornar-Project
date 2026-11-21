<?php

use App\Helpers\PhoneHelper;
use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
// تحقّق من وجود عمود الحذف المنطقي وقت تشغيل الترحيل التاريخي.
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize all existing customer phone numbers to the standard format (201XXXXXXXXX).
     * This fixes the mismatch where some phones were stored as 01XXXXXXXXX or 1XXXXXXXXX
     * while lookups normalize to 201XXXXXXXXX format.
     */
    public function up(): void
    {
        Customer::query()
            // في التثبيت الجديد لم يُضف عمود الحذف المنطقي في هذه المرحلة بعد.
            ->when(! Schema::hasColumn('customers', 'deleted_at'), fn ($query) => $query->withTrashed())
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->chunkById(500, function ($customers) {
                foreach ($customers as $customer) {
                    $normalized = PhoneHelper::normalize($customer->phone);
                    if ($normalized !== $customer->phone) {
                        $customer->updateQuietly(['phone' => $normalized]);
                    }
                }
            });
    }

    /**
     * No rollback — we don't know the original formats.
     */
    public function down(): void
    {
        // Cannot reverse phone normalization
    }
};
