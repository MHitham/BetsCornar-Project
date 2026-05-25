<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class)->latest('created_at');
    }

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
