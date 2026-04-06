# NEW FEATURES PLAN — BetsCornar

> هذا الملف يوثّق خطة التنفيذ التفصيلية لثلاث ميزات جديدة.
> يجب قراءة `FINAL_LOCKED_IMPLEMENTATION_PLAN.md` قبل البدء.

---

## Hard Rules (Mandatory for All 3 Features)

- Laravel 12, PHP 8.3+, Bootstrap 5 RTL, Arabic UI
- Vanilla JS only — **no new JS libraries**
- Arabic comments above every change (`// تم الإضافة:`)
- Admin only (`role:admin` middleware) for all 3 features
- Always use `Invoice::scopeConfirmed()` for financial queries
- `DB::transaction()` for all write operations
- No hard deletes — use `SoftDeletes` trait on `Expense` model
- Follow existing MVC pattern: Controller → Form Request → Service → Model

---

## Feature 1: Expenses Module

### 1.1 Purpose

Track clinic operational expenses (rent, utilities, supplies, etc.).
Full CRUD with monthly filter. Admin only.

---

### 1.2 Migration Schema

**File:** `database/migrations/2026_04_04_000001_create_expenses_table.php`

```php
Schema::create('expenses', function (Blueprint $table) {
    $table->id();
    $table->string('title');                          // عنوان المصروف
    $table->decimal('amount', 10, 2);                 // المبلغ
    $table->date('expense_date');                      // تاريخ المصروف
    $table->text('notes')->nullable();                 // ملاحظات اختيارية
    $table->unsignedBigInteger('created_by');          // المستخدم الذي أضاف المصروف
    $table->foreign('created_by')
        ->references('id')->on('users')
        ->nullOnDelete();
    $table->softDeletes();                             // حذف ناعم
    $table->timestamps();

    $table->index('expense_date');                     // فلترة بالشهر
    $table->index('created_by');                       // ربط بالمستخدم
});
```

---

### 1.3 Model

**File [NEW]:** `app/Models/Expense.php`

```php
class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'amount',
        'expense_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    // تم الإضافة: علاقة المستخدم الذي أنشأ المصروف
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

**Relationships added to existing model:**

- `User` → add `hasMany(Expense::class, 'created_by')`

---

### 1.4 Form Request

**File [NEW]:** `app/Http/Requests/StoreExpenseRequest.php`

```
Rules:
  title        => required|string|max:255
  amount       => required|numeric|min:0.01
  expense_date => required|date
  notes        => nullable|string|max:1000
```

**File [NEW]:** `app/Http/Requests/UpdateExpenseRequest.php`

```
Same rules as StoreExpenseRequest
```

---

### 1.5 Controller

**File [NEW]:** `app/Http/Controllers/ExpenseController.php`

| Method | Route | HTTP | Parameters | Variables Passed to View |
|--------|-------|------|------------|--------------------------|
| `index(Request $request)` | `/expenses` | GET | `?month=2026-04` (optional Y-m filter) | `$expenses` (paginated, 25/page), `$month` (current filter or null), `$totalMonthly` (sum for filtered month) |
| `create()` | `/expenses/create` | GET | — | — (empty form) |
| `store(StoreExpenseRequest $request)` | `/expenses` | POST | form data | redirect to `expenses.index` with success |
| `edit(Expense $expense)` | `/expenses/{expense}/edit` | GET | route model binding | `$expense` |
| `update(UpdateExpenseRequest $request, Expense $expense)` | `/expenses/{expense}` | PUT | form data + route model binding | redirect to `expenses.index` with success |
| `destroy(Expense $expense)` | `/expenses/{expense}` | DELETE | route model binding | redirect to `expenses.index` with success |

**Key logic in `index()`:**

```php
// تم الإضافة: فلتر المصروفات بالشهر مع إجمالي المصروفات الشهرية
$month = $request->input('month'); // e.g. '2026-04'

$query = Expense::query()->with('creator');

if ($month) {
    [$year, $m] = explode('-', $month);
    $query->whereYear('expense_date', $year)
          ->whereMonth('expense_date', $m);
}

$totalMonthly = (clone $query)->sum('amount');

$expenses = $query->latest('expense_date')
    ->paginate(25)
    ->withQueryString();
```

**Key logic in `store()`:**

```php
DB::transaction(function () use ($validated) {
    Expense::create([
        ...$validated,
        'created_by' => auth()->id(),
    ]);
});
```

**Key logic in `destroy()` — soft delete:**

```php
DB::transaction(function () use ($expense) {
    $expense->delete(); // SoftDeletes — sets deleted_at
});
```

---

### 1.6 Routes

**File [MODIFY]:** `routes/web.php`

Add inside the `role:admin` group:

```php
// تم الإضافة: وحدة المصروفات
use App\Http\Controllers\ExpenseController;

Route::resource('expenses', ExpenseController::class)->except('show');
```

**Named routes generated:**

| Name | Method | URI |
|------|--------|-----|
| `expenses.index` | GET | `/expenses` |
| `expenses.create` | GET | `/expenses/create` |
| `expenses.store` | POST | `/expenses` |
| `expenses.edit` | GET | `/expenses/{expense}/edit` |
| `expenses.update` | PUT | `/expenses/{expense}` |
| `expenses.destroy` | DELETE | `/expenses/{expense}` |

---

### 1.7 Views

**File [NEW]:** `resources/views/expenses/index.blade.php`

- Monthly filter (input type="month" with auto-submit on change)
- Summary card showing `$totalMonthly` for current filter
- Table: العنوان | المبلغ | التاريخ | الملاحظات | بواسطة | إجراءات
- Actions column: تعديل (link to edit), حذف (form with DELETE + confirmation modal)
- Pagination at bottom
- "إضافة مصروف" button in header

**File [NEW]:** `resources/views/expenses/create.blade.php`

- Form fields: العنوان (text), المبلغ (number step="0.01"), التاريخ (date, default today), ملاحظات (textarea optional)
- Submit button: حفظ المصروف

**File [NEW]:** `resources/views/expenses/edit.blade.php`

- Same form as create, pre-filled with `$expense` data
- Submit button: تحديث المصروف

---

### 1.8 Language File

**File [NEW]:** `lang/ar/expenses.php`

```php
return [
    'title'     => 'المصروفات',
    'fields'    => [
        'title'        => 'العنوان',
        'amount'       => 'المبلغ',
        'expense_date' => 'التاريخ',
        'notes'        => 'ملاحظات',
        'created_by'   => 'بواسطة',
    ],
    'actions'   => [
        'add'    => 'إضافة مصروف',
        'edit'   => 'تعديل المصروف',
        'delete' => 'حذف المصروف',
        'search' => 'بحث',
    ],
    'messages'  => [
        'created'       => 'تم إضافة المصروف بنجاح.',
        'updated'       => 'تم تحديث المصروف بنجاح.',
        'deleted'       => 'تم حذف المصروف بنجاح.',
        'confirm_delete'=> 'هل أنت متأكد من حذف هذا المصروف؟',
    ],
    'filters'   => [
        'month_label'   => 'فلتر بالشهر',
        'total_monthly' => 'إجمالي المصروفات',
    ],
];
```

---

### 1.9 Sidebar

**File [MODIFY]:** `resources/views/components/sidebar.blade.php`

Add inside `@role('admin')` block, after the vaccinations link:

```html
<!-- تم الإضافة: رابط المصروفات في القائمة الجانبية -->
<a href="{{ route('expenses.index') }}"
   class="sidebar-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
    <i class="bi bi-wallet2"></i> المصروفات
</a>
```

---

### 1.10 Files Summary

| Action | File |
|--------|------|
| **NEW** | `database/migrations/2026_04_04_000001_create_expenses_table.php` |
| **NEW** | `app/Models/Expense.php` |
| **NEW** | `app/Http/Controllers/ExpenseController.php` |
| **NEW** | `app/Http/Requests/StoreExpenseRequest.php` |
| **NEW** | `app/Http/Requests/UpdateExpenseRequest.php` |
| **NEW** | `resources/views/expenses/index.blade.php` |
| **NEW** | `resources/views/expenses/create.blade.php` |
| **NEW** | `resources/views/expenses/edit.blade.php` |
| **NEW** | `lang/ar/expenses.php` |
| **MODIFY** | `routes/web.php` — add expense routes |
| **MODIFY** | `resources/views/components/sidebar.blade.php` — add nav link |
| **MODIFY** | `app/Models/User.php` — add `expenses()` relationship |

---

---

## Feature 2: Monthly Reports

### 2.1 Purpose

Provide monthly financial reports for the admin:
revenue, visit counts, top-selling products/services, expenses total, and net profit.
Tables and numbers only — no charts.

---

### 2.2 Controller

**File [NEW]:** `app/Http/Controllers/ReportController.php`

| Method | Route | HTTP | Parameters | Variables Passed to View |
|--------|-------|------|------------|--------------------------|
| `index(Request $request)` | `/reports` | GET | `?year=2026` (optional, defaults to current year) | `$year`, `$monthlyData` (collection of 12 months), `$topProducts`, `$yearlyTotals` |

**Key logic in `index()` — محسّن: استعلامان فقط بدلاً من 24:**

```php
public function index(Request $request)
{
    $year = (int) $request->input('year', now()->year);

    // تم الإضافة: استعلام واحد للإيرادات — مجمّع بالشهر بدلاً من 12 استعلام منفصل
    $revenueByMonth = Invoice::confirmed()
        ->whereYear('created_at', $year)
        ->selectRaw('MONTH(created_at) as month,
                     COUNT(*) as visit_count,
                     COALESCE(SUM(total), 0) as revenue')
        ->groupBy('month')
        ->get()
        ->keyBy('month');

    // تم الإضافة: استعلام واحد للمصروفات — مجمّع بالشهر بدلاً من 12 استعلام منفصل
    $expensesByMonth = Expense::query()
        ->whereYear('expense_date', $year)
        ->selectRaw('MONTH(expense_date) as month,
                     COALESCE(SUM(amount), 0) as expenses')
        ->groupBy('month')
        ->get()
        ->keyBy('month');

    // تم الإضافة: تجميع البيانات في PHP — 12 شهر
    $monthlyData = collect(range(1, 12))->map(function ($month) use ($revenueByMonth, $expensesByMonth) {
        $revenue    = (float) ($revenueByMonth[$month]->revenue ?? 0);
        $visitCount = (int) ($revenueByMonth[$month]->visit_count ?? 0);
        $expenses   = (float) ($expensesByMonth[$month]->expenses ?? 0);

        return [
            'month'       => $month,
            'month_name'  => self::arabicMonthName($month),
            'revenue'     => $revenue,
            'visit_count' => $visitCount,
            'expenses'    => $expenses,
            'net_profit'  => $revenue - $expenses,
        ];
    });

    // تم الإضافة: أكثر المنتجات/الخدمات مبيعًا خلال السنة
    $topProducts = InvoiceItem::query()
        ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
        ->join('products', 'invoice_items.product_id', '=', 'products.id')
        ->where('invoices.status', 'confirmed')
        ->whereYear('invoices.created_at', $year)
        ->select(
            'products.id',
            'products.name',
            'products.type',
            DB::raw('SUM(invoice_items.quantity) as total_quantity'),
            DB::raw('SUM(invoice_items.line_total) as total_sales')
        )
        ->groupBy('products.id', 'products.name', 'products.type')
        ->orderByDesc('total_sales')
        ->limit(15)
        ->get();

    // تم الإضافة: إجماليات السنة — محسوبة من البيانات المجمّعة في PHP
    $yearlyTotals = [
        'revenue'     => $monthlyData->sum('revenue'),
        'expenses'    => $monthlyData->sum('expenses'),
        'net_profit'  => $monthlyData->sum('net_profit'),
        'visit_count' => $monthlyData->sum('visit_count'),
    ];

    return view('reports.index', compact(
        'year', 'monthlyData', 'topProducts', 'yearlyTotals'
    ));
}

// تم الإضافة: أسماء الأشهر العربية
private static function arabicMonthName(int $month): string
{
    return [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس',
        4 => 'أبريل', 5 => 'مايو',   6 => 'يونيو',
        7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر',
        10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ][$month] ?? '';
}
```

---

### 2.3 Routes

**File [MODIFY]:** `routes/web.php`

Add inside the `role:admin` group:

```php
// تم الإضافة: صفحة التقارير الشهرية
use App\Http\Controllers\ReportController;

Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
```

---

### 2.4 View

**File [NEW]:** `resources/views/reports/index.blade.php`

**Layout:**

1. **Year selector** — `<select>` with years from 2026 to current year, auto-submit `onchange`
2. **Yearly summary cards** (4 cards in a row):
   - إجمالي الإيرادات (revenue)
   - إجمالي المصروفات (expenses)
   - صافي الربح (net_profit) — green if positive, red if negative
   - عدد الزيارات (visit_count)
3. **Monthly breakdown table**:
   | الشهر | الإيرادات | عدد الزيارات | المصروفات | صافي الربح |
   With conditional coloring on net_profit (text-success / text-danger)
   Footer row with yearly totals
4. **Top selling products/services table**:
   | # | المنتج/الخدمة | النوع | الكمية المباعة | إجمالي المبيعات |
   Type shown as badge (product/service/vaccination)

**Variables consumed from controller:**
- `$year` — int
- `$monthlyData` — Collection of 12 arrays
- `$topProducts` — Collection of product aggregates
- `$yearlyTotals` — array with revenue, expenses, net_profit, visit_count

---

### 2.5 Language File

**File [NEW]:** `lang/ar/reports.php`

```php
return [
    'title'          => 'التقارير الشهرية',
    'year_filter'    => 'السنة',
    'monthly_table'  => 'التفاصيل الشهرية',
    'top_products'   => 'أعلى المنتجات والخدمات مبيعًا',
    'fields'         => [
        'month'       => 'الشهر',
        'revenue'     => 'الإيرادات',
        'visit_count' => 'عدد الزيارات',
        'expenses'    => 'المصروفات',
        'net_profit'  => 'صافي الربح',
        'product'     => 'المنتج/الخدمة',
        'type'        => 'النوع',
        'quantity'    => 'الكمية المباعة',
        'total_sales' => 'إجمالي المبيعات',
    ],
    'summary'        => [
        'total_revenue'  => 'إجمالي الإيرادات',
        'total_expenses' => 'إجمالي المصروفات',
        'total_profit'   => 'صافي الربح',
        'total_visits'   => 'إجمالي الزيارات',
    ],
    'totals_row'     => 'الإجمالي',
];
```

---

### 2.6 Sidebar

**File [MODIFY]:** `resources/views/components/sidebar.blade.php`

Add inside `@role('admin')` block, after the expenses link:

```html
<!-- تم الإضافة: رابط التقارير في القائمة الجانبية -->
<a href="{{ route('reports.index') }}"
   class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
    <i class="bi bi-bar-chart-line-fill"></i> التقارير
</a>
```

---

### 2.7 Files Summary

| Action | File |
|--------|------|
| **NEW** | `app/Http/Controllers/ReportController.php` |
| **NEW** | `resources/views/reports/index.blade.php` |
| **NEW** | `lang/ar/reports.php` |
| **MODIFY** | `routes/web.php` — add report route |
| **MODIFY** | `resources/views/components/sidebar.blade.php` — add nav link |

> **Note:** ReportController has **no model, no migration, no form request**.
> It reads from existing `invoices`, `invoice_items`, `products`, and the new `expenses` table.
> **Feature 1 (Expenses) must be implemented before Feature 2.**

---

---

## Feature 3: Customer Medical Timeline

### 3.1 Purpose

Display the full visit history for a single customer — all invoices with their vaccinations, products, services, and totals. Ordered newest to oldest.

---

### 3.2 Controller Method

**File [MODIFY]:** `app/Http/Controllers/CustomerController.php`

Add new method:

```php
/**
 * تم الإضافة: عرض السجل الطبي الكامل للعميل (التايم لاين)
 */
public function show(Customer $customer)
{
    // تم الإضافة: تحميل كل فواتير العميل مع البنود والتطعيمات
    $customer->load([
        'invoices' => function ($query) {
            $query->latest('created_at');
        },
        'invoices.items.product',
        'invoices.vaccinations.product',
    ]);

    return view('customers.show', compact('customer'));
}
```

**Variables passed to view:**
- `$customer` — Customer model with eager-loaded relationships:
  - `$customer->name`, `$customer->phone`, `$customer->animal_type`
  - `$customer->invoices` — Collection (newest first), each with:
    - `$invoice->invoice_number`
    - `$invoice->created_at` (visit date)
    - `$invoice->total`
    - `$invoice->status`
    - `$invoice->source`
    - `$invoice->items` — Collection of InvoiceItem, each with:
      - `$item->product->name`
      - `$item->product->type`
      - `$item->quantity`
      - `$item->unit_price`
      - `$item->line_total`
    - `$invoice->vaccinations` — Collection of Vaccination, each with:
      - `$vacc->product->name` (vaccine name)
      - `$vacc->vaccination_date`
      - `$vacc->next_dose_date`
      - `$vacc->is_completed`

---

### 3.3 Routes

**File [MODIFY]:** `routes/web.php`

Add inside the `role:admin` group, after the existing customers routes:

```php
// تم الإضافة: صفحة السجل الطبي للعميل
Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
```

> **Important:** This route must be placed **after** `customers/create`, `customers/search`,
> `customers/export-excel`, and `customers/export-phones` to avoid route conflicts.

---

### 3.4 View

**File [NEW]:** `resources/views/customers/show.blade.php`

**Layout:**

1. **Customer header card:**
   - Name, phone (with WhatsApp link), animal_type, total visit count
   - Button: "زيارة جديدة" linking to `customers.create?phone=...&name=...`
   - Button: "← العودة للعملاء" linking to `customers.index`

2. **Timeline section** — loop through `$customer->invoices`:

   For **each invoice** render a timeline card:

   ```
   ┌─ [date badge: Y-m-d H:i] ── [invoice number link] ── [status badge] ──┐
   │                                                                         │
   │  📋 بنود الفاتورة:                                                      │
   │  ┌──────────────────────────────────────────────────────────────────┐   │
   │  │ المنتج/الخدمة │ النوع │ الكمية │ سعر الوحدة │ الإجمالي          │   │
   │  │ كشف           │ خدمة  │ 1     │ 50.00      │ 50.00             │   │
   │  │ لقاح السعار   │ تطعيم │ 1     │ 120.00     │ 120.00            │   │
   │  └──────────────────────────────────────────────────────────────────┘   │
   │                                                                         │
   │  💉 التطعيمات في هذه الزيارة: (only if vaccinations exist)             │
   │  ┌──────────────────────────────────────────────────────────────────┐   │
   │  │ التطعيم    │ تاريخ التطعيم │ الموعد القادم │ الحالة              │   │
   │  │ لقاح السعار│ 2026-03-15   │ 2026-06-15   │ 🟢 قادم            │   │
   │  └──────────────────────────────────────────────────────────────────┘   │
   │                                                                         │
   │  💰 الإجمالي: 170.00 ج.م                                               │
   └─────────────────────────────────────────────────────────────────────────┘
   ```

   - Cancelled invoices shown with muted styling and "ملغية" badge (matching existing pattern)
   - Invoice number is a link to `invoices.show`
   - Vaccination status color-coded using same system as vaccinations.index:
     - Red (bg-danger): overdue (`next_dose_date < today`, not completed)
     - Orange (bg-warning): upcoming soon (within 7 days)
     - Green (bg-success): on track
     - Gray (bg-secondary): completed

3. **Empty state** if no invoices:
   - "لا توجد زيارات سابقة لهذا العميل"

---

### 3.5 Linking from Customers Index

**File [MODIFY]:** `resources/views/customers/index.blade.php`

Add a "السجل الطبي" button in the actions column, next to the existing "زيارة جديدة" button:

```html
<!-- تم الإضافة: زر السجل الطبي للعميل -->
<a href="{{ route('customers.show', $customer) }}"
   class="btn btn-sm btn-outline-info">
    <i class="bi bi-clock-history me-1"></i>السجل الطبي
</a>
```

---

### 3.6 Language File

**File [MODIFY]:** `lang/ar/customers.php`

Add these entries:

```php
// تم الإضافة: نصوص صفحة السجل الطبي
'timeline' => [
    'title'            => 'السجل الطبي',
    'visit_date'       => 'تاريخ الزيارة',
    'invoice_number'   => 'رقم الفاتورة',
    'invoice_items'    => 'بنود الفاتورة',
    'vaccinations'     => 'التطعيمات في هذه الزيارة',
    'product_name'     => 'المنتج/الخدمة',
    'type'             => 'النوع',
    'quantity'         => 'الكمية',
    'unit_price'       => 'سعر الوحدة',
    'line_total'       => 'الإجمالي',
    'vaccine_name'     => 'التطعيم',
    'vaccination_date' => 'تاريخ التطعيم',
    'next_dose'        => 'الموعد القادم',
    'status'           => 'الحالة',
    'total'            => 'إجمالي الفاتورة',
    'no_visits'        => 'لا توجد زيارات سابقة لهذا العميل.',
    'back_to_list'     => 'العودة للعملاء',
    'new_visit'        => 'زيارة جديدة',
    'total_visits'     => 'إجمالي الزيارات',
    'status_overdue'   => 'متأخر',
    'status_soon'      => 'قريب',
    'status_ok'        => 'قادم',
    'status_done'      => 'مكتمل',
],
'types' => [
    'product'     => 'منتج',
    'service'     => 'خدمة',
    'vaccination' => 'تطعيم',
],
```

---

### 3.7 Files Summary

| Action | File |
|--------|------|
| **NEW** | `resources/views/customers/show.blade.php` |
| **MODIFY** | `app/Http/Controllers/CustomerController.php` — add `show()` method |
| **MODIFY** | `routes/web.php` — add `customers/{customer}` route |
| **MODIFY** | `resources/views/customers/index.blade.php` — add "السجل الطبي" button |
| **MODIFY** | `lang/ar/customers.php` — add timeline translations |

---

---

## Implementation Order

Features must be implemented in this exact order due to dependencies:

```
1. Feature 1: Expenses Module
   ↓ (Feature 2 depends on expenses table)
2. Feature 2: Monthly Reports
   ↓ (Independent, but logical after financial infra)
3. Feature 3: Customer Medical Timeline
```

---

## Complete Files Inventory

### New Files (13 total)

| # | File Path |
|---|-----------|
| 1 | `database/migrations/2026_04_04_000001_create_expenses_table.php` |
| 2 | `app/Models/Expense.php` |
| 3 | `app/Http/Controllers/ExpenseController.php` |
| 4 | `app/Http/Requests/StoreExpenseRequest.php` |
| 5 | `app/Http/Requests/UpdateExpenseRequest.php` |
| 6 | `resources/views/expenses/index.blade.php` |
| 7 | `resources/views/expenses/create.blade.php` |
| 8 | `resources/views/expenses/edit.blade.php` |
| 9 | `lang/ar/expenses.php` |
| 10 | `app/Http/Controllers/ReportController.php` |
| 11 | `resources/views/reports/index.blade.php` |
| 12 | `lang/ar/reports.php` |
| 13 | `resources/views/customers/show.blade.php` |

### Modified Files (6 total)

| # | File Path | Changes |
|---|-----------|---------|
| 1 | `routes/web.php` | Add expense resource routes, report route, customer show route |
| 2 | `resources/views/components/sidebar.blade.php` | Add المصروفات and التقارير nav links |
| 3 | `app/Models/User.php` | Add `expenses()` hasMany relationship |
| 4 | `app/Http/Controllers/CustomerController.php` | Add `show()` method |
| 5 | `resources/views/customers/index.blade.php` | Add السجل الطبي button |
| 6 | `lang/ar/customers.php` | Add timeline translations |

---

## SQL Query Budget Per Page

| Page | Expected Queries | Notes |
|------|-----------------|-------|
| `expenses.index` | 3 | 1 count + 1 paginated list + 1 SUM for monthly total |
| `reports.index` | **3** | 1 revenue GROUP BY month + 1 expenses GROUP BY month + 1 top products |
| `customers/{id}` (show) | 4 | 1 customer + 1 invoices + 1 items with product + 1 vaccinations with product (eager loaded) |

---

## Compliance Checklist

- [x] Laravel 12, Bootstrap 5 RTL, Arabic UI
- [x] Vanilla JS only — no new libraries
- [x] Arabic comments above every change
- [x] Admin only (`role:admin`) for all 3 features
- [x] `Invoice::scopeConfirmed()` for all financial queries
- [x] `DB::transaction()` for write operations (store, update, destroy)
- [x] No hard deletes — Expense uses `SoftDeletes`
- [x] No new JS libraries
- [x] Full migration schema documented
- [x] Required routes specified with names
- [x] Controller methods with parameters detailed
- [x] Variables passed to each view listed
- [x] New and modified files inventoried
