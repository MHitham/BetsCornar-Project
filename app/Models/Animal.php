<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Animal extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'name',
        'species',
        'breed',
        'age',
        'gender',
        'weight',
        'color',
        'notes',
    ];

    protected function casts(): array
    {
        // تحويل حالة الأليف إلى قيمة منطقية ثابتة في جميع الاستخدامات.
        return [
            'is_active' => 'boolean',
            'weight' => 'decimal:2',
        ];
    }

    // توفير scope موحد لاسترجاع الحيوانات النشطة فقط.
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
