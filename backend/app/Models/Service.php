<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'duration_minutes',
        'price'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer'
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}