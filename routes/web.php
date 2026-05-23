<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VaccinationController;
use App\Http\Controllers\VaccineBatchController;
use App\Models\User;
// تم الإضافة: استدعاء الكاش لتخزين مؤشرات لوحة التحكم مؤقتًا
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// تم الإضافة: توحيد مفاتيح كاش لوحة التحكم المرتبطة بتاريخ اليوم
if (! function_exists('dashboardKey')) {
    // تم الإضافة: إنشاء مفتاح كاش يومي ثابت حسب تاريخ بداية اليوم
    function dashboardKey(string $key): string
    {
        // تم الإضافة: ربط المفتاح بتاريخ اليوم الحالي لتفادي تضارب بيانات الأيام المختلفة
        return 'dashboard.'.$key.'_'.now()->startOfDay()->toDateString();
    }
}

Route::get('/debug-admin', function () {
    $user = User::where('email', 'admin@betscornar.com')->first();

    if (! $user) {
        return 'admin user not found';
    }

    return [
        'email' => $user->email,
        'has_password_match' => Hash::check('admin123', $user->password),
    ];
});

// تم الإضافة: مسارات تسجيل الدخول للزوار فقط (المسجل يُحوَّل إلى لوحة التحكم)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// تم الإضافة: حماية جميع مسارات النظام بـ auth middleware + مسار تسجيل الخروج
Route::middleware('auth')->group(function () {
    // تم الإضافة: تسجيل الخروج متاح لأي مستخدم مسجل
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // تم التعديل: لوحة التحكم تحت auth فقط مؤقتًا (بدون role)
    Route::get('/', function () {
        // تم الإضافة: الموظف يُحوَّل تلقائيًا إلى صفحة الفواتير بدلًا من لوحة التحكم
        if (auth()->user()->hasRole('employee')) {
            return redirect()->route('invoices.index');
        }

        // ── يوم العمل يبدأ الساعة 2:00 صباحًا وليس منتصف الليل ──
        // إذا كان الوقت قبل 2 صباحًا فما زلنا في وردية «أمس»
        $now = \Carbon\Carbon::now();
        $businessDayStart = $now->copy()->startOfDay()->addHours(2); // اليوم 02:00

        if ($now->lt($businessDayStart)) {
            // قبل 2 صباحًا ← تابع ليوم العمل السابق
            $periodStart = $businessDayStart->copy()->subDay(); // أمس 02:00
            $periodEnd = $businessDayStart->copy()->subSecond(); // اليوم 01:59:59
        } else {
            // بعد 2 صباحًا ← يوم العمل الحالي
            $periodStart = $businessDayStart; // اليوم 02:00
            $periodEnd = $businessDayStart->copy()->addDay()->subSecond(); // غدًا 01:59:59
        }

        // تم التعديل: دمج عد زيارات اليوم وإيراداته في استعلام واحد لتقليل عدد الاستعلامات
        $todaySummary = \App\Models\Invoice::confirmed()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->selectRaw('COUNT(*) as today_visits, COALESCE(SUM(total), 0) as today_revenue')
            ->first();
        $todayVisits = (int) ($todaySummary->today_visits ?? 0);
        $todayRevenue = (float) ($todaySummary->today_revenue ?? 0);

        // عدد المنتجات الفعلية فقط (بدون لقاحات وخدمات)
        $totalProducts = Cache::remember('dashboard.total_products', now()->addDay(), fn () => \App\Models\Product::query()->where('type', 'product')->active()->count('*'));
        // عدد اللقاحات المتاحة (لها باتشات صالحة)
        $totalVaccinations = Cache::remember(
            'dashboard.total_available_vaccinations_count',
            now()->addHour(),
            fn (): int => \App\Models\Product::query()
                ->where('type', 'vaccination')
                ->active()
                ->whereHas('vaccineBatches', fn ($q) => $q->usable())
                ->count()
        );

        // تم الإضافة: تخزين قائمة التطعيمات القادمة مؤقتًا حتى نهاية اليوم مع الحفاظ على نفس النتائج الحالية
        $upcomingVaccinations = Cache::remember(dashboardKey('upcoming_vaccinations'), now()->endOfDay(), fn () => \App\Models\Vaccination::query()->with(['customer', 'product'])->where('is_completed', false)->whereDate('next_dose_date', '>=', today())->whereDate('next_dose_date', '<=', today()->addDays(3))->orderBy('next_dose_date')->limit(10)->get());

        $lowStockProducts = \App\Models\Product::query()
            ->active()
            ->whereIn('stock_status', ['low', 'out_of_stock'])
            ->where('track_stock', true)
            // تم الإضافة: تحديد الأعمدة المطلوبة فقط لمنتجات التنبيه منخفضة المخزون
            ->select(['id', 'name', 'quantity', 'stock_status', 'low_stock_threshold', 'type'])
            ->orderBy('stock_status')
            ->limit(10)
            ->get();

        // تم الإضافة: دمج استعلامات صلاحية التشغيلات في استعلام واحد مع تخزينه مؤقتًا حتى نهاية اليوم
        $batchExpiry = Cache::remember(dashboardKey('batch_expiry'), now()->endOfDay(), fn () => \App\Models\VaccineBatch::query()->whereDate('expiry_date', '<=', today()->addDays(5))->where('quantity_remaining', '>', 0)->with('product:id,name')->select(['id', 'product_id', 'batch_code', 'expiry_date', 'quantity_remaining'])->orderBy('expiry_date')->get());
        // تم الإضافة: تقسيم النتائج داخل PHP بدل تنفيذ استعلامين منفصلين مع الحفاظ على نفس تعريف المنتهي
        [$expiredBatches, $expiringSoonBatches] = $batchExpiry->partition(fn ($batch) => $batch->expiry_date->lt(today()));

        return view('home', compact('todayVisits', 'todayRevenue', 'totalProducts', 'totalVaccinations', 'upcomingVaccinations', 'lowStockProducts', 'expiredBatches', 'expiringSoonBatches'));
    })->name('dashboard');

    // تم التعديل: مسارات مشتركة (admin + employee) — زيارة العميل + بحث العملاء + الفواتير
    Route::middleware('role:admin,employee')->group(function () {
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        // تم الإضافة: بحث AJAX عن العملاء (يُستخدم في البيع السريع)
        Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');

        // Dedicated lightweight AJAX lookup for visit form (exact match only)
        Route::get('customers/lookup-for-visit', [CustomerController::class, 'lookupForVisit'])->name('customers.lookup-for-visit');

        // Animals (Hybrid Route Structure for Phase 2)
        Route::get('customers/{customer}/animals', [\App\Http\Controllers\AnimalController::class, 'index'])->name('customers.animals.index');
        Route::post('customers/{customer}/animals', [\App\Http\Controllers\AnimalController::class, 'store'])->name('customers.animals.store');
        Route::get('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'show'])->name('animals.show');
        Route::patch('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'update'])->name('animals.update');
        Route::delete('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'destroy'])->name('animals.destroy');

        // تم التعديل: الفواتير متاحة للموظف والمدير (الموظف يشاهد فواتيره فقط عبر InvoiceController@index)
        Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    });

    // تم الإضافة: مسارات الأدمن فقط
    Route::middleware('role:admin')->group(function () {
        // تم الإضافة: قائمة العملاء للأدمن فقط
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');

        // بحث العميل للزيارة (AJAX) — يجب أن يكون قبل customers/{customer}
        Route::get('customers/lookup-for-visit', [CustomerController::class, 'lookupForVisit'])->name('customers.lookup-for-visit');

        // ملفات الحيوانات
        Route::get('customers/{customer}/animals', [\App\Http\Controllers\AnimalController::class, 'index'])->name('customers.animals.index');
        Route::post('customers/{customer}/animals', [\App\Http\Controllers\AnimalController::class, 'store'])->name('customers.animals.store');
        Route::get('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'show'])->name('animals.show');
        Route::patch('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'update'])->name('animals.update');
        Route::delete('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'destroy'])->name('animals.destroy');

        // تقرير شهري تفصيلي
        Route::get('reports/{year}/{month}', [\App\Http\Controllers\ReportController::class, 'showMonth'])->name('reports.month');

        // إعدادات النظام
        Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');

        // تم الإضافة: مسارات تصدير العملاء إلى Excel وجلب أرقام الهواتف للواتساب
        Route::post('customers/export-excel', [CustomerController::class, 'exportExcel'])->name('customers.export-excel');
        // تم الإضافة: جلب أرقام العملاء المحددة أو كل النتائج الحالية للنسخ إلى الحافظة
        Route::get('customers/export-phones', [CustomerController::class, 'getPhones'])->name('customers.export-phones');
        // تم الإضافة: صفحة السجل الطبي للعميل
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

        // إلغاء الفواتير — للأدمن فقط
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

        // مرتجعات الفواتير - الأدمن فقط
        Route::post('invoices/{invoice}/returns', [\App\Http\Controllers\InvoiceReturnController::class, 'store'])->name('invoice-returns.store');

        // تسجيل دفعة على فاتورة عميل
        Route::post('invoices/{invoice}/pay', [\App\Http\Controllers\InvoicePaymentController::class, 'pay'])
            ->name('invoices.pay');

        // سجل دفعات الفاتورة (Payment History)
        Route::post('invoices/{invoice}/payments', [\App\Http\Controllers\InvoicePaymentController::class, 'store'])
            ->name('invoice.payments.store');

        // وحدة التطعيمات
        Route::get('vaccinations', [VaccinationController::class, 'index'])->name('vaccinations.index');
        // تم الإضافة: مسارات تصدير التطعيمات إلى Excel وجلب أرقام الهواتف للواتساب
        Route::post('vaccinations/export-excel', [VaccinationController::class, 'exportExcel'])->name('vaccinations.export-excel');
        // تم الإضافة: جلب أرقام أصحاب التطعيمات المحددة أو كل النتائج الحالية للنسخ إلى الحافظة
        Route::get('vaccinations/export-phones', [VaccinationController::class, 'getPhones'])->name('vaccinations.export-phones');
        Route::post('vaccinations/{vaccination}/complete', [VaccinationController::class, 'complete'])->name('vaccinations.complete');
        Route::post('vaccinations/{vaccination}/reschedule', [VaccinationController::class, 'reschedule'])->name('vaccinations.reschedule');

        // تم الإضافة: وحدة المصروفات
        Route::resource('expenses', ExpenseController::class)->except('show');

        // تم الإضافة: صفحة التقارير
        // تقرير الربحية
        Route::get('reports/profitability', [ReportController::class, 'profitability'])->name('reports.profitability');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        // وحدة المنتجات
        Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
        Route::patch('/products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle-active');
        Route::resource('products', ProductController::class)->except('show');

        // وحدة تشغيلات اللقاحات
        Route::resource('vaccine-batches', VaccineBatchController::class)->except('show');

        // وحدة إدارة المستخدمين — بدون صفحة عرض منفردة
        Route::resource('users', UserController::class)->except(['show']);
        // إعدادات النظام - الأدمن فقط
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

        // موردين
        Route::resource('suppliers', SupplierController::class)
            ->except(['show']);
        Route::post('suppliers/{supplier}/toggle-active', [SupplierController::class, 'toggleActive'])
            ->name('suppliers.toggle-active');

        // نسخ احتياطية - Backup routes (admin only)
        Route::get('settings/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('settings/backup', [BackupController::class, 'store'])->name('backup.store');
        // استعادة نسخة احتياطية - يجب أن يكون قبل {filename} DELETE route
        Route::post('settings/backup/{filename}/restore', [BackupController::class, 'restoreBackup'])
            ->where('filename', '^backup_[\d]{4}-[\d]{2}-[\d]{2}_[\d]{2}-[\d]{2}\.sql$')
            ->name('backup.restore');
        Route::delete('settings/backup/{filename}', [BackupController::class, 'destroy'])
            ->where('filename', '^backup_[\d]{4}-[\d]{2}-[\d]{2}_[\d]{2}-[\d]{2}\.sql$')
            ->name('backup.destroy');

        // فواتير الشراء
        Route::resource('purchases', PurchaseOrderController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::post('purchases/{purchase}/pay', [PurchaseOrderController::class, 'pay'])
            ->name('purchases.pay');
    });
});
