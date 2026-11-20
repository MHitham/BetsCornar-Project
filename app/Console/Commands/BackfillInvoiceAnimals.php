<?php

namespace App\Console\Commands;

use App\Models\Animal;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BackfillInvoiceAnimals extends Command
{
    protected $signature = 'invoices:backfill-animals
        {--apply : حفظ الربط بعد المعاينة والتأكيد}
        {--invoice= : مراجعة فاتورة برقم السجل ID}';

    protected $description = 'معاينة وربط فواتير الزيارات القديمة بالحيوان الذي تشير إليه البيانات المحفوظة';

    private const RELATIONS = [
        'customer:id',
        'customer.animals:id,customer_id,name,is_active,created_at',
        'vaccinations:id,invoice_id,customer_id,animal_id',
    ];

    public function handle(): int
    {
        $invoiceOption = $this->option('invoice');
        $invoiceId = $invoiceOption === null && ! $this->input->hasParameterOption('--invoice', true)
            ? null
            : filter_var($invoiceOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($invoiceId === false) {
            $this->error('أدخل رقم سجل الفاتورة (ID) كعدد صحيح موجب.');

            return self::INVALID;
        }

        if (! Schema::hasColumns('invoices', ['animal_id', 'animal_name'])
            || ! Schema::hasColumn('vaccinations', 'animal_id') || ! Schema::hasTable('animals')) {
            $this->error('جداول ربط الحيوانات غير جاهزة. راجع الترحيلات المعلّقة باستخدام migrate:status.');

            return self::FAILURE;
        }

        $this->info('قاعدة البيانات: '.DB::connection()->getDatabaseName());
        $rows = [];
        $plan = [];
        $unmatched = 0;
        foreach ($this->unlinkedVisits()->when($invoiceId !== null, fn (Builder $query) => $query->whereKey($invoiceId))
            ->with(self::RELATIONS)->lazyById(250) as $invoice) {
            $candidate = $this->findCandidate($invoice);
            if ($candidate === null) {
                $unmatched++;

                continue;
            }

            $rows[] = [$invoice->id, $invoice->invoice_number, $invoice->customer_id, $candidate['animal']->name,
                $candidate['confirmed'] ? 'تطعيم مرتبط' : 'اسم مطابق فقط'];

            if ($candidate['confirmed'] || $invoiceId !== null) {
                $plan[$invoice->id] = [$candidate['animal']->id, $candidate['confirmed']];
            }
        }

        if ($rows !== []) {
            $this->table(['ID الفاتورة', 'رقم الفاتورة', 'ID العميل', 'الحيوان', 'الدليل'], $rows);
        }
        if ($invoiceId !== null && $rows === [] && $unmatched === 0) {
            $this->line('الرقم المحدد لا يخص فاتورة زيارة غير مرتبطة بحيوان.');
        }
        $this->line('فواتير جاهزة للربط في هذا التشغيل: '.count($plan));
        $this->line('فواتير بدون دليل كافٍ أو ببيانات متعارضة: '.$unmatched);
        if ($invoiceId === null && count($rows) > count($plan)) {
            $this->line('تطابقات الاسم فقط تحتاج مراجعة كل فاتورة على حدة باستخدام --invoice=ID.');
        }

        if (! $this->option('apply')) {
            $this->info('المعاينة فقط. لم تُحفظ أي تغييرات.');

            return self::SUCCESS;
        }

        if ($plan === []) {
            $this->info('لا توجد فواتير جاهزة للربط.');

            return self::SUCCESS;
        }

        if (! $this->confirm('بعد مراجعة الجدول وأخذ نسخة احتياطية، هل تؤكد ربط '.count($plan).' فاتورة؟')) {
            $this->info('تم الإلغاء. لم تُحفظ أي تغييرات.');

            return self::SUCCESS;
        }

        try {
            $this->savePlan($plan);
        } catch (RuntimeException $exception) {
            report($exception);
            $this->error('توقّف الربط بسبب تغيّر السجلات أو خطأ في قاعدة البيانات. لم تُحفظ التغييرات. اعرض المعاينة مرة أخرى.');

            return self::FAILURE;
        }

        $this->info('تم ربط '.count($plan).' فاتورة.');

        return self::SUCCESS;
    }

    private function unlinkedVisits(): Builder
    {
        return Invoice::query()->where('source', 'customer')->whereNotNull('customer_id')->whereNull('animal_id');
    }

    /** @return array{animal: Animal, confirmed: bool}|null */
    private function findCandidate(Invoice $invoice): ?array
    {
        if ($invoice->customer === null) {
            return null;
        }

        $animals = $invoice->customer->animals;
        $savedName = trim((string) $invoice->animal_name);
        $linkedVaccinations = $invoice->vaccinations->whereNotNull('animal_id');
        if ($linkedVaccinations->isNotEmpty()) {
            $animalIds = $linkedVaccinations->pluck('animal_id')->unique();
            if ($animalIds->count() !== 1 || $linkedVaccinations->contains(fn ($row) => $row->customer_id !== $invoice->customer_id)) {
                return null;
            }

            $animal = $animals->find($animalIds->first());
            if (! $animal?->is_active || ($savedName !== '' && trim($animal->name) !== $savedName)) {
                return null;
            }

            return ['animal' => $animal, 'confirmed' => true];
        }

        if ($savedName === '') {
            return null;
        }

        $matches = $animals->filter(fn (Animal $animal) => trim($animal->name) === $savedName);
        $animal = $matches->count() === 1 ? $matches->first() : null;
        // اسم واحد ممكن يتكرر بعد حذف حيوان. لا نقترح ملفًا اتعمل بعد تاريخ الفاتورة.
        if (! $animal?->is_active || $animal->created_at === null || $invoice->created_at === null
            || $animal->created_at->gt($invoice->created_at)) {
            return null;
        }

        return ['animal' => $animal, 'confirmed' => false];
    }

    private function savePlan(array $plan): void
    {
        DB::transaction(function () use ($plan) {
            foreach ($plan as $invoiceId => [$animalId, $confirmed]) {
                // اقفل الحيوان أولاً بنفس ترتيب عملية الحذف.
                $animal = Animal::query()->lockForUpdate()->find($animalId);
                $invoice = $this->unlinkedVisits()->lockForUpdate()->find($invoiceId);
                if ($animal === null || $invoice === null) {
                    throw new RuntimeException('The reviewed invoice or animal changed.');
                }

                $invoice->load(self::RELATIONS);
                $candidate = $this->findCandidate($invoice);
                if ($candidate === null || $candidate['animal']->id !== $animalId || $candidate['confirmed'] !== $confirmed) {
                    throw new RuntimeException('The reviewed animal evidence changed.');
                }

                DB::table('invoices')->where('id', $invoiceId)->update([
                    'animal_id' => $animalId,
                    'animal_name' => filled($invoice->animal_name) ? $invoice->animal_name : $animal->name,
                ]);
            }
        }, 3);
    }
}
