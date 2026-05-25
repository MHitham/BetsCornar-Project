<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItemVaccineBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_item_id',
        'vaccine_batch_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function vaccineBatch(): BelongsTo
    {
        return $this->belongsTo(VaccineBatch::class);
    }
}
