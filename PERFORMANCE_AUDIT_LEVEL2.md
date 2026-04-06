# PERFORMANCE AUDIT LEVEL 2 — BetsCornar

> Performance update and analysis (Level 2): N+1 inspection, unoptimized select queries, caching, pagination, and redundant queries.

---

## 1. N+1 Query Detection

**[Low]** — File: `app/Http/Controllers/CustomerController.php` — Method: `index()`
- Problem: Using `limit(1)` within `with('vaccinations')` does not work as expected in Laravel (it applies the limit to the entire query and not per customer).
- Current code: 
  ```php
  ->with(['vaccinations' => function ($q) {
      $q->latest('vaccination_date')->limit(1);
  }])
  ```
- Suggested fix: Add a new relationship in the `Customer` model named `latestVaccination` using `hasOne()->latestOfMany()` and eager load it by calling `with('latestVaccination')`.
- Impact: Low (This may return incorrect data rather than causing N+1, but it is a logical error).

**[Medium]** — File: `app/Http/Controllers/VaccinationController.php` — Method: `index()`
- Problem: Hidden N+1 Query in upcoming vaccinations closure.
- Suggested fix: 
  ```php
  // Ensure relations are eager loaded with specifically selected columns to prevent hidden N+1
  ->with(['customer:id,name,phone'])
  ```
- Impact: Medium.

---

## 2. Select * Usage (Unoptimized Fetching)

⚠️ **Safety Note:** When using `select()`, ensure all foreign keys used in Blade or relationships are explicitly included (especially `customer_id`, `product_id`). Missing foreign keys will break Eloquent relationships when using eager loading.

**[Medium]** — File: `routes/web.php` — Method: `Dashboard Closure`
- Problem: Using `get()` to fetch all columns instead of specifically selected columns in Dashboard queries.
- Suggested fix: Append `->select()` to fetch only required columns.
  - On `$lowStockProducts`:
    ```php
    ->select(['id', 'name', 'quantity', 'stock_status', 'low_stock_threshold', 'type'])
    ```
  - On batches queries:
    ```php
    ->select(['id', 'product_id', 'batch_code', 'expiry_date', 'quantity_remaining'])
    ->with('product:id,name')
    ```
- Impact: Medium (Decreases memory consumption).

**[Medium]** — File: `app/Http/Controllers/VaccinationController.php` — Method: `index()`
- Problem: Using `get()` without `select()` to fetch upcoming vaccinations, which could cause a `Hidden N+1`.
- Suggested fix: 
  ```php
  // Fetch Modal columns only while also selecting relationship columns to prevent N+1 and relation breakage
  ->select([
      'id', 
      'customer_id', 
      'product_id', 
      'next_dose_date', 
      'vaccination_date', 
      'is_completed'
  ])
  ->with(['customer:id,name,phone'])
  ```
- Impact: Medium.

**[Medium]** — File: `app/Http/Controllers/InvoiceController.php` — Method: `index()`
- Problem: Using `paginate()` without explicitly selecting required fields.
- Suggested fix: Add the following line before paginating:
  ```php
  // Select required columns for the invoice list instead of select *
  ->select(['id', 'invoice_number', 'customer_id', 'customer_name', 'source', 'total', 'status', 'created_at', 'cancelled_at'])
  ```

---

## 3. Dashboard Query Caching Opportunities & Strategy

**Cache Strategy Considerations:**
* Date-based cache metrics MUST use dynamic keys bounded to the current date. To prevent inconsistencies when the server timezone differs from the application timezone, use `now()->startOfDay()->toDateString()`.
* Missing cache busting logic leads to stale data on the dashboard.
* Canceling an invoice heavily impacts multiple dashboard metrics simultaneously (sales numbers, vaccinations, and product inventory); therefore, robust cache invalidation is necessary.
* **Cache Consistency Tradeoff:** Mixing cached metrics (e.g. `totalVaccinations`) with live metrics (e.g. `todayRevenue`) may cause temporary inconsistencies in dashboard numbers. This is acceptable in most systems but should be acknowledged.

**File:** `routes/web.php` (Dashboard closure)

**(Optional Refactor for Code Cleanliness)**
Suggest creating a global helper method for caching in Dashboard to reduce duplication, maintain consistency for dynamic keys, and allow usage in route closures:
```php
if (!function_exists('dashboardKey')) {
    function dashboardKey($key)
    {
        return "dashboard.{$key}_" . now()->startOfDay()->toDateString();
    }
}
```

**Proposed Implementation for Caching:**
```php
use Illuminate\Support\Facades\Cache;

// Cache total active products - refreshes daily
$totalProducts = Cache::remember('dashboard.total_products', now()->addDay(), function () {
    return \App\Models\Product::where('is_active', true)->count();
});

// Cache total vaccinations - refreshes hourly
$totalVaccinations = Cache::remember('dashboard.total_vaccinations', now()->addHour(), function () {
    return \App\Models\Vaccination::count();
});

// Cache upcoming vaccinations - refreshes at the end of the day (using dynamic key helper)
$upcomingVaccinations = Cache::remember(dashboardKey('upcoming_vaccinations'), now()->endOfDay(), function () {
    // Original query goes here
});

// Cache batch expiry alerts - refreshes at the end of the day (using dynamic key helper)
$batchExpiry = Cache::remember(dashboardKey('batch_expiry'), now()->endOfDay(), function () {
    // Original query here (after merging)
});
```

⚠️ `$todayVisits` and `$todayRevenue` are deliberately NOT cached — they should always remain Live.

**Cache Busting Locations:**
*(Use the global identifier `dashboardKey()` to guarantee consistency)*

1. **In `app/Services/CustomerVisitService.php`** after saving the invoice/visit:
   ```php
   Cache::forget('dashboard.total_vaccinations');
   Cache::forget(dashboardKey('upcoming_vaccinations'));
   ```
2. **In `app/Http/Controllers/ProductController.php`** (methods: `store`, `update`, `toggleActive`):
   ```php
   Cache::forget('dashboard.total_products');
   ```
3. **In `app/Http/Controllers/VaccinationController.php`** (methods: `complete`, `reschedule`):
   ```php
   Cache::forget(dashboardKey('upcoming_vaccinations'));
   Cache::forget('dashboard.total_vaccinations');
   ```
4. **In `app/Http/Controllers/VaccineBatchController.php`** (methods: `store`, `update`):
   ```php
   Cache::forget(dashboardKey('batch_expiry'));
   ```
5. **In `app/Services/InvoiceService.php`** (method: `cancelInvoice()`):
   ```php
   Cache::forget('dashboard.total_vaccinations');
   Cache::forget(dashboardKey('upcoming_vaccinations'));
   Cache::forget(dashboardKey('batch_expiry'));
   ```

---

## 4. Pagination & Memory Check

**[High]** — File: `app/Http/Controllers/CustomerController.php` — Method: `exportExcel()`
- Problem: Calling `get()` to fetch all customers for Excel export could cause Memory Exhaustion, and using `orderBy('name')` could compromise Chunking stability.
- Current code: 
  ```php
  $customers = $request->boolean('select_all_ids') ? $this->customersIndexQuery($request)->orderBy('name')->get([...]) : ...;
  ```
- Suggested fix: Use `FromQuery` instead of `FromArray`, completely drop `orderBy('name')`, and apply `orderBy('id')` instead:
  ```php
  // Instead of orderBy('name'), you must use:
  $query->orderBy('id');
  ```
  ```php
  // Add inside Export Classes:
  public function chunkSize(): int
  {
      return 1000;
  }
  ```
  ⚠️ Removing `orderBy('name')` completely and substituting it with `orderBy('id')` ensures Deterministic Chunking to prevent duplicate or skipped rows during processing.
  
  * Note: Laravel Excel chunks internally when using `FromQuery`. If manually chunking outside of export, confirm usage as:
    ```php
    ->chunkById(1000, function ($rows) {
        // process
    });
    ```
  * Note: Lazy collections may also be used for extreme datasets.
- Impact: High ⚠️ (Prevents Server Crash).

**[High]** — File: `app/Http/Controllers/VaccinationController.php` — Method: `exportExcel()`
- Problem: The exact same `get()` issue when choosing "End of file/All Pages Export".
- Current code: 
  ```php
  $vaccinations = $request->boolean('select_all_ids') ? $this->vaccinationsExportQuery($request)->get() : ...;
  ```
- Suggested fix: Export data chunks via `FromQuery` and add the Chunk improvement.
  ```php
  // Add inside Export Classes:
  public function chunkSize(): int
  {
      return 1000;
  }
  ```
  ⚠️ Ensure `->orderBy('id')` is appended to the query to guarantee accurate chunk ordering.
  
  * Note: Ensure queries include `->orderBy('id')` as manual chunking will also require:
    ```php
    ->chunkById(1000, function ($rows) {
        // process
    });
    ```
- Impact: High ⚠️.

---

## 5. Repeated/Redundant Queries & Query Merging

**[Medium]** — File: `app/Models/VaccineBatch.php` — Model Casts
- Problem: Relying on `isPast()` in PHP might crash if the date field is not cast correctly to `Carbon`.
- Suggested fix: Guarantee a valid `casts` definition for the field:
  ```php
  protected $casts = [
      'expiry_date' => 'date',
  ];
  ```

**[Low]** — File: `routes/web.php` — Method: `Dashboard Closure`
- Problem: Querying `VaccineBatch` twice separately for Expired and Expiring Soon alerts.
- Suggested fix: Run a single query to grab both batches, then partition them inside PHP using `->partition()`.
- Implementation:
  ```php
  // A single query fetches both expired and upcoming expiry batches instead of two separate queries
  $allExpiryBatches = \App\Models\VaccineBatch::query()
      ->whereDate('expiry_date', '<=', now()->addDays(5))
      ->where('quantity_remaining', '>', 0)
      ->with('product:id,name')
      ->select(['id', 'product_id', 'batch_code', 'expiry_date', 'quantity_remaining'])
      ->get();

  // Partition results via PHP instead of a second database round trip
  [$expiredBatches, $expiringSoonBatches] = $allExpiryBatches->partition(
      fn($batch) => $batch->expiry_date->isPast()
  );
  ```
- Impact: Low (Saves 1 Database query).

---

## 6. Detailed Fix Plan: CustomerController Relationship Bug

**[Bug Description]**
In `CustomerController@index`, the relation mapping uses a hard limit `limit(1)` inside the `vaccinations` closure map. Laravel globally applies this `limit(1)` to the single whole Eager Loading query, not incrementally on a per customer index level. This leads directly to either incomplete subsequent customer mappings or induced residual N+1 mapping requests.

**1. Model Change (`app/Models/Customer.php`)**
- Add the following relationship:
  ```php
  // Added: Securely fetches the latest customer vaccination safely bypassing Eager Load Limits
  public function latestVaccination()
  {
      return $this->hasOne(Vaccination::class)->latestOfMany('vaccination_date');
  }
  ```

**2. Controller Change (`app/Http/Controllers/CustomerController.php`)**
- In the `index()` method, replace:
  ```php
  ->with(['vaccinations' => function ($q) {
      $q->latest('vaccination_date')->limit(1);
  }])
  ```
- With this:
  ```php
  // Added: Employs latestVaccination instead of the faulty limit(1) mapped relationship 
  ->with('latestVaccination')
  ```

**3. Blade Change (`resources/views/customers/index.blade.php`)**
- Swap the collection extraction call:
  ```php
  @php $lastVacc = $customer->vaccinations->first(); @endphp
  ```
- To output as follows:
  ```php
  {{-- Added: Triggers the new direct relation fetch for the latest vaccination natively --}}
  @php $lastVacc = $customer->latestVaccination; @endphp
  ```

⚠️ **Risk:** Double-check that `latestVaccination` does not collide with existing relationships or functions declared in the model wrapper.

---

## 7. Focused InvoiceController Audit

### 1. N+1 in `index()`
**[Unnecessary Eager Load]** — Method: `index()` — File: `app/Http/Controllers/InvoiceController.php`
- Problem: Eager loads the `customer` relationship even though it is completely unused in the `invoices.index` blade (the view simply displays the `customer_name` column mapped synchronously onto the table wrapper).
- Current code: `->with('customer')`
- Suggested fix: Wipe `->with('customer')` off both the Administrator and Staff payload to free up database and memory overhead.
- Impact: High (Removes an entire database transaction completely whilst boosting payload yields).

### 2. N+1 in `show()`
**[N+1 Query]** — Method: `show()` — File: `app/Http/Controllers/InvoiceController.php`
- Problem: Within the Invoice Show view wrapper, the injected iteration looping over vaccinations requests a product attribute `vacc->product->name`. However, the root `load()` binding only hooks `vaccinations`, resulting in repetitive N+1 database transactions inside the payload rendering pipe.
- Current code: `$invoice->load(['customer', 'items.product', 'vaccinations']);`
- Suggested fix: Update the loader map sequentially using `$invoice->load(['items.product', 'vaccinations.product'])` (Furthermore, purge `customer` entirely from here too if unused by Blade maps).
- Impact: Medium.

### 3. Select * usage
**[Unoptimized Fetching]** — Method: `index()` — File: `app/Http/Controllers/InvoiceController.php`
- Problem: Passing `paginate()` implicitly binds a wildcard mapping `select *`, subsequently transporting all metadata available indiscriminately.
- Current code: `->paginate(25)`
- Suggested fix: Define explicit yields before payload paginating via `->select(['id', 'invoice_number', 'customer_name', 'source', 'total', 'status', 'created_at'])`.
- Impact: Low to Medium.

### 4. Redundant queries
No unnecessary duplicated queries exist cascading within equivalent isolated methods boundaries.

---

## Final Output Summary

### 1. Total issues found per category
- **N+1 / Eager Loading:** 4 Issues (1 Limits Bug in Customer, 1 Unused Customer in Invoice, 1 Missing `vaccinations.product` in Invoice, 1 Hidden N+1 in Vaccination).
- **Select * Usage:** 3 Issues (2 in Dashboard, 1 in `InvoiceController@index`).
- **Dashboard Caching:** 4 Opportunities identified (with robust cache busting parameters added).
- **Pagination Check:** 2 Issues (Memory safe exports via `FromQuery` and chunking).
- **Redundant Queries:** 1 Issue (Dashboard batches).

### 2. Recommended execution order
1. **[High] Fix Export Memory Issues**: Migrate subscriber and vaccination endpoints to `FromQuery` combined with restricted Chunk boundaries.
2. **[High] Fix Relationship Bug**: Immediately execute the `latestVaccination` workaround within the Customer models preventing recursive layout logic breakdown errors.
3. **[Medium] Fix Invoice Eager Loads**: Purge unused `with('customer')` wrappers entirely bypassing queries and patch N+1 `show()` constraints.
4. **[Medium] Limit Selects**: Integrate deterministic mapping structures explicitly inside `select()` to bypass memory fragmentation limits.
5. **[Medium] Caching**: Cache all baseline widget metrics on the Dashboard while linking global event state clearing functionality explicitly on dependent interactions, tied reliably to dynamic day scopes keys.
6. **[Low] Merge Batches Query**: Condense isolated batch data scopes securely encapsulating them iteratively.

### 3. Estimated query reduction per page
- **Dashboard**: From ~7 queries to **~3 queries** (Load time plummets explicitly via layered caching implementations, data collision metrics safely avoided by utilizing scoped dynamic keys bounds).
- **Invoices Index**: From 3 queries to **2 queries** (Generated by terminating unnecessary embedded relational logic payloads).
- **Invoices Show**: N+1 for vaccinations removed (Rescues N subsequent queries mathematically identical to mapped vaccinations payloads attached to equivalent parent relationships).
- **Excel Exports**: Memory footprint drastically locked beneath secure barriers effectively mitigated via structured determinism combined natively around `chunkSize(1000)` and `FromQuery`.
- **Customers Index**: Authentic structural mappings of recent vaccination yields reliably overrides inconsistent batch retrieval gaps without generating systemic downstream payload loading times.
