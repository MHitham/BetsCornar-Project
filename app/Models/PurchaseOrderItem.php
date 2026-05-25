<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id', 'quantity',
        'purchase_price_per_unit', 'selling_price_per_unit',
        'expiry_date', 'batch_id',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:2',
        'purchase_price_per_unit' => 'decimal:2',
        'selling_price_per_unit' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(VaccineBatch::class, 'batch_id');
    }

    public function getLineTotalAttribute(): float
    {
        return (float) $this->quantity * (float) $this->purchase_price_per_unit;
    }
}
