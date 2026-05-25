<?php

return [
    'title' => 'التطعيمات',
    'create_title' => 'إضافة تطعيم',
    'edit_title' => 'تعديل تطعيم',

    'fields' => [
        'product' => 'التطعيم',
        'batch_code' => 'كود التطعيم',
        'received_date' => 'تاريخ الاستلام',
        'expiry_date' => 'تاريخ الانتهاء',
        'quantity_received' => 'الكمية المستلمة',
        'quantity_remaining' => 'الكمية المتبقية',
        'status' => 'الحالة',
    ],

    'statuses' => [
        'usable' => 'صالحة',
        'expired' => 'منتهية',
        'expiring_soon' => 'تنتهي قريباً',
    ],

    'actions' => [
        'add' => 'إضافة تطعيم',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'save' => 'حفظ',
        'search' => 'بحث',
        'clear' => 'إعادة تعيين',
    ],

    'filters' => [
        'search_placeholder' => 'ابحث بكود التطعيم أو اسم التطعيم...',
        'all_vaccines' => 'كل التطعيمات',
    ],

    'messages' => [
        'created' => 'تمت إضافة التطعيم بنجاح.',
        'updated' => 'تم تحديث التطعيم بنجاح.',
        'deleted' => 'تم حذف التطعيم بنجاح.',
        'delete_referenced_error' => 'لا يمكن حذف تطعيم له سجل استخدام سابق.',
        'confirm_delete' => 'هل أنت متأكد من حذف التطعيم؟',
        'no_results' => 'لا توجد تطعيمات مطابقة.',
        'insufficient_stock' => 'لا توجد كمية صالحة كافية من التطعيم (حسب تواريخ الانتهاء).',
        'remaining_hint' => 'يمكن ترك الكمية المتبقية فارغة لإدخال نفس الكمية المستلمة تلقائياً عند الإنشاء.',
        'remaining_exceeds_received' => 'الكمية المتبقية لا يمكن أن تكون أكبر من الكمية المستلمة.',
        'invalid_vaccine_product' => 'يجب اختيار منتج تطعيم فعّال مع تتبع مخزون.',
    ],
];
