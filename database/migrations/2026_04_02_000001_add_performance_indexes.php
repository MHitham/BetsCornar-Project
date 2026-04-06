<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaccinations', function (Blueprint $table) {
            // Level 1: indexes used by vaccinations listing and upcoming-dose queries.
            $table->index('is_completed', 'vaccinations_is_completed_index');
            $table->index('vaccination_date', 'vaccinations_vaccination_date_index');
            $table->index(['is_completed', 'next_dose_date'], 'vaccinations_is_completed_next_dose_date_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Level 1: indexes used by invoices listing and invoice status scopes.
            $table->index('source', 'invoices_source_index');
            $table->index('status', 'invoices_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('vaccinations', function (Blueprint $table) {
            $table->dropIndex('vaccinations_is_completed_index');
            $table->dropIndex('vaccinations_vaccination_date_index');
            $table->dropIndex('vaccinations_is_completed_next_dose_date_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_source_index');
            $table->dropIndex('invoices_status_index');
        });
    }
};
