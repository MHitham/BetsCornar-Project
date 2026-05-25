<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'price',
        'average_cost',
        'quantity',
        'track_stock',
        'stock_status',
        'low_stock_threshold',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'decimal:2',
            'low_stock_threshold' => 'decimal:2',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function vaccineBatches(): HasMany
    {
        return $this->hasMany(VaccineBatch::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSelectable(Builder $query): Builder
    {
        return $query->active()->orderBy('name');
    }

    public function scopeVaccinations(Builder $query): Builder
    {
        return $query->where('type', 'vaccination');
    }

    public function scopeServices(Builder $query): Builder
    {
        return $query->where('type', 'service');
    }

    public function scopeProducts(Builder $query): Builder
    {
        return $query->where('type', 'product');
    }

    public function isReferenced(): bool
    {
        return $this->invoiceItems()->exists()
            || $this->vaccinations()->exists()
            || $this->vaccineBatches()->exists();
    }
}
