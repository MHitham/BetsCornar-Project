<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * أمر قراءة فقط (READ-ONLY) يكشف بنود الفاتورة التي يختلف فيها مجموع كميات الـ pivot عن كمية البند الحالية.
 * شغّله على قاعدة بيانات العيادة بـ: php artisan audit:vaccine-pivot
 */
class AuditVaccinePivot extends Command
{
    protected $signature = 'audit:vaccine-pivot
                            {--limit=100 : أقصى عدد نتائج}';

    protected $description = 'يسرد invoice_items التي يختلف فيها SUM(pivot.quantity) عن invoice_items.quantity [قراءة فقط]';

    public function handle(): int
    {
        // استعلام قراءة فقط — لا يُعدّل أي بيانات.
        $rows = DB::select('
            SELECT
                ii.id                              AS invoice_item_id,
                ii.invoice_id,
                ii.product_id,
                CAST(ii.quantity AS DECIMAL(10,2))             AS item_qty,
                CAST(COALESCE(SUM(iivb.quantity), 0) AS DECIMAL(10,2)) AS pivot_sum,
                CAST(ii.quantity - COALESCE(SUM(iivb.quantity), 0) AS DECIMAL(10,2)) AS diff
            FROM invoice_items ii
            LEFT JOIN invoice_item_vaccine_batches iivb ON iivb.invoice_item_id = ii.id
            GROUP BY ii.id, ii.invoice_id, ii.product_id, ii.quantity
            HAVING ABS(ii.quantity - COALESCE(SUM(iivb.quantity), 0)) > 0.001
            ORDER BY ABS(ii.quantity - COALESCE(SUM(iivb.quantity), 0)) DESC
            LIMIT ?
        ', [$this->option('limit')]);

        if (empty($rows)) {
            $this->info('✅ لا توجد تعارضات — كل بنود اللقاح متسقة مع الـ pivot.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('⚠️  وُجدت %d بنود بها تعارض في الـ pivot:', count($rows)));
        $this->table(
            ['invoice_item_id', 'invoice_id', 'product_id', 'item_qty', 'pivot_sum', 'diff'],
            array_map(fn ($r) => (array) $r, $rows)
        );

        $this->newLine();
        $this->line('💡 الـ diff = item_qty - pivot_sum. قيمة موجبة تعني أن الـ pivot أقل من الكمية الحالية (بيانات قديمة قبل إصلاح FIX-2).');

        return self::FAILURE;
    }
}
