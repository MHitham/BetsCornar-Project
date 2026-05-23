<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_return_id')
                ->constrained('invoice_returns')->cascadeOnDelete();
            $table->foreignId('invoice_item_id')
                ->constrained('invoice_items')->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity_returned', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
            $table->index('invoice_return_id');
            $table->index('invoice_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_return_items');
    }
};
