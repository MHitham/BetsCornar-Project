<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'animal_name')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('animal_name')->nullable()->after('animal_id');
            });
        }

        // نسخ اسم الحيوان على الفواتير القديمة المرتبطة بحيوان.
        $names = DB::table('animals')->pluck('name', 'id');
        DB::table('invoices')->whereNotNull('animal_id')->whereNull('animal_name')
            ->orderBy('id')->chunkById(500, function ($rows) use ($names) {
                foreach ($rows as $row) {
                    if (isset($names[$row->animal_id])) {
                        DB::table('invoices')->where('id', $row->id)
                            ->update(['animal_name' => $names[$row->animal_id]]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('animal_name'));
    }
};
