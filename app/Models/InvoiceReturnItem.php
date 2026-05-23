<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReturnItem extends Model
{
    protected $fillable = [
        'invoice_return_id', 'invoice_item_id',
        'product_id', 'quantity_returned', 'unit_price', 'line_total',
    ];

    protected $casts = [
        'quantity_returned' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    // علاقة: بند المرتجع يخص سجل مرتجع
    public function invoiceReturn(): BelongsTo
    {
        return $this->belongsTo(InvoiceReturn::class);
    }

    // علاقة: بند المرتجع مرتبط ببند الفاتورة الأصلية
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    // علاقة: بند المرتجع مرتبط بمنتج
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
