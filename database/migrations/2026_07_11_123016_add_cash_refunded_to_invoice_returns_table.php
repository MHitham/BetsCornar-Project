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
        Schema::table('invoice_returns', function (Blueprint $table) {
            $table->decimal('cash_refunded', 10, 2)->default(0)->after('total_refund');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_returns', function (Blueprint $table) {
            $table->dropColumn('cash_refunded');
        });
    }
};
