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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

if (! function_exists('dashboardKey')) {

    function dashboardKey(string $key): string
    {

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

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', function () {

        if (auth()->user()->hasRole('employee')) {
            return redirect()->route('invoices.index');
        }

        $now = \Carbon\Carbon::now();
        $businessDayStart = $now->copy()->startOfDay()->addHours(2);

        if ($now->lt($businessDayStart)) {

            $periodStart = $businessDayStart->copy()->subDay();
            $periodEnd = $businessDayStart->copy()->subSecond();
        } else {

            $periodStart = $businessDayStart;
            $periodEnd = $businessDayStart->copy()->addDay()->subSecond();
        }

        $todaySummary = \App\Models\Invoice::confirmed()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->selectRaw('COUNT(*) as today_visits, COALESCE(SUM(total), 0) as today_revenue')
            ->first();
        $todayVisits = (int) ($todaySummary->today_visits ?? 0);
        $todayRevenue = (float) ($todaySummary->today_revenue ?? 0);

        $totalProducts = Cache::remember('dashboard.total_products', now()->addDay(), fn () => \App\Models\Product::query()->where('type', 'product')->active()->count('*'));

        $totalVaccinations = Cache::remember(
            'dashboard.total_available_vaccinations_count',
            now()->addHour(),
            fn (): int => \App\Models\Product::query()
                ->where('type', 'vaccination')
                ->active()
                ->whereHas('vaccineBatches', fn ($q) => $q->usable())
                ->count()
        );

        $upcomingVaccinations = Cache::remember(dashboardKey('upcoming_vaccinations'), now()->endOfDay(), fn () => \App\Models\Vaccination::query()->with(['customer', 'product'])->where('is_completed', false)->whereDate('next_dose_date', '>=', today())->whereDate('next_dose_date', '<=', today()->addDays(3))->orderBy('next_dose_date')->limit(10)->get());

        $lowStockProducts = \App\Models\Product::query()
            ->active()
            ->whereIn('stock_status', ['low', 'out_of_stock'])
            ->where('track_stock', true)

            ->select(['id', 'name', 'quantity', 'stock_status', 'low_stock_threshold', 'type'])
            ->orderBy('stock_status')
            ->limit(10)
            ->get();

        $batchExpiry = Cache::remember(dashboardKey('batch_expiry'), now()->endOfDay(), fn () => \App\Models\VaccineBatch::query()->whereDate('expiry_date', '<=', today()->addDays(5))->where('quantity_remaining', '>', 0)->with('product:id,name')->select(['id', 'product_id', 'batch_code', 'expiry_date', 'quantity_remaining'])->orderBy('expiry_date')->get());

        [$expiredBatches, $expiringSoonBatches] = $batchExpiry->partition(fn ($batch) => $batch->expiry_date->lt(today()));

        return view('home', compact('todayVisits', 'todayRevenue', 'totalProducts', 'totalVaccinations', 'upcomingVaccinations', 'lowStockProducts', 'expiredBatches', 'expiringSoonBatches'));
    })->name('dashboard');

    Route::middleware('role:admin,employee')->group(function () {
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');

        Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');

        Route::get('customers/lookup-for-visit', [CustomerController::class, 'lookupForVisit'])->name('customers.lookup-for-visit');

        Route::get('customers/{customer}/animals', [\App\Http\Controllers\AnimalController::class, 'index'])->name('customers.animals.index');
        Route::post('customers/{customer}/animals', [\App\Http\Controllers\AnimalController::class, 'store'])->name('customers.animals.store');
        Route::get('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'show'])->name('animals.show');
        Route::patch('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'update'])->name('animals.update');
        Route::delete('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'destroy'])->name('animals.destroy');

        Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    });

    Route::middleware('role:admin')->group(function () {

        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');

        Route::get('/api/notifications', [App\Http\Controllers\NotificationController::class, 'index'])
            ->name('notifications.index');

        Route::get('customers/lookup-for-visit', [CustomerController::class, 'lookupForVisit'])->name('customers.lookup-for-visit');

        Route::get('customers/{customer}/animals', [\App\Http\Controllers\AnimalController::class, 'index'])->name('customers.animals.index');
        Route::post('customers/{customer}/animals', [\App\Http\Controllers\AnimalController::class, 'store'])->name('customers.animals.store');
        Route::get('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'show'])->name('animals.show');
        Route::patch('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'update'])->name('animals.update');
        Route::delete('animals/{animal}', [\App\Http\Controllers\AnimalController::class, 'destroy'])->name('animals.destroy');

        Route::get('reports/{year}/{month}', [\App\Http\Controllers\ReportController::class, 'showMonth'])->name('reports.month');

        Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');

        Route::post('customers/export-excel', [CustomerController::class, 'exportExcel'])->name('customers.export-excel');

        Route::get('customers/export-phones', [CustomerController::class, 'getPhones'])->name('customers.export-phones');

        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

        Route::post('invoices/{invoice}/returns', [\App\Http\Controllers\InvoiceReturnController::class, 'store'])->name('invoice-returns.store');

        Route::post('invoices/{invoice}/pay', [\App\Http\Controllers\InvoicePaymentController::class, 'pay'])
            ->name('invoices.pay');

        Route::post('invoices/{invoice}/payments', [\App\Http\Controllers\InvoicePaymentController::class, 'store'])
            ->name('invoice.payments.store');

        Route::get('vaccinations', [VaccinationController::class, 'index'])->name('vaccinations.index');

        Route::post('vaccinations/export-excel', [VaccinationController::class, 'exportExcel'])->name('vaccinations.export-excel');

        Route::get('vaccinations/export-phones', [VaccinationController::class, 'getPhones'])->name('vaccinations.export-phones');
        Route::post('vaccinations/{vaccination}/complete', [VaccinationController::class, 'complete'])->name('vaccinations.complete');
        Route::post('vaccinations/{vaccination}/reschedule', [VaccinationController::class, 'reschedule'])->name('vaccinations.reschedule');

        Route::resource('expenses', ExpenseController::class)->except('show');

        Route::get('reports/profitability', [ReportController::class, 'profitability'])->name('reports.profitability');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
        Route::patch('/products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle-active');
        Route::resource('products', ProductController::class)->except('show');

        Route::resource('vaccine-batches', VaccineBatchController::class)->except('show');

        Route::resource('users', UserController::class)->except(['show']);

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::resource('suppliers', SupplierController::class)
            ->except(['show']);
        Route::post('suppliers/{supplier}/toggle-active', [SupplierController::class, 'toggleActive'])
            ->name('suppliers.toggle-active');

        Route::get('settings/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('settings/backup', [BackupController::class, 'store'])->name('backup.store');

        Route::post('settings/backup/{filename}/restore', [BackupController::class, 'restoreBackup'])
            ->where('filename', '^backup_[\d]{4}-[\d]{2}-[\d]{2}_[\d]{2}-[\d]{2}\.sql$')
            ->name('backup.restore');
        Route::delete('settings/backup/{filename}', [BackupController::class, 'destroy'])
            ->where('filename', '^backup_[\d]{4}-[\d]{2}-[\d]{2}_[\d]{2}-[\d]{2}\.sql$')
            ->name('backup.destroy');

        Route::resource('purchases', PurchaseOrderController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::post('purchases/{purchase}/pay', [PurchaseOrderController::class, 'pay'])
            ->name('purchases.pay');
    });
});
