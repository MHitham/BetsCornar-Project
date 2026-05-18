<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('animal_id')
                ->nullable()
                ->constrained('animals')
                ->nullOnDelete();

            $table->index('animal_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['animal_id']);
            $table->dropIndex(['animal_id']);
            $table->dropColumn('animal_id');
        });
    }
};
