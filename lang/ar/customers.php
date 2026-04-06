<?php

return [
    'title'        => 'العملاء',
    'create_title' => 'تسجيل زيارة عميل',
    'edit_title'   => 'تعديل بيانات العميل',

    'fields' => [
        'name'             => 'اسم صاحب الحيوان',
        'phone'            => 'رقم الهاتف',
        'address'          => 'العنوان',
        'animal_type'      => 'نوع الحيوان',
        'notes'            => 'ملاحظات',
        'last_vaccination' => 'آخر تطعيم',
    ],

    'animal_types' => [
        'قط'   => 'قط',
        'كلب'  => 'كلب',
        'طائر' => 'طائر',
        'أرنب' => 'أرنب',
        'أخرى' => 'أخرى',
    ],

    'visit' => [
        'title'                  => 'تفاصيل الزيارة',
        'consultation'           => 'الكشف',
        'consultation_price'     => 'سعر الكشف',
        // تم الإضافة: نصوص التطعيمات المتعددة
        'has_vaccination'        => 'يوجد تطعيم في هذه الزيارة؟',
        'vaccinations_section'   => 'التطعيمات',
        'vaccine_product'        => 'نوع اللقاح',
        'vaccine_quantity'       => 'الكمية',
        'next_dose_date'         => 'موعد الجرعة القادمة',
        'add_vaccination'        => 'إضافة تطعيم',
        'remove_vaccination'     => 'حذف التطعيم',
        // تم الإضافة: نصوص المنتجات والخدمات الإضافية
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
        'add'       => 'إضافة زيارة',
        'save'      => 'حفظ الزيارة',
        'edit'      => 'تعديل',
        'delete'    => 'حذف',
        'search'    => 'بحث',
        'clear'     => 'إعادة تعيين',
        'new_visit' => 'زيارة جديدة',
        'view'      => 'عرض',
    ],

    'filters' => [
        'search_placeholder' => 'ابحث بالاسم أو الهاتف...',
    ],

    // تم الإضافة: نصوص التحديد الجماعي ونسخ الأرقام وتصدير العملاء
    'bulk' => [
        'selected_count' => 'عنصر محدد',
        'copy_phones' => 'نسخ الأرقام',
        'export_excel' => 'تصدير Excel',
        'clear_selection' => 'إلغاء التحديد',
        'select_page' => 'تحديد كل عناصر الصفحة',
        'select_all_results' => 'اختيار كل النتائج (:count عميل)',
        'page_selected_prefix' => 'تم اختيار جميع العملاء في هذه الصفحة.',
        'page_selected_suffix' => 'عميلًا في الصفحة الحالية.',
        'all_results_selected_prefix' => 'تم اختيار كل النتائج المطابقة وعددها',
        'all_results_selected_suffix' => 'عميلًا.',
        'no_selection' => 'يرجى اختيار عميل واحد على الأقل.',
        'copy_success' => 'تم نسخ الأرقام بنجاح.',
        'copy_failed' => 'تعذر نسخ الأرقام. يرجى المحاولة مرة أخرى.',
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

    // تم الإضافة: نصوص صفحة السجل الطبي
    'timeline' => [
        'title' => 'السجل الطبي',
        'visit_date' => 'تاريخ الزيارة',
        'invoice_number' => 'رقم الفاتورة',
        'invoice_items' => 'بنود الفاتورة',
        'vaccinations' => 'التطعيمات في هذه الزيارة',
        'product_name' => 'المنتج/الخدمة',
        'type' => 'النوع',
        'quantity' => 'الكمية',
        'unit_price' => 'سعر الوحدة',
        'line_total' => 'الإجمالي',
        'vaccine_name' => 'التطعيم',
        'vaccination_date' => 'تاريخ التطعيم',
        'next_dose' => 'الموعد القادم',
        'status' => 'الحالة',
        'total' => 'إجمالي الفاتورة',
        'no_visits' => 'لا توجد زيارات سابقة لهذا العميل.',
        'back_to_list' => 'العودة للعملاء',
        'new_visit' => 'زيارة جديدة',
        'total_visits' => 'إجمالي الزيارات',
        'status_overdue' => 'متأخر',
        'status_soon' => 'قريب',
        'status_ok' => 'قادم',
        'status_done' => 'مكتمل',
    ],

    'types' => [
        'product' => 'منتج',
        'service' => 'خدمة',
        'vaccination' => 'تطعيم',
    ],
];