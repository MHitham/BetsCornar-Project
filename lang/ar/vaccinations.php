<?php

return [
    'title' => 'التطعيمات',
    'fields' => [
        'customer_name' => 'اسم العميل',
        'customer_phone' => 'رقم الهاتف',
        'animal_type' => 'نوع الحيوان',
        'vaccine_name' => 'اسم التطعيم',
        'vaccination_date' => 'تاريخ التطعيم',
        'next_dose_date' => 'موعد الجرعة القادمة',
        'invoice_reference' => 'رقم الفاتورة',
        'whatsapp' => 'واتساب',
    ],
    'filters' => [
        'all' => 'الكل',
        'upcoming' => 'الجرعات القادمة',
        'past' => 'السابقة',
        'search_placeholder' => 'ابحث باسم العميل أو رقم الهاتف...',
    ],
    'actions' => [
        'search' => 'بحث',
        'clear' => 'مسح البحث',
    ],
    // تم الإضافة: نصوص التحديد الجماعي ونسخ الأرقام وتصدير التطعيمات
    'bulk' => [
        'selected_count' => 'عنصر محدد',
        'copy_phones' => 'نسخ الأرقام',
        'export_excel' => 'تصدير Excel',
        'clear_selection' => 'إلغاء التحديد',
        'select_page' => 'تحديد كل عناصر الصفحة',
        'select_all_results' => 'اختيار كل النتائج (:count سجل)',
        'page_selected_prefix' => 'تم اختيار جميع السجلات في هذه الصفحة.',
        'page_selected_suffix' => 'سجلاً في الصفحة الحالية.',
        'all_results_selected_prefix' => 'تم اختيار كل النتائج المطابقة وعددها',
        'all_results_selected_suffix' => 'سجلاً.',
        'copy_modal_phones' => 'نسخ كل الأرقام',
        'no_selection' => 'يرجى اختيار سجل واحد على الأقل.',
        'no_modal_phones' => 'لا توجد أرقام متاحة للنسخ في هذه القائمة.',
        'copy_success' => 'تم نسخ الأرقام بنجاح.',
        'copy_failed' => 'تعذر نسخ الأرقام. يرجى المحاولة مرة أخرى.',
    ],

    'messages' => [
        'no_vaccinations_found' => 'لم يتم العثور على أي تطعيمات مسجلة.',
    ],
];
