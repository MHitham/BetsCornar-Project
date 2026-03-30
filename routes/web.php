<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VaccinationController;
use App\Http\Controllers\VaccineBatchController;
use Illuminate\Support\Facades\Route;

// تم الإضافة: مسارات تسجيل الدخول للزوار فقط (المسجّل يتم تحويله للوحة التحكم)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// تم الإضافة: حماية جميع مسارات النظام بـ auth middleware + مسار تسجيل الخروج
Route::middleware('auth')->group(function () {
    // تم الإضافة: تسجيل الخروج متاح لأي مستخدم مسجّل
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // تم التعديل: Dashboard تحت auth فقط مؤقتًا (بدون role)
    Route::get('/', function () {
        // ── Business day starts at 02:00 AM, not midnight ──────────────────
        // If current time is before 02:00 AM, we're still in "yesterday's" shift.
        $now = \Carbon\Carbon::now();
        $businessDayStart = $now->copy()->startOfDay()->addHours(2); // today 02:00 AM

        if ($now->lt($businessDayStart)) {
            // Before 2 AM → belong to previous business day
            $periodStart = $businessDayStart->copy()->subDay(); // yesterday 02:00 AM
            $periodEnd = $businessDayStart->copy()->subSecond(); // today 01:59:59 AM
        } else {
            // After 2 AM → current business day
            $periodStart = $businessDayStart; // today 02:00 AM
            $periodEnd = $businessDayStart->copy()->addDay()->subSecond(); // tomorrow 01:59:59 AM
        }

        // عدد زيارات اليوم — الفواتير المؤكدة فقط بدون الملغية
        $todayVisits = \App\Models\Invoice::confirmed()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->count('*');

        // إيرادات اليوم — مجموع الفواتير المؤكدة فقط بدون الملغية
        $todayRevenue = \App\Models\Invoice::confirmed()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->sum('total');

        $totalProducts = \App\Models\Product::query()->active()->count('*');
        $totalVaccinations = \App\Models\Vaccination::count('*');

        $upcomingVaccinations = \App\Models\Vaccination::query()
            ->with(['customer', 'product'])
            ->where('is_completed', false)
            ->whereDate('next_dose_date', '>=', today())
            ->whereDate('next_dose_date', '<=', today()->addDays(3))
            ->orderBy('next_dose_date')
            ->limit(10)
            ->get();

        $lowStockProducts = \App\Models\Product::query()
            ->active()
            ->whereIn('stock_status', ['low', 'out_of_stock'])
            ->where('track_stock', true)
            ->orderBy('stock_status')
            ->limit(10)
            ->get();

        $expiredBatches = \App\Models\VaccineBatch::query()->with('product')->whereDate('expiry_date', '<', today())->where('quantity_remaining', '>', 0)->orderBy('expiry_date')->limit(10)->get();

        $expiringSoonBatches = \App\Models\VaccineBatch::query()
            ->with('product')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays(5))
            ->where('quantity_remaining', '>', 0)
            ->orderBy('expiry_date')
            ->limit(10)
            ->get();

        return view('home', compact('todayVisits', 'todayRevenue', 'totalProducts', 'totalVaccinations', 'upcomingVaccinations', 'lowStockProducts', 'expiredBatches', 'expiringSoonBatches'));
    })->name('dashboard');

    // تم الإضافة: مسارات مشتركة (admin + employee) — زيارة العميل فقط
    Route::middleware('role:admin,employee')->group(function () {
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    });

    // تم الإضافة: مسارات الأدمن فقط
    Route::middleware('role:admin')->group(function () {
        // Customers (index فقط للأدمن)
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');

        // Invoices (مؤقتًا للأدمن فقط حتى FEATURE 4)
        Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

        // Vaccinations module
        Route::get('vaccinations', [VaccinationController::class, 'index'])->name('vaccinations.index');
        Route::post('vaccinations/{vaccination}/complete', [VaccinationController::class, 'complete'])->name('vaccinations.complete');
        Route::post('vaccinations/{vaccination}/reschedule', [VaccinationController::class, 'reschedule'])->name('vaccinations.reschedule');

        // Products module
        Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
        Route::patch('/products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle-active');
        Route::resource('products', ProductController::class)->except('show');

        // Vaccine Batches module
        Route::resource('vaccine-batches', VaccineBatchController::class)->except('show');
    });
});
