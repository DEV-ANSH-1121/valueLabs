<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class BookingOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $today = Carbon::today();

        $todayBookings = Booking::whereDate('slot_start', $today)->count();

        $nextBooking = Booking::where('slot_start', '>=', now())
            ->orderBy('slot_start')
            ->with('service')
            ->first();

        $todayRevenue = Booking::whereDate('slot_start', $today)
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->sum('services.price');

        return [
            Stat::make('Today\'s Bookings', $todayBookings)
                ->description('Appointments scheduled today')
                ->icon('heroicon-o-calendar-days')
                ->color('primary'),

            Stat::make('Next Booking', $nextBooking
                ? $nextBooking->slot_start->format('M d, H:i') . ' — ' . $nextBooking->service?->name
                : 'None')
                ->description('Upcoming appointment')
                ->icon('heroicon-o-clock')
                ->color('info'),

            Stat::make('Today\'s Revenue', '$' . number_format((float) $todayRevenue, 2))
                ->description('Total revenue for today')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
        ];
    }
}
