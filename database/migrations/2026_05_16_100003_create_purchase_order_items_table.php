<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->decimal('purchase_price_per_unit', 10, 2);
            $table->decimal('selling_price_per_unit', 10, 2);
            $table->date('expiry_date')->nullable();
            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('vaccine_batches')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
