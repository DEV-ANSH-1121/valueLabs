<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\Holiday;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'slot_start' => ['required', 'date_format:Y-m-d H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $service = Service::withoutGlobalScopes()->find($this->service_id);
                $slotStart = Carbon::createFromFormat('Y-m-d H:i', $this->slot_start);
                $dayOfWeek = $slotStart->dayOfWeek;

                if (! $service->is_active) {
                    $validator->errors()->add('service_id', 'This service is currently inactive.');
                    return;
                }

                // Holiday check (global for all services)
                if (Holiday::whereDate('date', $slotStart->toDateString())->exists()) {
                    $validator->errors()->add('slot_start', 'The selected date is a holiday.');
                    return;
                }

                // Opening hours check
                $openingHour = $service->openingHours()
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                if (! $openingHour) {
                    $validator->errors()->add('slot_start', 'The service is closed on this day.');
                    return;
                }

                $dayDate = $slotStart->copy()->startOfDay();
                $opensAt = $dayDate->copy()->setTimeFromTimeString($openingHour->start_time);
                $closesAt = $dayDate->copy()->setTimeFromTimeString($openingHour->end_time);
                $slotEnd = $slotStart->copy()->addMinutes($service->duration_minutes);

                if ($slotStart->lt($opensAt) || $slotEnd->gt($closesAt)) {
                    $validator->errors()->add('slot_start', 'The slot falls outside opening hours.');
                    return;
                }

                // Break time check
                $duringBreak = $service->breakTimes()
                    ->where('day_of_week', $dayOfWeek)
                    ->get()
                    ->contains(function ($brk) use ($dayDate, $slotStart) {
                        $breakStart = $dayDate->copy()->setTimeFromTimeString($brk->start_time);
                        $breakEnd = $dayDate->copy()->setTimeFromTimeString($brk->end_time);

                        return $slotStart->gte($breakStart) && $slotStart->lt($breakEnd);
                    });

                if ($duringBreak) {
                    $validator->errors()->add('slot_start', 'The slot overlaps with a scheduled break.');
                    return;
                }

                // Capacity check (lightweight — the actual lock happens in the controller)
                $currentBookings = Booking::where('service_id', $service->id)
                    ->where('slot_start', $slotStart)
                    ->count();

                if ($currentBookings >= $service->max_capacity) {
                    $validator->errors()->add('slot_start', 'This slot is fully booked.');
                }
            },
        ];
    }
}
