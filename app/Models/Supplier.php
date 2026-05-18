<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'phone', 'address', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // علاقة: المورد عنده كتير فواتير شراء
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // حساب الرصيد المتبقي ديناميكياً (مش مخزن في DB)
    public function getBalanceAttribute(): float
    {
        return (float) $this->purchaseOrders()
            ->selectRaw('SUM(total_cost) - SUM(amount_paid) as balance')
            ->value('balance') ?? 0;
    }
}
