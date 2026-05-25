<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_licenses')) {
            Schema::create('app_licenses', function (Blueprint $table) {
                $table->id();

                $table->string('machine_id');

                $table->text('install_date');

                $table->text('last_run_date');

                $table->text('license_key')->nullable();

                $table->boolean('is_activated')->default(false);

                $table->string('checksum');

                $table->timestamps();

                $table->index('machine_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_licenses');
    }
};
