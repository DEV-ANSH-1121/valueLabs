<?php

namespace App\Models;

use App\Scopes\ActiveServiceScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'duration_minutes',
        'price',
        'cleanup_minutes',
        'max_capacity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration_minutes' => 'integer',
        'cleanup_minutes' => 'integer',
        'max_capacity' => 'integer',
        'price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveServiceScope());
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class);
    }

    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    public function formattedDuration(): Attribute
    {
        return Attribute::make(
            get: fn () => sprintf('%d min', $this->duration_minutes),
        );
    }
}

