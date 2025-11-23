<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakPeriod extends Model
{
    protected $fillable = [
        'working_hour_id',
        'start_time',
        'end_time',
        'name',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function workingHour(): BelongsTo
    {
        return $this->belongsTo(WorkingHour::class);
    }
}

