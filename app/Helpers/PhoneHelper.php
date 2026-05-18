<?php
namespace App\Helpers;

class PhoneHelper
{
    // توحيد تنسيق رقم الهاتف المصري إلى الصيغة الدولية 20xxxxxxxxx
    public static function normalize(string $phone): string
    {
        // إزالة كل الرموز غير الرقمية
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // إزالة كود الدولة 20 لو موجود في الأول
        if (str_starts_with($phone, '20') && strlen($phone) === 12) {
            $phone = substr($phone, 2);
        }
        
        // إزالة الصفر الأول فقط لو الرقم يبدأ بـ 0
        if (str_starts_with($phone, '0') && strlen($phone) === 11) {
            $phone = substr($phone, 1);
        }
        
        // إضافة كود مصر في الأول
        return '20' . $phone;
    }
}
