<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'animal_type',
        'notes',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    // تم الإضافة: جلب آخر تطعيم للعميل بشكل آمن دون limit داخل eager loading
    public function latestVaccination(): HasOne
    {
        return $this->hasOne(Vaccination::class)->latestOfMany('vaccination_date');
    }

    public static function findOrCreateByPhone(string $normalizedPhone, array $attributes = []): self
    {
        return static::firstOrCreate(
            ['phone' => $normalizedPhone],
            $attributes
        );
    }
}
