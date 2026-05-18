<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ===================================================================
 * جدول التراخيص - App Licenses Table
 * ===================================================================
 *
 * يخزن بيانات ترخيص الجهاز للتحقق من الفترة التجريبية والتفعيل
 *
 * ملاحظة: الحقول install_date, last_run_date, license_key تُخزن كـ text
 * لأنها مشفرة عبر Laravel Encrypted Casting (القيم المشفرة تكون طويلة)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_licenses')) {
            Schema::create('app_licenses', function (Blueprint $table) {
                $table->id();

                // بصمة الجهاز (SHA256 hash) - لا يحتاج تشفير لأنه hash بالفعل
                $table->string('machine_id');

                // تاريخ التثبيت الأول - مشفر (text لدعم encrypted casting)
                $table->text('install_date');

                // آخر تاريخ تشغيل - مشفر (text لدعم encrypted casting)
                $table->text('last_run_date');

                // مفتاح الترخيص المُدخل - مشفر + nullable
                $table->text('license_key')->nullable();

                // حالة التفعيل
                $table->boolean('is_activated')->default(false);

                // التحقق من سلامة البيانات (SHA256 hash مع المفتاح السري)
                $table->string('checksum');

                $table->timestamps();

                // فهرس على بصمة الجهاز للبحث السريع
                $table->index('machine_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_licenses');
    }
};
