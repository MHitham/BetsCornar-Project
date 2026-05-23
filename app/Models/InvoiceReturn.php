<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceReturn extends Model
{
    protected $fillable = [
        'invoice_id', 'reason', 'total_refund', 'created_by',
    ];

    protected $casts = [
        'total_refund' => 'decimal:2',
    ];

    // علاقة: المرتجع يخص فاتورة
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // علاقة: المرتجع يحتوي على بنود متعددة
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceReturnItem::class);
    }

    // علاقة: المرتجع سجله مستخدم
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
