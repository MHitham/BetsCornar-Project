# BetsCornar QA Test Report

**Run date:** 2026-11-17  
**Framework:** Pest 3 / PHPUnit 11  
**Database:** SQLite in-memory (`:memory:`), selected by `phpunit.xml`; the application `.env` MySQL database `bets-animal` was not used. `RefreshDatabase` therefore reset only the per-test in-memory database.

## Suite result

The complete command was:

```text
vendor\\bin\\pest.bat --colors=never
```

**Result: 91 passed, 8 failed, 376 assertions, 99 executed tests.**

| Test area | Tests executed | Passed | Failed |
|---|---:|---:|---:|
| Existing baseline and regression tests | 83 | 80 | 3 |
| Requested service QA suite | 11 | 9 | 2 |
| Requested HTTP/authorization/payment checks | 4 | 1 | 3 |
| Duplicate-submission probe | 1 | 1 | 0 |
| **Total** | **99** | **91** | **8** |

## Failed tests and confirmed findings

The tests were intentionally left failing where they demonstrate an application defect or an unmet security/business rule. No application source was changed.

| Test | App evidence | Severity | Suggested fix |
|---|---|---|---|
| `AnimalRelationshipsTest::it casts animal attributes and scopes active animals` | `app/Models/Animal.php:14-30` does not cast `is_active`; the database value is returned as integer `1` rather than boolean `true`. | Medium | Add an `is_active` boolean cast and ensure the model contract matches the migration. |
| `ExampleTest::the application returns a successful response` | `/` is protected by `auth` in `routes/web.php`; an unauthenticated request correctly receives a login redirect (302), while the example expects 200. | Low / test drift | Replace the example expectation with an authenticated dashboard test or assert the guest redirect. |
| `InvoicesRevenueBarTest::employee does not see revenue bar on invoices index` | `resources/views/invoices/index.blade.php:42` includes the revenue modal without the required role condition. | Medium | Gate the revenue modal and its data on the same admin authorization rule used by the controller. |
| `RequestedQASuiteTest::quick sale deducts normal and vaccine stock, records payment, and creates no vaccination` | `app/Services/InvoiceService.php:91-96` calls `StockService::decreaseStock($product, $qty)` without passing the newly-created invoice item. The snapshot logic exists at `app/Services/StockService.php:135-138` but is therefore bypassed. | High | Pass `$invoiceItem` into `decreaseStock()` or centralize cost snapshotting in the invoice service. |
| `RequestedQASuiteTest::return rejects an invoice item belonging to another invoice` | `app/Services/InvoiceService.php:190-192` finds the submitted item globally and does not constrain it to `$invoice->items()`. | Critical | Query the item through the route invoice relationship under lock, or reject when `invoice_id !== $invoice->id`. |
| `RequestedQASuiteTest::debug admin endpoint is not publicly available` | `routes/web.php:30-41` exposes `/debug-admin` without authentication and returns a response. | Critical | Remove the route; if retained for development, protect it with authentication and a strict non-production guard. |
| `RequestedQASuiteTest::legacy payment endpoint keeps invoice aggregate and payment ledger consistent` | `app/Http/Controllers/InvoicePaymentController.php:22-35` updates `invoices.amount_paid` but does not insert an `InvoicePayment` row. | High | Remove the legacy implementation or route it through the transactional payment service that writes the ledger row and locks the invoice. |
| `RequestedQASuiteTest::employee access to an invoice does not reveal another employee invoices` | `routes/web.php` protects invoice routes by role but no record-level ownership/policy check prevents an employee from viewing another employee’s invoice. | High | Add an Invoice Policy or explicit scope/authorization check for employee-owned records. |

## Observed duplicate-submission behavior

The duplicate probe submitted each payload twice sequentially and passed with the following observed counts:

| Flow | Observed result |
|---|---:|
| Customer visit with the same normalized phone | 1 customer, 2 invoices |
| Quick sale with the same payload | 2 invoices |
| Purchase order with the same payload | 2 purchase orders |

The customer identity is deduplicated by normalized phone, but the financial operations are not idempotent. If retries are possible at the HTTP/client layer, add an idempotency key or an equivalent request de-duplication mechanism.

## Important passing coverage

The suite also verifies that:

- Customer visits create consultation, vaccination, and additional-item records and roll back completely on insufficient vaccine stock.
- Existing animals are linked to invoices/vaccinations, while cross-customer animal submissions are rejected by validation without partial writes.
- Quick sales create payment rows and do not create vaccination rows for vaccine products.
- Invoice cancellation restores normal and vaccine stock and rejects a second cancellation.
- Partial returns update invoice totals and refund calculations and restore normal stock.
- FEFO selects the earliest usable batch, splits across batches, excludes expired batches, and rolls back on insufficient stock.
- Normal stock status transitions and weighted average cost calculations work for the tested cases.
- Vaccine purchases create batches; a vaccine purchase without an expiry currently fails and rolls back, which is documented by a dedicated test.
- Admin-only routes reject employees, customer phone normalization works across Egyptian formats, referenced products are deactivated rather than deleted, and expenses are soft-deleted.

## Areas not fully tested

- A complete route-list iteration for every protected route with all required route parameters was not added; representative guest and employee coverage already exists in `ProjectBaselineTest`.
- The requested dashboard 02:00 business-day edge cases using `Carbon::setTestNow()` were not completed in this run. The existing dashboard/report tests do not fully prove behavior at 01:59 versus 02:00.
- Full Form Request matrix coverage for negative quantities, invalid dates, and every `next_dose_date`/`vaccination_date` combination was not completed. Existing tests cover several important validation paths.
- Concurrent transaction/race testing for payments, cancellation, purchase WAC, and order-number generation was not performed because the configured SQLite in-memory database is not a meaningful substitute for concurrent MySQL locking behavior.
- MySQL-specific report SQL compatibility was not tested. The suite uses SQLite adapters in selected existing report tests; this does not validate production MySQL execution.
- The isolated `tests/Audit` suite is outside the configured `Unit` and `Feature` PHPUnit directories and was not included in the complete default run.

## Coverage

No PHP coverage driver (`Xdebug` or `PCOV`) is installed or enabled in the current PHP CLI environment. A line/branch coverage summary could not be generated.

## Files added or modified

- `tests/TestCase.php` — shared `actingAsAdmin()`, `actingAsEmployee()`, and consultation-product helpers.
- `tests/Feature/RequestedQASuiteTest.php` — requested focused Pest coverage.
- `TEST_REPORT.md` — this report.

Testing complete. Waiting for next instructions.
