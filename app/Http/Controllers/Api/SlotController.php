<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SlotController extends Controller
{
    public function __construct(private readonly SlotService $slotService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = Service::with(['openingHours', 'breakTimes'])
            ->findOrFail($request->service_id);

        $date = Carbon::createFromFormat('Y-m-d', $request->date);

        $slots = $this->slotService->availableSlots($service, $date);

        return response()->json([
            'data' => $slots->map(fn (Carbon $s) => $s->format('H:i')),
        ]);
    }
}
