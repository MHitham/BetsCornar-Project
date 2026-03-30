<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // حقل سبب الإلغاء — اختياري يملأه الموظف عند الإلغاء
            $table->string('cancellation_reason')->nullable()->after('status');

            // تاريخ ووقت الإلغاء — يُسجَّل تلقائياً عند تغيير الحالة إلى cancelled
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // حذف الحقلين عند rollback
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });
    }
};
