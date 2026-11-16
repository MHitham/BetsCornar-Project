<?php

use App\Helpers\PhoneHelper;
use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;

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
