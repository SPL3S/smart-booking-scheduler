<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function getDayNameAttribute(): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $days[$this->day_of_week] ?? 'Unknown';
    }
}