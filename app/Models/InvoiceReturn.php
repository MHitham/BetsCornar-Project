<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceReturn extends Model
{
    protected $fillable = [
        'invoice_id', 'reason', 'total_refund', 'cash_refunded', 'created_by',
    ];

    protected $casts = [
        'total_refund' => 'decimal:2',
        'cash_refunded' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceReturnItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
