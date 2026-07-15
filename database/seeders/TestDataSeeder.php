<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Product;
use App\Services\CustomerVisitService;
use App\Services\InvoiceService;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    private int $uniqueNumber = 10000;

    private function getWord(): string
    {
        $words = ['أساسي', 'متقدم', 'ممتاز', 'عادي', 'خاص', 'إضافي', 'مستورد', 'محلي', 'جديد', 'سريع', 'شامل', 'مؤقت', 'عالي الجودة', 'طبي', 'بيطري'];

        return Arr::random($words);
    }

    private function getName(): string
    {
        $first = ['أحمد', 'محمد', 'محمود', 'علي', 'عمر', 'سارة', 'فاطمة', 'مريم', 'نورهان', 'حسين', 'حسن', 'عبدالله', 'يوسف', 'إسلام', 'ياسين', 'هبة', 'دينا', 'منى', 'خديجة', 'عائشة', 'يحيى', 'طارق', 'زياد', 'نادية', 'رانيا', 'سامي', 'كريم', 'مصطفى', 'أميرة', 'لمياء'];
        $last = ['السيد', 'إبراهيم', 'حسن', 'عبدالرحمن', 'فهمي', 'سالم', 'علي', 'سعد', 'توفيق', 'مصطفى', 'يونس', 'خالد', 'عادل', 'كمال', 'منصور', 'شوقي', 'رضا', 'جمال', 'نصر', 'زكي'];

        return Arr::random($first).' '.Arr::random($last);
    }

    private function getAddress(): string
    {
        $cities = ['القاهرة', 'الجيزة', 'الإسكندرية', 'المنصورة', 'طنطا', 'أسيوط', 'الزقازيق', 'الإسماعيلية', 'شرم الشيخ', 'الغردقة', 'بورسعيد'];

        return Arr::random($cities).'، شارع '.rand(1, 100);
    }

    private function getAnimalType(): string
    {
        $animals = ['قط', 'كلب', 'طائر', 'سلحفاة', 'أرنب', 'هامستر'];

        return Arr::random($animals);
    }

    private function getDiagnosis(): string
    {
        $diagnoses = [
            'الحيوان بصحة جيدة وتم إجراء الفحص الدوري بنجاح.',
            'يعاني من ارتفاع في درجة الحرارة (39.5) وفقدان للشهية. تم إعطاء مضاد حيوي وخافض حرارة.',
            'وجود التهاب في الأذن الوسطى مع إفرازات. تم تنظيف الأذن وصرف قطرة مضادة للالتهاب.',
            'اشتباه في حساسية طعام تسبب حكة شديدة وتساقط للشعر. تم تغيير النظام الغذائي.',
            'إصابة بجرح سطحي في القدم الأمامية. تم تعقيم الجرح ووضع ضمادة طبية.',
            'يعاني من ضعف عام ونقص في الفيتامينات. تم حقن فيتامينات متعددة.',
            'التهاب في الجهاز التنفسي العلوي، سعال وعطس متكرر. تم وصف كورس علاج.',
            'مشاكل في الهضم وإسهال خفيف. تم إعطاء أدوية مهضمة ومضاد للتشنجات.',
            'تم إزالة جير الأسنان وتلميعها. اللثة بها التهاب طفيف.',
            'حالة متابعة روتينية بعد العملية السابقة. الحيوان يتعافى بشكل طبيعي.',
        ];

        return Arr::random($diagnoses);
    }

    private function randomDateInLast3Months(): Carbon
    {
        return Carbon::now()->subDays(rand(0, 90));
    }

    public function run(): void
    {
        $visitService = app(CustomerVisitService::class);
        $stockService = app(StockService::class);
        $invoiceService = app(InvoiceService::class);
        $purchaseService = app(\App\Services\PurchaseService::class);

        DB::transaction(function () use ($visitService, $stockService, $invoiceService, $purchaseService) {

            $this->command->info('Creating 5 Users...');
            for ($i = 0; $i < 5; $i++) {
                $user = \App\Models\User::updateOrCreate(
                    ['email' => 'user'.$i.'@test.com'],
                    [
                        'name' => $this->getName(),
                        'password' => \Illuminate\Support\Facades\Hash::make('password'),
                    ]
                );
                if ($i === 0) {
                    \Illuminate\Support\Facades\Auth::login($user);
                }
            }

            $this->command->info('Creating 200 Products & Services...');
            $products = [];

            for ($i = 0; $i < 200; $i++) {
                $type = Arr::random(['product', 'service']);
                $trackStock = $type === 'product';
                $name = ($type === 'service' ? 'خدمة ' : 'منتج ').$this->getWord().' '.rand(1, 999999);
                $isOutOfStock = $trackStock && rand(1, 100) <= 20;

                $products[] = Product::updateOrCreate(
                    ['name' => $name],
                    [
                        'type' => $type,
                        'price' => rand(50, 800),
                        'quantity' => $trackStock ? ($isOutOfStock ? 0 : rand(100, 1000)) : 0,
                        'track_stock' => $trackStock,
                        'stock_status' => $trackStock ? ($isOutOfStock ? 'out_of_stock' : 'available') : 'available',
                        'low_stock_threshold' => $trackStock ? 10 : 0,
                        'is_active' => true,
                    ]
                );
            }

            $this->command->info('Creating 80 Vaccines with Batches...');
            $vaccines = [];

            for ($i = 0; $i < 80; $i++) {
                $vaccine = Product::updateOrCreate(
                    ['name' => 'تطعيم '.$this->getWord().' '.rand(1, 999999)],
                    [
                        'type' => 'vaccination',
                        'price' => rand(150, 1000),
                        'quantity' => 0,
                        'track_stock' => true,
                        'stock_status' => 'available',
                        'low_stock_threshold' => 5,
                        'is_active' => true,
                    ]
                );
                $vaccines[] = $vaccine;

                $numBatches = rand(2, 5);
                for ($b = 0; $b < $numBatches; $b++) {
                    $this->uniqueNumber++;
                    $isExpired = rand(1, 100) <= 15;
                    $qtyReceived = rand(100, 500);
                    $qtyRemaining = rand(50, $qtyReceived);

                    $expiryDate = $isExpired
                        ? now()->subDays(rand(10, 60))->format('Y-m-d')
                        : now()->addDays(rand(60, 365))->format('Y-m-d');

                    $stockService->createVaccineBatch([
                        'product_id' => $vaccine->id,
                        'batch_code' => 'BAT-'.$this->uniqueNumber,
                        'received_date' => now()->subDays(rand(10, 90))->format('Y-m-d'),
                        'expiry_date' => $expiryDate,
                        'quantity_received' => $qtyReceived,
                        'quantity_remaining' => $qtyRemaining,
                    ]);
                }
            }

            $vaccines = Product::where('name', 'LIKE', 'تطعيم %')->whereIn('id', collect($vaccines)->pluck('id'))->get()->all();

            $this->command->info('Generating 2000 Customers...');
            $customerPool = [];

            for ($i = 0; $i < 2000; $i++) {
                $prefixes = ['010', '011', '012', '015'];
                $rawPhone = Arr::random($prefixes).str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT);
                $formats = [
                    $rawPhone,
                    '+20'.substr($rawPhone, 1),
                    substr($rawPhone, 0, 3).' '.substr($rawPhone, 3, 4).' '.substr($rawPhone, 7),
                ];
                $animalType = $this->getAnimalType();

                $customerPool[] = [
                    'name' => $this->getName(),
                    'phone_variations' => $formats,
                    'address' => $this->getAddress(),
                    'animal_type' => $animalType,
                    'new_animal' => [
                        'name' => Arr::random(['بندق', 'سيمبا', 'بيلا', 'لوسي', 'ماكس', 'لونا', 'كوكي', 'مشمش', 'ليو', 'تومي', 'ميلو', 'روكي', 'زوي', 'كيتي']),
                        'species' => $animalType,
                        'breed' => Arr::random(['شيرازي', 'بلدي', 'سيامي', 'جولدن', 'بيتبول', 'هاسكي', 'مختلط', 'فرعوني', 'روت وايلر']),
                        'age' => rand(1, 15).' أشهر',
                        'gender' => Arr::random(['ذكر', 'أنثى']),
                        'weight' => rand(1, 40) + (rand(0, 9) / 10),
                        'color' => Arr::random(['أبيض', 'أسود', 'بني', 'مشمشي', 'رمادي', 'أشقر', 'مختلط']),
                    ],
                    'real_animal_id' => null,
                ];
            }

            $this->command->info('Creating 4000 Visits spread over last 3 months...');

            $availableProducts = array_filter($products, fn ($p) => ! $p->track_stock || $p->quantity > 0);
            $availableVaccines = array_filter($vaccines, fn ($v) => $v->quantity > 0);

            $newInvoices = [];

            for ($i = 0; $i < 4000; $i++) {
                $poolIndex = array_rand($customerPool);
                $customerData = &$customerPool[$poolIndex];
                $phoneToUse = Arr::random($customerData['phone_variations']);

                $visitDate = $this->randomDateInLast3Months();

                $visitData = [
                    'name' => $customerData['name'],
                    'phone' => $phoneToUse,
                    'animal_type' => $customerData['animal_type'],
                    'address' => $customerData['address'],
                    'diagnosis' => rand(1, 100) <= 70 ? $this->getDiagnosis() : null,
                    'consultation_price' => rand(1, 100) <= 80 ? rand(100, 300) : 0,
                    'vaccinations' => [],
                    'additional_items' => [],
                ];

                if ($customerData['real_animal_id'] === null) {
                    $visitData['animal_id'] = 'new_animal';
                    $visitData['new_animal'] = $customerData['new_animal'];
                } else {
                    $visitData['animal_id'] = $customerData['real_animal_id'];
                }

                $numVaccinations = rand(0, 2);
                $usedVaccineIds = [];
                for ($v = 0; $v < $numVaccinations; $v++) {
                    if (empty($availableVaccines)) {
                        break;
                    }
                    $vaccine = Arr::random($availableVaccines);
                    if (in_array($vaccine->id, $usedVaccineIds)) {
                        continue;
                    }
                    $usedVaccineIds[] = $vaccine->id;

                    $dateType = Arr::random(['past', 'near_future', 'far_future', 'none']);
                    $nextDoseDate = match ($dateType) {
                        'past' => Carbon::now()->subDays(rand(1, 30))->format('Y-m-d'),
                        'near_future' => Carbon::now()->addDays(rand(1, 5))->format('Y-m-d'),
                        'far_future' => Carbon::now()->addDays(rand(20, 180))->format('Y-m-d'),
                        'none' => null,
                    };

                    $visitData['vaccinations'][] = [
                        'vaccine_product_id' => $vaccine->id,
                        'vaccine_quantity' => 1,
                        'vaccine_unit_price' => $vaccine->price,
                        'vaccination_date' => $visitDate->format('Y-m-d'),
                        'next_dose_date' => $nextDoseDate,
                    ];
                }

                $numItems = rand(0, 3);
                $usedItemIds = [];
                for ($j = 0; $j < $numItems; $j++) {
                    if (empty($availableProducts)) {
                        break;
                    }
                    $item = Arr::random($availableProducts);
                    if (in_array($item->id, $usedItemIds)) {
                        continue;
                    }
                    $usedItemIds[] = $item->id;

                    $visitData['additional_items'][] = [
                        'product_id' => $item->id,
                        'quantity' => rand(1, 2),
                        'unit_price' => $item->price,
                    ];
                }

                try {
                    $invoice = $visitService->saveVisit($visitData);

                    $invoice->created_at = $visitDate;
                    $invoice->saveQuietly();

                    \App\Models\Vaccination::where('invoice_id', $invoice->id)
                        ->update(['created_at' => $visitDate]);

                    $newInvoices[] = $invoice;

                    if ($customerData['real_animal_id'] === null && $invoice->animal_id) {
                        $customerData['real_animal_id'] = $invoice->animal_id;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $this->command->info('Cancelling 150 random invoices...');
            $reasons = ['طلب العميل الإلغاء', 'خطأ في إدخال البيانات', 'تم تسجيل الزيارة بالخطأ', 'إرجاع العلاج'];

            collect($newInvoices)
                ->filter(fn ($inv) => $inv->status === 'confirmed')
                ->random(min(150, count($newInvoices)))
                ->each(function ($invoice) use ($invoiceService, $reasons) {
                    try {
                        $invoiceService->cancelInvoice($invoice, Arr::random($reasons));
                    } catch (\Exception $e) {

                    }
                });

            $this->command->info('Creating 600 Expenses over last 3 months...');
            $expenseTitles = [
                'إيجار العيادة', 'فاتورة كهرباء', 'فاتورة مياه', 'مستلزمات نظافة',
                'صيانة أجهزة', 'رواتب عاملين', 'تسويق وإعلانات', 'أدوات مكتبية',
                'ضيافة للعملاء', 'مستلزمات طبية', 'شراء قطن وشاش', 'صيانة تكييف',
                'فاتورة إنترنت', 'بنزين سيارة العيادة', 'شراء أكل للحيوانات',
            ];

            for ($i = 0; $i < 600; $i++) {
                Expense::create([
                    'title' => Arr::random($expenseTitles),
                    'amount' => rand(50, 5000),
                    'expense_date' => $this->randomDateInLast3Months()->format('Y-m-d'),
                    'notes' => rand(1, 10) > 7 ? 'مصروف تم إضافته للفترة الحالية.' : null,
                    'created_by' => 1,
                ]);
            }

            $this->command->info('Creating 20 Suppliers...');
            $suppliers = [];
            for ($i = 0; $i < 20; $i++) {
                $suppliers[] = \App\Models\Supplier::create([
                    'name' => 'مورد ' . $this->getWord() . ' ' . rand(1, 999),
                    'phone' => '010' . rand(10000000, 99999999),
                    'address' => $this->getAddress(),
                    'notes' => 'مورد بيانات تجريبية',
                    'is_active' => true,
                ]);
            }

            $this->command->info('Creating 100 Purchase Orders...');
            $allProducts = array_filter($products, fn ($p) => $p->track_stock);
            $allVaccines = $vaccines;
            for ($i = 0; $i < 100; $i++) {
                if (empty($allProducts) && empty($allVaccines)) break;

                $supplier = \Illuminate\Support\Arr::random($suppliers);
                $isPaid = rand(1, 100) <= 70;
                $poItems = [];
                $numItems = rand(1, 5);

                for($j = 0; $j < $numItems; $j++) {
                    $isVaccine = rand(0, 1) && !empty($allVaccines);
                    if ($isVaccine) {
                        $product = \Illuminate\Support\Arr::random($allVaccines);
                        $poItems[] = [
                            'product_id' => $product->id,
                            'quantity' => rand(10, 100),
                            'purchase_price_per_unit' => $product->price * 0.7,
                            'selling_price_per_unit' => $product->price,
                            'expiry_date' => now()->addDays(rand(30, 365))->format('Y-m-d'),
                        ];
                    } else {
                        if(empty($allProducts)) continue;
                        $product = \Illuminate\Support\Arr::random($allProducts);
                        $poItems[] = [
                            'product_id' => $product->id,
                            'quantity' => rand(10, 100),
                            'purchase_price_per_unit' => $product->price * 0.7,
                            'selling_price_per_unit' => $product->price,
                        ];
                    }
                }

                if (empty($poItems)) continue;

                $totalCost = collect($poItems)->sum(fn($it) => $it['quantity'] * $it['purchase_price_per_unit']);
                $amountPaid = $isPaid ? $totalCost : (rand(0, 1) ? $totalCost * 0.5 : 0);

                try {
                    $purchaseService->createPurchaseOrder([
                        'supplier_id' => $supplier->id,
                        'purchased_at' => $this->randomDateInLast3Months()->format('Y-m-d'),
                        'notes' => 'فاتورة مشتريات تجريبية',
                        'amount_paid' => $amountPaid,
                        'is_from_clinic_cash' => rand(0, 1) == 1,
                        'items' => $poItems,
                    ]);
                } catch (\Exception $e) {
                    continue;
                }
            }

            $this->command->info('Creating 50 Quick Sales...');
            for ($i = 0; $i < 50; $i++) {
                $numItems = rand(1, 3);
                $qsItems = [];
                for ($j = 0; $j < $numItems; $j++) {
                    if (empty($availableProducts)) break;
                    $product = \Illuminate\Support\Arr::random($availableProducts);
                    $qsItems[] = [
                        'product_id' => $product->id,
                        'quantity' => rand(1, 3),
                        'unit_price' => $product->price,
                    ];
                }
                if (empty($qsItems)) continue;

                try {
                    $invoiceService->saveQuickSale([
                        'customer_name' => rand(0, 1) ? $this->getName() : '',
                        'customer_phone' => rand(0, 1) ? '011' . rand(10000000, 99999999) : '',
                        'items' => $qsItems,
                    ]);
                } catch (\Exception $e) {}
            }

            $this->command->info('Creating 50 Invoice Returns...');
            $confirmedInvoices = collect($newInvoices)
                ->filter(fn ($inv) => $inv->status === 'confirmed')
                ->values();

            if ($confirmedInvoices->isNotEmpty()) {
                $invoicesToReturn = $confirmedInvoices->random(min(50, $confirmedInvoices->count()));
                foreach ($invoicesToReturn as $invoice) {
                    try {
                        $invoice->load('items');
                        if ($invoice->items->isEmpty()) continue;

                        $returnItems = [];
                        foreach ($invoice->items as $item) {
                            if (rand(0, 1)) {
                                $qty = rand(1, max(1, floor($item->quantity)));
                                if ($qty > $item->quantity) $qty = $item->quantity;
                                $returnItems[] = [
                                    'invoice_item_id' => $item->id,
                                    'quantity_returned' => $qty,
                                ];
                            }
                        }
                        if (!empty($returnItems)) {
                            $invoiceService->createReturn($invoice, $returnItems, 'إرجاع تجريبي للعميل');
                        }
                    } catch (\Exception $e) {}
                }
            }

            $this->command->info('✅ TestDataSeeder completed successfully!');
        });
    }
}
