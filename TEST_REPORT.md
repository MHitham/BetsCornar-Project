# BetsCornar QA report

## Release candidate verification

- `vendor\bin\pest.bat --colors=never` passed all **178 tests (712 assertions)** after the backup restore fix, including Unit, Feature, and Audit. The test process explicitly used SQLite in memory and isolated cache, session, queue, and mail settings.
- Pint passed on all **16 changed PHP files** after fixing two import-order findings. Blade templates were exercised by the test suite. `npm run build`, Composer platform checks, and `git diff --check` passed.
- The production Composer lockfile audit reported **69 advisories across 16 packages**. The npm audit reported **11 affected entries** (2 critical, 8 high, 1 moderate). Dependency remediation is still pending.
- The same-minute backup restore issue below was fixed. On a separate temporary MySQL server, restoring a legacy backup in the same minute restored the original fixture data and left the selected SQL file's SHA-256 unchanged. Restoring the automatically created safety copy recovered the data from immediately before the first restore. Pest also checked retention and the failure paths.

## Previous QA run

Run date **2026-11-20** (Cairo). Laravel 12.56, PHP 8.3, Pest 3, Node 24 and MySQL 8.4.11.

## Application reviewed

BetsCornar is an Arabic veterinary clinic app. Laravel routes, form requests, and role middleware handle access and input. Services coordinate customer visits, animal records, sales and payments, returns and cancellation, stock and vaccine batches, purchases and supplier balances, expenses, and cash and profitability reporting. The UI uses Blade, Bootstrap RTL, and Tailwind 4 through Vite. The scheduler registers database backups and license checks.

All 10 skills in `skills-lock.json` were present under `.agents/skills` and available to Codex at project scope. Laravel architecture, Eloquent, database, security, verification, and Pest guidance informed the review and tests. Tailwind guidance informed the asset checks. The host's installed Playwright skill was used to run the browser checks.

## Results

| Check | Result |
| --- | --- |
| Full Pest suite on SQLite in memory | **162 passed, 589 assertions** across Unit, Feature, and Audit. Xdebug measured **61.7% line coverage** for `app/`. The Audit group contributed 10 passing tests. |
| Pest on temporary MySQL | The first run covered 160 compatible cases, including Audit. Of these, 158 passed and the two purchase-cancellation tests exposed issues in their test setup and assertions. The corrected 11-test purchase-cancellation group passed on MySQL with 36 assertions. The two SQLite-specific cases passed on SQLite, and MySQL report calculations were verified separately using native SQL. |
| Database migrations | All 39 migrations applied to an empty MySQL schema. The 17 pending migrations also applied to a temporary copy of the supplied clinic SQLite database with its existing records. |
| Financial reports on MySQL | Annual, monthly, and profitability routes returned HTTP 200. A fixture with revenue 200, cost of goods 60, and operating expense 20 returned profit 120. Supplier cash of 50 appeared separately. |
| Dashboard day boundary | At 01:59 the dashboard counted the previous business day's two fixture visits. At 02:00 it counted the new business day's one fixture visit. |
| Chrome via Playwright | The guest redirect, admin login, and nine clinic pages passed. An employee could open Customers and was redirected from Reports and Settings. Verified that visit, quick-sale, and purchase forms contained submission tokens. At 390 px, the RTL invoices page fit the viewport and the sidebar opened and closed. The built stylesheet and double-submit script returned HTTP 200. The SQLite report page returned HTTP 500. |
| Build and syntax | `npm run build`, syntax checks for 180 PHP and five JavaScript files, and Laravel config, route, and Blade cache preparation passed. Laravel listed 81 application routes. |
| Composer validation | Platform requirements passed. The manifest validated with a warning about the exact `maatwebsite/excel` version constraint. |
| Pint | Five files still have lint findings. The corrected purchase test file passes the import-order check. |
| Dependency audit | `composer audit --locked` reported **75 advisories across 18 packages**, with 58 advisories in 16 production packages. `npm audit` flagged **11 dependency entries**, including two rated critical and eight high. These are dependency audit results. |

## Confirmed findings

1. **Fixed: restoring a backup in the same minute could overwrite the selected recovery file.** The previous MySQL probe confirmed a filename collision. The service now validates the selected file, gives the safety copy a unique name, and protects both files from retention cleanup during restore. [BackupService.php](app/Services/BackupService.php)
2. **The default MySQL database is missing from the current workstation.** Laravel returned `Unknown database 'bets-animal'` for the configured connection. The existing clinic records were available in `database/database.sqlite`, and the browser checks ran against a temporary copy of that file.
3. **The annual and profitability report queries depend on MySQL date functions.** The browser reproduced HTTP 500 for `/reports` on SQLite. The controller uses `MONTH()` and `YEAR()`. The corresponding report routes and calculations passed on MySQL. [ReportController.php:22](app/Http/Controllers/ReportController.php#L22) · [ReportController.php:261](app/Http/Controllers/ReportController.php#L261)
4. **Formatting and dependency audit checks are still failing.** Pint flagged `BackfillCashRefunds.php`, `InvoicePaymentController.php`, `VerifyCloudflareLicense.php`, `UpdateCustomerRequest.php`, and `PurchasePayment.php`. The dependency advisories are reproducible from both lockfiles with the audit commands below.

## Test corrections and verification scope

- The cumulative cancellation test now refreshes the product before setting its database quantity to 10. This ensures the fixture actually has less stock than the sum of the two purchased lines.
- The duplicate cancellation test now checks the success response, the duplicate redirect and message, and the one unit of original stock remaining after cancellation. [PurchaseCancelTest.php](tests/Feature/PurchaseCancelTest.php)
- Browser testing used a temporary clone with disposable accounts. MySQL checks used temporary schemas and synthetic fixtures. The two fixes are confined to test code. Live license activation, background worker execution, and concurrent MySQL transactions were outside this run.

```powershell
php vendor/pestphp/pest/bin/pest --bootstrap tests/isolated-bootstrap.php --colors=never
php vendor/laravel/pint/builds/pint --test
composer validate --no-check-publish
composer check-platform-reqs
composer audit --locked
npm audit
npm run build
```

Coverage was measured with the official PHP 8.3 TS x64 Xdebug DLL enabled for the test process. [Xdebug downloads](https://xdebug.org/download)
