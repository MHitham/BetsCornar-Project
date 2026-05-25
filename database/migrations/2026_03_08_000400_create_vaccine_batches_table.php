<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('batch_code')->nullable();
            $table->date('received_date');
            $table->date('expiry_date');
            $table->decimal('quantity_received', 10, 2);
            $table->decimal('quantity_remaining', 10, 2);
            $table->timestamps();

            $table->index(['product_id', 'expiry_date']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccine_batches');
    }
};
