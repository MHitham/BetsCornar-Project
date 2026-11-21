<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CustomerDeletionService
{
    // امسح السجل الطبي وأخفِ الفواتير في معاملة واحدة دون المساس بالمخزون أو الدفعات.
    public function deleteCustomerWithHistory(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            // اقفل صف العميل قبل تعديل سجله الطبي لتحميل حالته الحالية داخل المعاملة.
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->getKey());

            // حذف الحيوان يفصل مرجعه من الفاتورة تلقائيًا بفضل قيد قاعدة البيانات.
            $locked->vaccinations()->delete();
            $locked->animals()->delete();

            // أخفِ كل الفواتير مع الاحتفاظ بمبالغها وبنودها وسجل دفعاتها.
            $locked->invoices()->toBase()->update(['is_hidden' => true]);

            // حرّر الرقم الفريد قبل الحذف المنطقي حتى يمكن تسجيله مرة أخرى.
            $locked->phone = 'deleted_'.$locked->id.'_'.$locked->phone;
            $locked->save();
            $locked->delete();

            // حدّث بيانات التطعيمات المخزّنة مؤقتًا بعد نجاح الحذف فقط.
            DB::afterCommit(function () {
                Cache::forget(dashboardKey('upcoming_vaccinations'));
                Cache::forget('dashboard.total_vaccinations');
                Cache::forget('dashboard.total_available_vaccinations_count');
            });
        }, 3);
    }
}
