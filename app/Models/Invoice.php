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
        'animal_id',
        'customer_name',
        'source',
        'total',
        'amount_paid',
        'status',
        // تم الإضافة: تتبع المستخدم الذي أنشأ الفاتورة
        'created_by',
        'cancellation_reason',
        'cancelled_at',
        'diagnosis',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }
    // ── Relationships ────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    // تم الإضافة: علاقة المستخدم الذي أنشأ الفاتورة
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_at');
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

    // حساب حالة الدفع ديناميكياً (accessor — مش مخزن في DB)
    public function getPaymentStatusAttribute(): string
    {
        if ((float) $this->amount_paid <= 0) {
            return 'unpaid';
        }
        if ((float) $this->amount_paid >= (float) $this->total) {
            return 'paid';
        }

        return 'partial';
    }

    // المبلغ المتبقي على العميل
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->total - (float) $this->amount_paid);
    }
}
