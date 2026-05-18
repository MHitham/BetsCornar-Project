<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'supplier_id', 'order_number', 'total_cost',
        'amount_paid', 'notes', 'purchased_at',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'total_cost'   => 'decimal:2',
        'amount_paid'  => 'decimal:2',
    ];

    // علاقة: فاتورة الشراء تخص مورد (اختياري)
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // علاقة: فاتورة الشراء عندها كتير بنود
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // علاقة: فاتورة الشراء عندها كتير دفعات
    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    // حساب حالة الدفع ديناميكياً (accessor - مش مخزن في DB)
    public function getPaymentStatusAttribute(): string
    {
        if ($this->amount_paid <= 0) return 'unpaid';
        if ($this->amount_paid >= $this->total_cost) return 'paid';
        return 'partial';
    }

    // المبلغ المتبقي
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->total_cost - (float) $this->amount_paid);
    }
}
