<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('invoice_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->date('vaccination_date');
            $table->date('next_dose_date')->nullable();
            $table->timestamps();

            $table->index('next_dose_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccinations');
    }
};
