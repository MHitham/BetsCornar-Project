<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->boolean('is_from_clinic_cash')
                ->default(false)
                ->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropColumn('is_from_clinic_cash');
        });
    }
};
