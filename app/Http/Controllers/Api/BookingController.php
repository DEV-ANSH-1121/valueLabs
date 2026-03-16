<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Jobs\SendBookingConfirmationJob;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $service = Service::findOrFail($request->service_id);
        $slotStart = Carbon::createFromFormat('Y-m-d H:i', $request->slot_start);
        $slotEnd = $slotStart->copy()->addMinutes($service->duration_minutes);

        // Concurrency-safe booking using a pessimistic lock
        $booking = DB::transaction(function () use ($request, $service, $slotStart, $slotEnd) {
            $currentCount = Booking::where('service_id', $service->id)
                ->where('slot_start', $slotStart)
                ->lockForUpdate()
                ->count();

            if ($currentCount >= $service->max_capacity) {
                abort(Response::HTTP_CONFLICT, 'This slot is no longer available.');
            }

            return Booking::create([
                'service_id' => $service->id,
                'name' => $request->name,
                'email' => $request->email,
                'slot_start' => $slotStart,
                'slot_end' => $slotEnd,
            ]);
        });

        SendBookingConfirmationJob::dispatch($booking);

        return response()->json([
            'message' => 'Booking confirmed.',
            'data' => [
                'id' => $booking->id,
                'service' => $service->name,
                'slot' => $booking->formatted_slot_time,
            ],
        ], Response::HTTP_CREATED);
    }
}
