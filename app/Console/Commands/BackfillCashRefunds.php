<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceReturn;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * سكريبت تصحيح لمرة واحدة للفواتير القديمة اللي كان فيها amount_paid أكبر من total
 * بسبب مرتجعات اتعملت قبل إضافة نظام cash_refunded - بيصحح البيانات القديمة
 * عشان تقارير الكاش تبقى مظبوطة.
 *
 * Usage:
 *   php artisan app:backfill-cash-refunds           → تطبيق التصحيح فعلياً
 *   php artisan app:backfill-cash-refunds --dry-run → معاينة بدون حفظ
 */
class BackfillCashRefunds extends Command
{
    protected $signature = 'app:backfill-cash-refunds
                            {--dry-run : معاينة التصحيحات بدون حفظ أي تغييرات}';

    protected $description = 'تصحيح لمرة واحدة: يضبط cash_refunded في invoice_returns القديمة ويصحح amount_paid في الفواتير المتأثرة';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 وضع المعاينة (dry-run) — لن يتم حفظ أي تغييرات.');
        } else {
            $this->info('🚀 وضع التنفيذ الفعلي — ستُحفظ جميع التصحيحات في قاعدة البيانات.');
        }

        $this->newLine();

        // متغيرات ملخص النهاية
        $totalInvoicesCorrected = 0;
        $totalAmountAdjusted    = 0.0;
        $totalCashRefundsFilled = 0.0;

        // نلف كل العمليات في transaction واحدة
        // لو كانت dry-run، نعمل rollback في الآخر بدل commit
        DB::beginTransaction();

        try {
            // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
            // الخطوة ١: جلب الفواتير اللي amount_paid فيها أكبر من total
            // ده بيحصل لما مرتجع اتعمل قديماً وخصم من total الفاتورة
            // لكن ما عدّلش amount_paid، فبقى amount_paid > total.
            // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
            $invoices = Invoice::query()
                ->whereColumn('amount_paid', '>', 'total')
                ->with([
                    // نجيب المرتجعات المؤهلة: cash_refunded=0 و total_refund>0 (الأقدم أولاً للتوزيع)
                    'returns' => fn ($q) => $q
                        ->where('cash_refunded', 0)
                        ->where('total_refund', '>', 0)
                        ->orderBy('created_at', 'asc'),
                ])
                ->lockForUpdate()
                ->get();

            if ($invoices->isEmpty()) {
                $this->info('✅ لا توجد فواتير تحتاج تصحيح. كل البيانات سليمة.');
                DB::rollBack();
                return self::SUCCESS;
            }

            $this->info("🔎 وُجد {$invoices->count()} فاتورة تحتاج تصحيح:");
            $this->newLine();

            foreach ($invoices as $invoice) {

                // ── حساب الزيادة في amount_paid عن total الجديد ──
                $oldAmountPaid   = round((float) $invoice->amount_paid, 2);
                $invoiceTotal    = round((float) $invoice->total, 2);
                $excessRemaining = round($oldAmountPaid - $invoiceTotal, 2);

                if ($excessRemaining <= 0) {
                    // تجنّب أي حالة edge بعد التقريب
                    continue;
                }

                $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->line("  📄 فاتورة: <fg=yellow>{$invoice->invoice_number}</fg=yellow>");
                $this->line("     amount_paid القديم : {$oldAmountPaid} ج");
                $this->line("     total الفاتورة     : {$invoiceTotal} ج");
                $this->line("     الزيادة المكتشفة  : {$excessRemaining} ج");

                // ── تصحيح amount_paid في الفاتورة — نضبطه على total بالضبط ──
                $newAmountPaid = $invoiceTotal;

                if (! $isDryRun) {
                    $invoice->update(['amount_paid' => $newAmountPaid]);
                }

                $this->line("     ✏️  amount_paid الجديد : {$newAmountPaid} ج" .
                    ($isDryRun ? ' (لم يُحفظ - dry-run)' : ' ✅ حُفظ'));

                // ── توزيع الزيادة على المرتجعات المؤهلة (الأقدم أولاً) ──
                $returns = $invoice->returns; // محمّلة مسبقاً، مرتّبة تصاعدياً

                if ($returns->isEmpty()) {
                    $this->warn('     ⚠️  لا توجد مرتجعات مؤهلة (cash_refunded=0 وtotal_refund>0) لتوزيع الزيادة.');
                } else {
                    foreach ($returns as $return) {
                        if ($excessRemaining <= 0) {
                            break;
                        }

                        // الحد الأقصى المسموح لهذا المرتجع = total_refund (لا نتجاوزه أبداً)
                        $returnTotalRefund = round((float) $return->total_refund, 2);
                        $cashToAssign      = round(min($excessRemaining, $returnTotalRefund), 2);

                        if ($cashToAssign <= 0) {
                            continue;
                        }

                        if (! $isDryRun) {
                            $return->update(['cash_refunded' => $cashToAssign]);
                        }

                        $this->line(
                            "     🔁  مرتجع #{$return->id}" .
                            " | cash_refunded = {$cashToAssign} ج" .
                            " (total_refund = {$returnTotalRefund} ج)" .
                            ($isDryRun ? ' (لم يُحفظ - dry-run)' : ' ✅ حُفظ')
                        );

                        $totalCashRefundsFilled += $cashToAssign;
                        $excessRemaining = round($excessRemaining - $cashToAssign, 2);
                    }

                    if ($excessRemaining > 0) {
                        // زيادة لم تُوزَّع: مجموع total_refund المتاح أقل من الزيادة
                        $this->warn("     ⚠️  تبقّى {$excessRemaining} ج لم تُوزَّع (total_refund المتاح لا يكفي).");
                    }
                }

                $totalInvoicesCorrected++;
                $totalAmountAdjusted += round($oldAmountPaid - $newAmountPaid, 2);
            }

            // ── ملخص نهائي ──
            $this->newLine();
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📊 ملخص التصحيح' . ($isDryRun ? ' (dry-run — لم يُحفظ شيء)' : ''));
            $this->table(
                ['البيان', 'القيمة'],
                [
                    ['الفواتير التي صُحِّحت',         $totalInvoicesCorrected],
                    ['إجمالي التعديل في amount_paid',  number_format($totalAmountAdjusted, 2) . ' ج'],
                    ['إجمالي cash_refunded المُعبَّأ', number_format($totalCashRefundsFilled, 2) . ' ج'],
                ]
            );

            if ($isDryRun) {
                // dry-run: دائماً rollback — لا نحفظ أي شيء
                DB::rollBack();
                $this->warn('↩️  Dry-run: تم التراجع عن جميع التغييرات. لا شيء حُفظ في قاعدة البيانات.');
            } else {
                DB::commit();
                $this->info('✅ تم حفظ جميع التصحيحات بنجاح في قاعدة البيانات.');
            }

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('❌ حدث خطأ — تم التراجع عن جميع التغييرات:');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
