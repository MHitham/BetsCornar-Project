<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['product', 'service', 'vaccination']);
            $table->decimal('price', 10, 2);
            $table->decimal('quantity', 10, 2)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->enum('stock_status', ['available', 'low', 'out_of_stock'])->default('available');
            $table->decimal('low_stock_threshold', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
