<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Holiday;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SlotService
{
    /**
     * Generate available time slots for a given service on a specific date.
     *
     * Algorithm:
     * 1. Check if the date falls on a holiday — if so, return empty.
     * 2. Fetch opening hours for the day-of-week — if none, return empty.
     * 3. Build all possible slots using duration + cleanup as the interval.
     * 4. Remove slots that overlap with any break window.
     * 5. Count existing bookings per slot and exclude those at max capacity.
     */
    public function availableSlots(Service $service, Carbon $date): Collection
    {
        if ($this->isHoliday($service, $date)) {
            return collect();
        }

        $openingHour = $service->openingHours
            ->firstWhere('day_of_week', $date->dayOfWeek);

        if (! $openingHour) {
            return collect();
        }

        $interval = $service->duration_minutes + $service->cleanup_minutes;
        $breaks = $service->breakTimes
            ->where('day_of_week', $date->dayOfWeek);

        $allSlots = $this->generateSlots($date, $openingHour, $interval, $service->duration_minutes);
        $slotsOutsideBreaks = $this->removeBreakSlots($allSlots, $breaks, $date);

        return $this->removeFullSlots($slotsOutsideBreaks, $service, $date);
    }

    private function isHoliday(Service $service, Carbon $date): bool
    {
        return Holiday::whereDate('date', $date->toDateString())->exists();
    }

    private function generateSlots(Carbon $date, $openingHour, int $interval, int $duration): Collection
    {
        $slots = collect();
        $start = $date->copy()->setTimeFromTimeString($openingHour->start_time);
        $end = $date->copy()->setTimeFromTimeString($openingHour->end_time);

        $cursor = $start->copy();

        while ($cursor->copy()->addMinutes($duration)->lte($end)) {
            $slots->push($cursor->copy());
            $cursor->addMinutes($interval);
        }

        return $slots;
    }

    private function removeBreakSlots(Collection $slots, $breaks, Carbon $date): Collection
    {
        if ($breaks->isEmpty()) {
            return $slots;
        }

        return $slots->reject(function (Carbon $slotStart) use ($breaks, $date) {
            foreach ($breaks as $brk) {
                $breakStart = $date->copy()->setTimeFromTimeString($brk->start_time);
                $breakEnd = $date->copy()->setTimeFromTimeString($brk->end_time);

                // Reject if slot overlaps with break window
                if ($slotStart->lt($breakEnd) && $slotStart->gte($breakStart)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function removeFullSlots(Collection $slots, Service $service, Carbon $date): Collection
    {
        if ($slots->isEmpty()) {
            return $slots;
        }

        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        $bookingCounts = Booking::where('service_id', $service->id)
            ->whereBetween('slot_start', [$dayStart, $dayEnd])
            ->selectRaw('slot_start, COUNT(*) as total')
            ->groupBy('slot_start')
            ->pluck('total', 'slot_start');

        return $slots->filter(function (Carbon $slotStart) use ($bookingCounts, $service) {
            $key = $slotStart->format('Y-m-d H:i:s');
            $booked = $bookingCounts[$key] ?? 0;

            return $booked < $service->max_capacity;
        })->values();
    }
}
