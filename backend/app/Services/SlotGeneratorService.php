<?php

namespace App\Services;

use App\Models\Service;
use App\Models\WorkingHour;
use App\Models\Booking;
use Carbon\Carbon;

class SlotGeneratorService
{
    public function getAvailableSlots(string $date, int $serviceId): array
    {
        $service = \App\Models\Service::findOrFail($serviceId);
        // Use format('w') to get 0=Sunday, 1=Monday, etc. (matches database schema)
        $dayOfWeek = (int)Carbon::parse($date)->format('w');

        // Check if working day
        $workingHour = WorkingHour::getForDay($dayOfWeek);

        if (!$workingHour) {
            return [];
        }

        // Load active break periods
        $breakPeriods = $workingHour->breakPeriods()
            ->where('is_active', true)
            ->get();

        // Generate all possible slots (excluding break periods)
        // Normalize times to H:i format (remove seconds if present)
        $startTime = $this->normalizeTime($workingHour->start_time);
        $endTime = $this->normalizeTime($workingHour->end_time);

        $allSlots = $this->generateSlots(
            $date,
            $startTime,
            $endTime,
            $service->duration_minutes,
            $breakPeriods
        );

        // Filter out past slots if today
        if (Carbon::parse($date)->isToday()) {
            $allSlots = $this->filterPastSlots($allSlots, $date);
        }

        // Get existing bookings
        $bookings = Booking::where('booking_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        // Filter available slots
        return $this->filterAvailableSlots($allSlots, $bookings);
    }

    private function generateSlots(string $date, string $startTime, string $endTime, int $durationMinutes, $breakPeriods = null): array
    {
        $slots = [];
        $current = Carbon::parse("$date $startTime");
        $end = Carbon::parse("$date $endTime");

        while ($current->copy()->addMinutes($durationMinutes)->lte($end)) {
            $slotStart = $current->format('H:i');
            $slotEnd = $current->copy()->addMinutes($durationMinutes)->format('H:i');

            // Skip slot if it overlaps with any break period
            if ($breakPeriods && $this->isSlotDuringBreak($date, $slotStart, $slotEnd, $breakPeriods)) {
                $current->addMinutes($durationMinutes);
                continue;
            }

            $slots[] = [
                'start_time' => $slotStart,
                'end_time' => $slotEnd,
            ];

            $current->addMinutes($durationMinutes);
        }

        return $slots;
    }

    private function filterPastSlots(array $slots, string $date): array
    {
        $now = Carbon::now();

        return array_filter($slots, function ($slot) use ($now, $date) {
            $slotTime = Carbon::parse("$date {$slot['start_time']}");
            return $slotTime->isAfter($now);
        });
    }

    private function filterAvailableSlots(array $slots, $bookings): array
    {
        return array_values(array_filter($slots, function ($slot) use ($bookings) {
            foreach ($bookings as $booking) {
                // Normalize booking times to H:i format
                $bookingStart = $this->normalizeTime($booking->start_time);
                $bookingEnd = $this->normalizeTime($booking->end_time);

                if ($this->timesOverlap(
                    $slot['start_time'],
                    $slot['end_time'],
                    $bookingStart,
                    $bookingEnd
                )) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function timesOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return ($start1 < $end2) && ($end1 > $start2);
    }

    /**
     * Check if a date has working hours configured
     *
     * @param string $date The date to check
     * @return bool True if the date has working hours
     */
    public function hasWorkingHours(string $date): bool
    {
        // Use format('w') to get 0=Sunday, 1=Monday, etc. (matches database schema)
        $dayOfWeek = (int)Carbon::parse($date)->format('w');

        // Check if working day
        $workingHour = WorkingHour::getForDay($dayOfWeek);

        return $workingHour !== null;
    }

    /**
     * Get all active working days (day_of_week values)
     *
     * @return array Array of day_of_week integers (0=Sunday, 1=Monday, etc.)
     */
    public function getActiveWorkingDays(): array
    {
        return WorkingHour::where('is_active', true)
            ->pluck('day_of_week')
            ->toArray();
    }

    /**
     * Normalize time string to H:i format (remove seconds if present)
     *
     * @param string|null $time Time string (may be H:i or H:i:s format)
     * @return string Time in H:i format
     */
    private function normalizeTime(?string $time): string
    {
        if (empty($time)) {
            return '00:00';
        }

        $time = trim((string)$time);

        // If time includes seconds (H:i:s format like "09:00:00"), remove them
        if (strlen($time) >= 8 && substr_count($time, ':') >= 2) {
            return substr($time, 0, 5);
        }

        // If already in H:i format, return as-is
        if (strlen($time) === 5 && substr_count($time, ':') === 1) {
            return $time;
        }

        // Try to parse with Carbon and format
        try {
            $carbon = Carbon::parse($time);
            return $carbon->format('H:i');
        } catch (\Exception $e) {
            // Fallback: return as-is if parsing fails
            return $time;
        }
    }

    /**
     * Check if a slot overlaps with any break period.
     *
     * @param string $date The date for the slot
     * @param string $slotStart Slot start time (H:i format)
     * @param string $slotEnd Slot end time (H:i format)
     * @param \Illuminate\Database\Eloquent\Collection $breakPeriods Collection of break periods
     * @return bool True if slot overlaps with any break period
     */
    private function isSlotDuringBreak(string $date, string $slotStart, string $slotEnd, $breakPeriods): bool
    {
        $slotStartCarbon = Carbon::parse("$date $slotStart");
        $slotEndCarbon = Carbon::parse("$date $slotEnd");

        foreach ($breakPeriods as $breakPeriod) {
            // Normalize break period times to H:i format
            $breakStartTime = $this->normalizeTime($breakPeriod->start_time);
            $breakEndTime = $this->normalizeTime($breakPeriod->end_time);
            $breakStart = Carbon::parse("$date $breakStartTime");
            $breakEnd = Carbon::parse("$date $breakEndTime");

            // Check if slot overlaps with break: slot_start < break_end AND slot_end > break_start
            if ($slotStartCarbon->lt($breakEnd) && $slotEndCarbon->gt($breakStart)) {
                return true;
            }
        }

        return false;
    }
}
