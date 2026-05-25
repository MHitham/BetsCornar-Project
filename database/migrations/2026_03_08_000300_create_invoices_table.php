<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('customer_name');
            $table->enum('source', ['customer', 'quick_sale']);
            $table->decimal('total', 10, 2);
            $table->string('status')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
