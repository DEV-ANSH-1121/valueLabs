<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'name',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function formattedDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->date?->format('Y-m-d'),
        );
    }
}

