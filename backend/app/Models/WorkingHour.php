<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkingHour extends Model
{
    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'day_of_week' => 'integer'
    ];

    public static function getForDay(int $dayOfWeek): ?self
    {
        return self::where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();
    }

    public function breakPeriods(): HasMany
    {
        return $this->hasMany(BreakPeriod::class);
    }
}
