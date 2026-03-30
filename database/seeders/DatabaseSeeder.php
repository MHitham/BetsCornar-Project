<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\CustomerVisitService;
use App\Services\InvoiceService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // تم الإضافة: تهيئة أدوار النظام (admin/employee) قبل باقي البيانات
        $this->call(RolesSeeder::class);

        DB::transaction(function () {
            // 1. Consultation Product (كشف)
            Product::updateOrCreate(
                ['name' => 'كشف', 'type' => 'service'],
                [
                    'price' => 150.00,
                    'quantity' => 0,
                    'track_stock' => false,
                    'stock_status' => 'available',
                    'low_stock_threshold' => 0,
                    'is_active' => true,
                ]
            );

            // 2. Example products
            Product::updateOrCreate(
                ['name' => 'طوق براغيث', 'type' => 'product'],
                [
                    'price' => 75.00,
                    'quantity' => 20,
                    'track_stock' => true,
                    'stock_status' => 'available',
                    'low_stock_threshold' => 5,
                    'is_active' => true,
                ]
            );

            Product::updateOrCreate(
                ['name' => 'قص أظافر', 'type' => 'service'],
                [
                    'price' => 50.00,
                    'quantity' => 0,
                    'track_stock' => false,
                    'stock_status' => 'available',
                    'low_stock_threshold' => 0,
                    'is_active' => true,
                ]
            );

            // 3. Example vaccines & batches
            $rabiesVaccine = Product::updateOrCreate(
                ['name' => 'تطعيم السعار', 'type' => 'vaccination'],
                [
                    'price' => 200.00,
                    'quantity' => 0,
                    'track_stock' => true,
                    'stock_status' => 'out_of_stock',
                    'low_stock_threshold' => 5,
                    'is_active' => true,
                ]
            );

            $quadVaccine = Product::updateOrCreate(
                ['name' => 'التطعيم الرباعي', 'type' => 'vaccination'],
                [
                    'price' => 350.00,
                    'quantity' => 0,
                    'track_stock' => true,
                    'stock_status' => 'out_of_stock',
                    'low_stock_threshold' => 5,
                    'is_active' => true,
                ]
            );

            // ─── جلب منتج طوق براغيث لاستخدامه في Visit 5 ─────────────────────
            // يُستخدم كمنتج إضافي في الزيارة الخامسة (maximum complexity test)
            $fleaCollar = Product::where('name', 'طوق براغيث')->first();

            $stockService = app(StockService::class);

            // ─── إنشاء وحدات التطعيم (Vaccine Batches) ─────────────────────
            // وحدة تطعيم السعار: 10 وحدات، صلاحيتها 6 أشهر
            $stockService->createVaccineBatch([
                'product_id' => $rabiesVaccine->id,
                'batch_code' => 'RAB-2023-01',
                'received_date' => now()->subDays(10)->format('Y-m-d'),
                'expiry_date' => now()->addMonths(6)->format('Y-m-d'),
                'quantity_received' => 10,
                'quantity_remaining' => 10,
            ]);

            // ─── وحدة التطعيم الرباعي #1: 15 وحدة، صلاحيتها 12 شهر ──────────
            $stockService->createVaccineBatch([
                'product_id' => $quadVaccine->id,
                'batch_code' => 'QUAD-2023-01',
                'received_date' => now()->subDays(5)->format('Y-m-d'),
                'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
                'quantity_received' => 15,
                'quantity_remaining' => 15,
            ]);

            // ─── وحدة التطعيم الرباعي منتهية الصلاحية: 5 وحدات (اختبار FEFO) ──
            $stockService->createVaccineBatch([
                'product_id' => $quadVaccine->id,
                'batch_code' => 'QUAD-EXPIRED',
                'received_date' => now()->subMonths(14)->format('Y-m-d'),
                'expiry_date' => now()->subDays(5)->format('Y-m-d'),
                'quantity_received' => 5,
                'quantity_remaining' => 5,
            ]);

            // 4. Example customers & visits
            $visitService = app(CustomerVisitService::class);

            // ─── Visit 1: كلب، تطعيم سعار واحد ────────────────────────────
            // استخدام الصيغة الجديدة: vaccinations array بدلاً من has_vaccination
            // يتم خصم وحدة واحدة من تطعيم السعار
            $visitService->saveVisit([
                'name' => 'أحمد محمد',
                'phone' => '01012345678',
                'animal_type' => 'كلب',
                'consultation_price' => 150,
                // ─── صيغة التطعيمات الجديدة: array من التطعيمات ──────────────
                'vaccinations' => [
                    [
                        'vaccine_product_id' => $rabiesVaccine->id,
                        'vaccine_quantity' => 1,
                        'vaccine_unit_price' => 200,
                        'vaccination_date' => now()->format('Y-m-d'),
                        'next_dose_date' => now()->addYear()->format('Y-m-d'),
                    ],
                ],
                'additional_items' => [],
            ]);

            // ─── Visit 2: قط، بدون تطعيم ─────────────────────────────────
            // كشف فقط (consultation_price = 150)، بدون تطعيمات
            // vaccinations array فارغ = بدون تطعيمات
            $visitService->saveVisit([
                'name' => 'سارة عبده',
                'phone' => '01123456789',
                'animal_type' => 'قط',
                'address' => 'القاهرة',
                'consultation_price' => 150,
                // ─── لا توجد تطعيمات في هذه الزيارة ──────────────────────
                'vaccinations' => [],
                'additional_items' => [],
            ]);

            // ─── Visit 3: نفس القط، تطعيم رباعي (اختبار phone normalization) ──
            // نفس العميل "سارة عبده" بصيغة هاتف مختلفة: +20 11 2345 6789
            // يجب أن يُطابق الرقم السابق (01123456789) بعد تطبيع (normalization)
            // في هذه الزيارة: consultation_price = 0 (كشف بلا سعر)
            $visitService->saveVisit([
                'name' => 'سارة عبده',
                'phone' => '+20 11 2345 6789',
                'animal_type' => 'قط',
                'consultation_price' => 0,
                // ─── تطعيم رباعي في هذه الزيارة ────────────────────────────
                'vaccinations' => [
                    [
                        'vaccine_product_id' => $quadVaccine->id,
                        'vaccine_quantity' => 1,
                        'vaccine_unit_price' => 350,
                        'vaccination_date' => now()->format('Y-m-d'),
                        'next_dose_date' => now()->addDays(21)->format('Y-m-d'),
                    ],
                ],
                'additional_items' => [],
            ]);

            // ─── Visit 4: كلب، تطعيمين في زيارة واحدة (اختبار multiple vaccinations) ─
            // عميل جديد: محمود علي
            // في نفس الزيارة: تطعيمين (السعار + الرباعي)
            // اختبار الميزة الجديدة: دعم تطعيمات متعددة في نفس الزيارة
            $visitService->saveVisit([
                'name' => 'محمود علي',
                'phone' => '01234567890',
                'animal_type' => 'كلب',
                'consultation_price' => 150,
                // ─── صيغة التطعيمات المتعددة: array مع بناصرين ──────────────
                'vaccinations' => [
                    // ─── التطعيم الأول: السعار ───────────────────────────
                    [
                        'vaccine_product_id' => $rabiesVaccine->id,
                        'vaccine_quantity' => 1,
                        'vaccine_unit_price' => 200,
                        'vaccination_date' => now()->format('Y-m-d'),
                        'next_dose_date' => now()->addDays(8)->format('Y-m-d'),
                    ],
                    // ─── التطعيم الثاني: الرباعي ──────────────────────────
                    [
                        'vaccine_product_id' => $quadVaccine->id,
                        'vaccine_quantity' => 1,
                        'vaccine_unit_price' => 350,
                        'vaccination_date' => now()->format('Y-m-d'),
                        'next_dose_date' => now()->addDays(30)->format('Y-m-d'),
                    ],
                ],
                'additional_items' => [],
            ]);

            // ─── Visit 5: أرنب، ٣ تطعيمات + منتج إضافي (اختبار maximum complexity) ─
            // عميل جديد: فاطمة حسن
            // اختبار الحد الأقصى: 3 تطعيمات + منتج إضافي (طوق براغيث)
            // تواريخ متنوعة: (past, present, future) للاختبار الشامل
            $visitService->saveVisit([
                'name' => 'فاطمة حسن',
                'phone' => '01098765432',
                'animal_type' => 'أرنب',
                'consultation_price' => 150,
                // ─── صيغة التطعيمات: 3 تطعيمات مع تواريخ متنوعة ──────────────
                'vaccinations' => [
                    // ─── التطعيم الأول: السعار، جرعة قادمة قريبة (future) ──
                    [
                        'vaccine_product_id' => $rabiesVaccine->id,
                        'vaccine_quantity' => 1,
                        'vaccine_unit_price' => 200,
                        'vaccination_date' => now()->format('Y-m-d'),
                        'next_dose_date' => now()->addDays(3)->format('Y-m-d'),
                    ],
                    // ─── التطعيم الثاني: الرباعي، جرعة قادمة ماضية (past) ──
                    [
                        'vaccine_product_id' => $quadVaccine->id,
                        'vaccine_quantity' => 1,
                        'vaccine_unit_price' => 350,
                        'vaccination_date' => now()->format('Y-m-d'),
                        'next_dose_date' => now()->subDays(2)->format('Y-m-d'),
                    ],
                    // ─── التطعيم الثالث: السعار (تكراري)، جرعة قادمة بعيدة ──
                    [
                        'vaccine_product_id' => $rabiesVaccine->id,
                        'vaccine_quantity' => 1,
                        'vaccine_unit_price' => 200,
                        'vaccination_date' => now()->subDays(30)->format('Y-m-d'),
                        'next_dose_date' => now()->addDays(60)->format('Y-m-d'),
                    ],
                ],
                // ─── منتج إضافي: طوق براغيث (اختبار منتجات إضافية) ────────
                'additional_items' => [
                    [
                        'product_id' => $fleaCollar->id,
                        'quantity' => 1,
                        'unit_price' => 75,
                    ],
                ],
            ]);

            // ─── اختبار إلغاء فاتورة مع إرجاع الستوك ───────────────────────
            // البحث عن أول فاتورة للعميل "أحمد محمد" (من Visit 1)
            // يتم إلغاء الفاتورة مما يؤدي إلى:
            //   1. تحديث status إلى 'cancelled'
            //   2. إرجاع الستوك (restore vaccine stock via FEFO)
            $invoiceToCancel = \App\Models\Invoice::where('customer_name', 'أحمد محمد')->first();
            if ($invoiceToCancel) {
                // ─── استخدام InvoiceService لإلغاء الفاتورة ────────────────
                // المعاملات:
                //   1. $invoiceToCancel: كائن الفاتورة المراد إلغاؤها
                //   2. السبب: "بيانات تجريبية - اختبار الإلغاء"
                $invoiceService = app(InvoiceService::class);
                $invoiceService->cancelInvoice($invoiceToCancel, 'بيانات تجريبية - اختبار الإلغاء');
            }

        });
    }
}
