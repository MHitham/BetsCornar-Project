<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // احتفظ بالعميل والفواتير ماليًا، مع إتاحة إخفائها في الشاشات المحددة.
    public function up(): void
    {
        // اسمح بإكمال الترحيل إذا توقّف MySQL بعد إضافة أحد الحقول.
        if (! Schema::hasColumn('customers', 'deleted_at')) {
            Schema::table('customers', fn (Blueprint $table) => $table->softDeletes());
        }

        // أضف علامة الإخفاء ثم فهرسها حتى يمكن استئناف كل خطوة مستقلة.
        if (! Schema::hasColumn('invoices', 'is_hidden')) {
            Schema::table('invoices', fn (Blueprint $table) => $table->boolean('is_hidden')->default(false));
        }
        if (! Schema::hasIndex('invoices', 'invoices_is_hidden_index')) {
            Schema::table('invoices', fn (Blueprint $table) => $table->index('is_hidden'));
        }
    }

    // أزل حقول الحذف المنطقي والإخفاء عند التراجع عن الترحيل.
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['is_hidden']);
            $table->dropColumn('is_hidden');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
