<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>فاتورة رقم {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: sans-serif;
            direction: rtl;
            text-align: right;
            margin: 0;
            padding: 10px 20px;
            color: #333;
            font-size: 13px;
        }

        div, table, th, td {
            direction: rtl;
            text-align: right;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 5px;
        }

        .clinic-name {
            font-size: 20px;
            font-weight: bold;
            color: #0056b3;
            margin: 0;
        }

        .clinic-sub {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }

        .invoice-details {
            width: 100%;
            margin-bottom: 10px;
        }

        .invoice-details td {
            padding: 3px 0;
            vertical-align: top;
        }

        .invoice-details .label {
            width: 15%;
            font-weight: bold;
            color: #555;
        }

        .invoice-details .value {
            width: 35%;
        }

        /* Watermark للفواتير الملغية */
        .watermark {
            position: absolute;
            top: 30%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(255, 0, 0, 0.15);
            font-weight: bold;
            z-index: -1;
            white-space: nowrap;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.items th,
        table.items td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: right;
        }

        table.items th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }

        table.items td.center,
        table.items th.center {
            text-align: center;
        }

        .total-row {
            font-weight: bold;
            background-color: #e9ecef;
        }

        .total-row td {
            font-size: 14px;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
    </style>
</head>
<body>

    @if ($invoice->isCancelled())
        <div class="watermark">فاتورة ملغية</div>
    @endif

    <div class="header">
        {{-- اسم العيادة الديناميكي من الإعدادات --}}
        <h1 class="clinic-name">{{ \App\Models\Setting::get('clinic_name', 'عيادة بيطرية') }}</h1>
        <div class="clinic-sub">رعاية متكاملة للحيوانات الأليفة</div>
    </div>

    <table class="invoice-details">
        <tr>
            <td class="label">رقم الفاتورة:</td>
            <td class="value"><strong>{{ $invoice->invoice_number }}</strong></td>
            <td class="label">تاريخ الفاتورة:</td>
            <td class="value">{{ $invoice->created_at->format('Y-m-d H:i') }}</td>
        </tr>
        <tr>
            <td class="label">اسم العميل:</td>
            <td class="value" colspan="3">{{ $invoice->customer_name }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>المنتج / الخدمة</th>
                <th class="center" style="width: 15%;">الكمية</th>
                <th class="center" style="width: 20%;">سعر الوحدة</th>
                <th class="center" style="width: 20%;">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>
                        {{ $item->product->name }}
                        <br>
                        <span style="font-size: 10px; color: #666;">
                            {{ ['product' => 'منتج', 'service' => 'خدمة', 'vaccination' => 'تطعيم'][$item->product->type] ?? $item->product->type }}
                        </span>
                    </td>
                    <td class="center">{{ number_format($item->quantity, 2) }}</td>
                    <td class="center">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="center">{{ number_format($item->line_total, 2) }} ج.م</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3" style="text-align: left;">الإجمالي الكلي:</td>
                <td class="center" style="color: #198754;">{{ number_format($invoice->total, 2) }} ج.م</td>
            </tr>
        </tbody>
    </table>

    @if ($invoice->vaccinations->where('next_dose_date', '!=', null)->count())
        <div style="margin-bottom: 8px; padding: 6px 10px; border: 1px dashed #333; border-radius: 4px;">
            <strong style="font-size: 12px; color: #333;">مواعيد الجرعات القادمة:</strong>
            @foreach ($invoice->vaccinations->whereNotNull('next_dose_date') as $vaccination)
                <div style="font-size: 12px; margin-top: 3px; padding-right: 10px; color: #333;">
                    • {{ $vaccination->product->name }} — <strong>{{ \Carbon\Carbon::parse($vaccination->next_dose_date)->format('Y-m-d') }}</strong>
                </div>
            @endforeach
        </div>
    @endif

    {{-- تذييل الفاتورة باسم العيادة الديناميكي --}}
    <div class="footer">
        نشكركم على ثقتكم في {{ \App\Models\Setting::get('clinic_name', 'عيادة بيطرية') }}
    </div>

</body>
</html>
