<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'customer_name',
        'source',
        'total',
        'status',
        'cancellation_reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }
    // ── Relationships ────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

     
    // ── Scopes ───────────────────────────────────────────────────────────
 
    /** الفواتير المؤكدة فقط (تُستخدم في الإيرادات والإحصائيات) */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }
 
    /** الفواتير الملغية فقط */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
 
    // ── Helpers ──────────────────────────────────────────────────────────
 
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
 
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
 
