<?php

return [
    'title'        => 'العملاء',
    'create_title' => 'تسجيل زيارة عميل',
    'edit_title'   => 'تعديل بيانات العميل',

    'fields' => [
        'name'              => 'اسم صاحب الحيوان',
        'phone'             => 'رقم الهاتف',
        'address'           => 'العنوان',
        'animal_type'       => 'نوع الحيوان',
        'notes'             => 'ملاحظات',
        'last_vaccination'  => 'آخر تطعيم',
    ],

    'animal_types' => [
        'قط'    => 'قط',
        'كلب'   => 'كلب',
        'طائر'  => 'طائر',
        'أرنب'  => 'أرنب',
        'أخرى'  => 'أخرى',
    ],

    'visit' => [
        'title'                  => 'تفاصيل الزيارة',
        'consultation'           => 'الكشف',
        'consultation_price'     => 'سعر الكشف',
        // ─── التطعيمات المتعددة ─────────────────────────────────
        'has_vaccination'        => 'يوجد تطعيم في هذه الزيارة؟',
        'vaccinations_section'   => 'التطعيمات',
        'vaccine_product'        => 'نوع اللقاح',
        'vaccine_quantity'       => 'الكمية',
        'next_dose_date'         => 'موعد الجرعة القادمة',
        'add_vaccination'        => 'إضافة تطعيم',
        'remove_vaccination'     => 'حذف التطعيم',
        // ─── المنتجات/الخدمات الإضافية ─────────────────────────
        'additional_items'       => 'منتجات / خدمات إضافية',
        'add_item'               => 'إضافة عنصر',
        'remove_item'            => 'حذف',
        'product_service'        => 'المنتج / الخدمة',
        'quantity'               => 'الكمية',
        'unit_price'             => 'سعر الوحدة',
        'line_total'             => 'الإجمالي',
        'grand_total'            => 'المجموع الكلي',
        'select_vaccine'         => 'اختر اللقاح',
        'select_product_service' => 'اختر منتجًا أو خدمة',
        'vaccination_date'       => 'تاريخ التطعيم',
    ],

    'actions' => [
        'add'        => 'إضافة زيارة',
        'save'       => 'حفظ الزيارة',
        'edit'       => 'تعديل',
        'delete'     => 'حذف',
        'search'     => 'بحث',
        'clear'      => 'إعادة تعيين',
        'new_visit'  => 'زيارة جديدة',
        'view'       => 'عرض',
    ],

    'filters' => [
        'search_placeholder' => 'ابحث بالاسم أو الهاتف...',
    ],

    'messages' => [
        'created'             => 'تم تسجيل الزيارة وحفظ الفاتورة بنجاح.',
        'updated'             => 'تم تحديث بيانات العميل بنجاح.',
        'deleted'             => 'تم حذف العميل بنجاح.',
        'no_results'          => 'لا يوجد عملاء مطابقون للبحث.',
        'no_customers'        => 'لا يوجد عملاء حتى الآن.',
        'confirm_delete'      => 'هل أنت متأكد من حذف هذا العميل؟',
        'phone_normalized'    => 'تم تطبيع رقم الهاتف تلقائيًا.',
        'customer_reused'     => 'تم استخدام بيانات العميل الموجود تلقائيًا.',
        'insufficient_stock'  => 'الكمية المطلوبة من اللقاح غير متوفرة (المخزون الصالح غير كافٍ).',
        'select_consultation' => 'منتج الكشف غير موجود. يرجى الإضافة من قائمة المنتجات.',
    ],
];
