<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_item_vaccine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('vaccine_batch_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_item_vaccine_batches');
    }
};
