<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePayment extends Model
{
    protected $fillable = [
        'purchase_order_id', 'amount', 'is_from_clinic_cash', 'notes', 'paid_at', 'created_by',
    ];

    protected $casts = [
        'paid_at'              => 'date',
        'amount'               => 'decimal:2',
        'is_from_clinic_cash'  => 'boolean',
    ];

    /** Scope: only payments that came from the clinic cash drawer. */
    public function scopeClinicCash(Builder $query): Builder
    {
        return $query->where('is_from_clinic_cash', true);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
