<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'name',
        'email',
        'slot_start',
        'slot_end',
    ];

    protected $casts = [
        'slot_start' => 'datetime',
        'slot_end' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function formattedSlotTime(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->slot_start instanceof Carbon || ! $this->slot_end instanceof Carbon) {
                    return null;
                }

                return sprintf(
                    '%s–%s',
                    $this->slot_start->format('Y-m-d H:i'),
                    $this->slot_end->format('H:i'),
                );
            },
        );
    }

    public function bookingStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->slot_end instanceof Carbon) {
                    return 'unknown';
                }

                if ($this->slot_end->isPast()) {
                    return 'completed';
                }

                if ($this->slot_start?->isFuture()) {
                    return 'upcoming';
                }

                return 'ongoing';
            },
        );
    }
}

