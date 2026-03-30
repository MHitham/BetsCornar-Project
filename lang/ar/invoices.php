<?php

return [
    'title' => 'الفواتير',
    'create_title' => 'بيع سريع',
    'show_title' => 'تفاصيل الفاتورة',

    'fields' => [
        'invoice_number' => 'رقم الفاتورة',
        'customer_name' => 'اسم العميل',
        'customer_phone' => 'هاتف العميل (اختياري)',
        'source' => 'المصدر',
        'total' => 'المجموع',
        'status' => 'الحالة',
        'date' => 'التاريخ',
        'items' => 'البنود',
        'product' => 'المنتج / الخدمة',
        'quantity' => 'الكمية',
        'unit_price' => 'سعر الوحدة',
        'line_total' => 'إجمالي السعر',
        'grand_total' => 'المجموع الكلي',
    ],

    'sources' => [
        'customer' => 'زيارة عميل',
        'quick_sale' => 'بيع سريع',
        'all' => 'الكل',
    ],

    'statuses' => [
        'confirmed' => 'مؤكدة',
        'cancelled' => 'ملغاة',
    ],

    'actions' => [
        'add' => 'بيع سريع',
        'save' => 'حفظ الفاتورة',
        'view' => 'عرض',
        'search' => 'بحث',
        'clear' => 'إعادة تعيين',
        'add_item' => 'إضافة بند',
        'remove_item' => 'حذف',
        'print' => 'طباعة',
    ],

    'filters' => [
        'search_placeholder' => 'ابحث برقم الفاتورة أو اسم العميل...',
        'all_sources' => 'كل المصادر',
        'date_from' => 'من تاريخ',
        'date_to' => 'إلى تاريخ',
    ],

    'messages' => [
        'created' => 'تم حفظ الفاتورة بنجاح.',
        'no_results' => 'لا توجد فواتير مطابقة.',
        'no_invoices' => 'لا توجد فواتير حتى الآن.',
        'insufficient_stock' => 'الكمية المطلوبة من اللقاح غير متوفرة (المخزون الصالح غير كافٍ).',
        'at_least_one_item' => 'يجب إضافة بند واحد على الأقل.',
        'select_product' => 'اختر منتجًا أو خدمة',
        'walk_in_customer' => 'عميل نقدي',
        'no_delete' => 'لا يمكن حذف الفاتورة.',
        // رسالة نجاح عند إلغاء الفاتورة بنجاح
        'cancelled' => 'تم إلغاء الفاتورة بنجاح',
        // رسالة خطأ إذا كانت الفاتورة ملغية مسبقاً
        'already_cancelled' => 'هذه الفاتورة ملغية بالفعل',
    ],
];
