# Performance Audit — BetsCornar Laravel Project

## Audit Summary

Full code-level audit of 5 target pages, their controllers, models, views, routes, and migrations.
**Goal:** Identify every performance issue, classify by risk, execute Level 1 only.

---

## Page-by-Page Audit Results

### 1) `vaccinations.index`

| Metric | Current State |
|--------|--------------|
| **Estimated SQL queries** | **~35–40** per page load (15 items paginated) |
| **Root cause** | Blade `@php` block on lines 8–47 runs a **full duplicate query** identical to the controller's — effectively doubling all queries |
| **N+1 queries** | No — `with(['customer','product','invoice'])` is correct |
| **Duplicate query** | ✅ The `@php` block on line 8 creates **a brand new `Vaccination::query()`** with the same filters/sort/pagination, **completely ignoring** the `$vaccinations` variable passed from the controller |
| **2nd duplicate query** | ✅ Line 40: `$threeDaysUpcoming` — another full Eloquent query with `with(['customer','product'])` — runs **every page load** regardless of whether the modal is opened |
| **Missing indexes** | `vaccinations.is_completed` — filtered on every request, no index |
| **Missing compound index** | `vaccinations(is_completed, next_dose_date)` — used together in 3-day upcoming query |
| **Pagination** | ✅ 15 per page — reasonable |

> [!CAUTION]
> **Critical Issue:** `vaccinations/index.blade.php` lines 8–47 contain raw Eloquent queries that **completely replace** the controller's data. The controller fetches data into `$vaccinations`, but the Blade view **overwrites it** with its own query. This means **every page load runs 2× the queries** needed.

---

### 2) `customers.index`

| Metric | Current State |
|--------|--------------|
| **Estimated SQL queries** | ~8–10 (20 items paginated) |
| **N+1 queries** | No — partial. `withCount('vaccinations')` + constrained eager load of latest vaccination is correct |
| **Eager loading** | ✅ Properly done |
| **Missing indexes** | `customers.name` — used in `LIKE` search (limited benefit for LIKE but helps `ORDER BY`) |
| **Pagination** | ✅ 20 per page — good |
| **Redundant** | `$customers->total()` called twice (line 10 stores it, line 41 calls it again) — negligible |

✅ **This page is reasonably well-optimized.** Minor index additions possible.

---

### 3) `dashboard` (route closure in `web.php`)

| Metric | Current State |
|--------|--------------|
| **Estimated SQL queries** | **7 separate queries** in the route closure |
| **Queries breakdown** | 1) `Invoice::confirmed()->whereBetween->count` 2) `Invoice::confirmed()->whereBetween->sum` 3) `Product::active()->count` 4) `Vaccination::count` 5) `Vaccination::query()->with([...])->...->get` 6) `Product::query()->active()->...->get` 7) `VaccineBatch::query()->with('product')->...->get` (×2 = expired + expiring soon) |
| **Optimizable** | Queries 1 & 2 can be merged into a single `SELECT COUNT(*), SUM(total)` |
| **N+1 queries** | No — eager loading used correctly |
| **Missing indexes** | `invoices.status` — used in `confirmed()` scope, no index |
| **Missing indexes** | `invoices.created_by` — filtered for employees, no explicit index (FK exists but not always indexed) |
| **Pagination** | N/A — dashboard widgets use `->limit(10)->get()` which is fine |

---

### 4) `invoices.index`

| Metric | Current State |
|--------|--------------|
| **Estimated SQL queries** | **Admin: ~8–10** / Employee: ~3 |
| **Root cause** | Blade `@php` block (lines 8–55) runs **its own full query set** for admins, computing 3 tab counts via `clone` + the actual paginated list. Controller's query is ignored for admins. |
| **Duplicate work** | ✅ Controller builds `$invoices` for admin, then Blade **overwrites** it with its own query — same double-execution pattern as vaccinations |
| **Tab counts** | 3 separate `COUNT(*)` queries (today, month, all) — runs every page load |
| **Missing indexes** | `invoices.source` — filtered frequently, no index |
| **Missing indexes** | `invoices.status` — filtered by scope, no index |
| **N+1** | No — `with('customer')` is correct |
| **Pagination** | ✅ 25 per page — good |

---

### 5) Upcoming Vaccinations Modal (3-day view)

| Metric | Current State |
|--------|--------------|
| **Location** | `vaccinations/index.blade.php` line 40 AND `web.php` line 73 (dashboard) |
| **Queries** | Separate query each location — no reusability |
| **Missing indexes** | `vaccinations(is_completed, next_dose_date)` compound index would serve both |
| **No pagination** | `->get()` fetches ALL upcoming — acceptable as 3-day window is typically small |

---

## Existing Indexes Audit

| Table | Existing Indexes |
|-------|-----------------|
| `vaccinations` | `PK(id)`, `FK(customer_id)`, `FK(product_id)`, `FK(invoice_id)`, `idx(next_dose_date)` |
| `invoices` | `PK(id)`, `UNIQUE(invoice_number)`, `FK(customer_id)`, `idx(created_at)`, `FK(created_by)` |
| `customers` | `PK(id)`, `UNIQUE(phone)` |
| `products` | `PK(id)`, `idx(type)`, `idx(is_active)` |
| `vaccine_batches` | `PK(id)`, `FK(product_id)`, `idx(product_id, expiry_date)`, `idx(expiry_date)` |
| `invoice_items` | `PK(id)`, `FK(invoice_id)`, `FK(product_id)`, `idx(invoice_id)`, `idx(product_id)` |

### Missing Indexes Needed

| Table | Column(s) | Used By | Priority |
|-------|-----------|---------|----------|
| `vaccinations` | `is_completed` | Every vaccinations.index load, dashboard | **Level 1** |
| `vaccinations` | `(is_completed, next_dose_date)` | 3-day upcoming query (2 locations) | **Level 1** |
| `vaccinations` | `vaccination_date` | Sort column on vaccinations.index | **Level 1** |
| `invoices` | `source` | Filter on invoices.index | **Level 1** |
| `invoices` | `status` | `confirmed()` / `cancelled()` scopes | **Level 1** |
| `invoices` | `created_by` | Employee filter | Level 2 |
| `customers` | `name` | Search LIKE (limited benefit) | Level 3 |

---

## Vite / Assets Review

| Item | Status |
|------|--------|
| **CDN dependencies** | Bootstrap CSS/JS + Google Fonts + Bootstrap Icons loaded from CDN — acceptable for small project |
| **Vite entry points** | `app.css`, `layout.css`, `app.js` — clean, minimal bundle |
| **Tailwind** | Installed but `app.css` is only 2.5KB and `layout.css` is 7.5KB — not a bottleneck |
| **Inline JS** | Large `<script>` blocks in vaccination and customer views — not ideal but acceptable |
| **No code splitting** | Single bundle — fine for this project size |

✅ **No critical asset performance issues.**

---

## Classification of Fixes

### Level 1 — Safe (✅ Execute)

> Zero behavior change, zero UI change, zero business logic change.

| # | Fix | Files | Risk |
|---|-----|-------|------|
| **L1-1** | **Move Blade Eloquent queries → VaccinationController@index** | `VaccinationController.php`, `vaccinations/index.blade.php` | **None** — Blade already overrides controller data. Moving queries to controller and removing Blade `@php` blocks makes the view consume controller-provided data. Exact same queries, exact same variables. |
| **L1-2** | **Move Blade Eloquent queries → InvoiceController@index (admin path)** | `InvoiceController.php`, `invoices/index.blade.php` | **None** — Same pattern. Move 3 count queries + period filter to controller. Pass all needed variables. |
| **L1-3** | **Add missing database indexes via migration** | New migration file | **None** — `ADD INDEX` is non-blocking on small tables, doesn't change behavior |
| **L1-4** | **Merge 2 dashboard queries into 1** (todayVisits + todayRevenue) | `routes/web.php` | **None** — `selectRaw('COUNT(*) as count, SUM(total) as sum')` replaces two separate calls |
| **L1-5** | **Eliminate duplicate $vaccinations query in Blade** | `vaccinations/index.blade.php` | **None** — Direct consequence of L1-1; Blade will use controller's `$vaccinations` instead of running its own |

### Level 2 — Medium Risk (Document only)

| # | Fix | Description |
|---|-----|-------------|
| L2-1 | Cache dashboard counts | `todayVisits`, `totalProducts`, `totalVaccinations` could be cached for 5 min |
| L2-2 | Lazy-load 3-day upcoming modal via AJAX | Currently runs query on every page load even if modal is never opened |
| L2-3 | Move dashboard route closure to a DashboardController | Better separation of concerns |
| L2-4 | Add `created_by` index on invoices | FK exists but may not have explicit index |

### Level 3 — Higher Risk (Document only)

| # | Fix | Description |
|---|-----|-------------|
| L3-1 | Replace CDN assets with local Vite bundles | Could improve first-load by eliminating external requests |
| L3-2 | Add query result caching on vaccination counts | Risk of stale data |
| L3-3 | Database denormalization (cached customer_name on vaccinations) | Schema change, migration complexity |

---

## Proposed Changes (Level 1 Only)

### Component: VaccinationController + View

#### [MODIFY] [VaccinationController.php](file:///d:/Laravel/Projects-test/bets-cornar/app/Http/Controllers/VaccinationController.php)

Update `index()` method to:
1. Accept `is_completed` and `sort` filters (currently handled only in Blade)
2. Build the `$threeDaysUpcoming` query
3. Build the `$customerVaccinationsMap` array
4. Pass `$threeDaysUpcoming`, `$customerVaccinationsMap`, `$isCompleted`, `$sort` to the view

This consolidates **3 separate Eloquent queries** (main list, 3-day upcoming, customer map) that were running in the Blade `@php` blocks into the controller where they belong.

#### [MODIFY] [index.blade.php (vaccinations)](file:///d:/Laravel/Projects-test/bets-cornar/resources/views/vaccinations/index.blade.php)

Remove the 2 `@php` blocks (lines 8–47 and 49–78) that contain raw Eloquent queries. The view will consume variables passed from the controller instead.

---

### Component: InvoiceController + View

#### [MODIFY] [InvoiceController.php](file:///d:/Laravel/Projects-test/bets-cornar/app/Http/Controllers/InvoiceController.php)

Update `index()` admin path to:
1. Accept `period` and `date` parameters
2. Compute `$countToday`, `$countMonth`, `$countAll` tab counts
3. Apply period/date filter for actual list
4. Pass all count variables + `$period`, `$date`, `$hasFilters` to view

#### [MODIFY] [index.blade.php (invoices)](file:///d:/Laravel/Projects-test/bets-cornar/resources/views/invoices/index.blade.php)

Remove the admin `@php` block (lines 8–55) that duplicates the controller's query logic. Use controller-provided variables.

---

### Component: Database Indexes

#### [NEW] [add_performance_indexes.php](file:///d:/Laravel/Projects-test/bets-cornar/database/migrations/2026_04_02_000001_add_performance_indexes.php)

Add indexes:
- `vaccinations.is_completed`
- `vaccinations.vaccination_date`
- `vaccinations(is_completed, next_dose_date)` compound
- `invoices.source`
- `invoices.status`

---

### Component: Dashboard

#### [MODIFY] [web.php](file:///d:/Laravel/Projects-test/bets-cornar/routes/web.php)

Merge `todayVisits` COUNT + `todayRevenue` SUM into a single query using `selectRaw()`.

---

## Expected Impact (Before → After)

| Page | Before (queries) | After (queries) | Savings |
|------|-----------------|-----------------|---------|
| `vaccinations.index` | ~35–40 | ~18–20 | **~50% reduction** |
| `invoices.index` (admin) | ~8–10 | ~5–7 | **~30% reduction** |
| `dashboard` | 8 | 7 | **1 query saved** |
| `customers.index` | ~8–10 | ~8–10 (unchanged) | Already optimized |

---

## Verification Plan

### Automated Tests
1. Run `php artisan migrate` to apply new index migration
2. Visit each page in browser to verify identical UI/behavior
3. Optional: Enable `DB::listen()` or Laravel Debugbar to compare query counts

### Manual Verification
- Verify all 5 pages load correctly with same data
- Verify pagination, search, filters, and modals work identically
- Verify no visual changes

---

## Open Questions

> [!IMPORTANT]
> **No open questions.** All Level 1 changes are mechanical refactors (moving queries from Blade to Controller) and additive indexes. Zero behavior change guaranteed.

Shall I proceed with executing Level 1?
