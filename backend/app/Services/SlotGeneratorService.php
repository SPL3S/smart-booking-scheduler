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
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        // Check if working day
        $workingHour = WorkingHour::getForDay($dayOfWeek);

        if (!$workingHour) {
            return [];
        }

        // Generate all possible slots
        $allSlots = $this->generateSlots(
            $date,
            $workingHour->start_time,
            $workingHour->end_time,
            $service->duration_minutes
        );

        // Filter out past slots if today
        if (Carbon::parse($date)->isToday()) {
            $allSlots = $this->filterPastSlots($allSlots);
        }

        // Get existing bookings
        $bookings = Booking::where('booking_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        // Filter available slots
        return $this->filterAvailableSlots($allSlots, $bookings);
    }

    private function generateSlots(string $date, string $startTime, string $endTime, int $durationMinutes): array
    {
        $slots = [];
        $current = Carbon::parse("$date $startTime");
        $end = Carbon::parse("$date $endTime");

        while ($current->copy()->addMinutes($durationMinutes)->lte($end)) {
            $slotStart = $current->format('H:i');
            $slotEnd = $current->copy()->addMinutes($durationMinutes)->format('H:i');

            $slots[] = [
                'start_time' => $slotStart,
                'end_time' => $slotEnd,
            ];

            $current->addMinutes($durationMinutes);
        }

        return $slots;
    }

    private function filterPastSlots(array $slots): array
    {
        $now = Carbon::now();

        return array_filter($slots, function ($slot) use ($now) {
            $slotTime = Carbon::parse($slot['start_time']);
            return $slotTime->isAfter($now);
        });
    }

    private function filterAvailableSlots(array $slots, $bookings): array
    {
        return array_values(array_filter($slots, function ($slot) use ($bookings) {
            foreach ($bookings as $booking) {
                if ($this->timesOverlap(
                    $slot['start_time'],
                    $slot['end_time'],
                    $booking->start_time,
                    $booking->end_time
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
}
