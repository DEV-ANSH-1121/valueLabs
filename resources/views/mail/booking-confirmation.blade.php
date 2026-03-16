<x-mail::message>
# Booking Confirmed

Hi {{ $booking->name }},

Your appointment has been successfully booked.

**Service:** {{ $booking->service?->name }}
**When:** {{ $booking->formatted_slot_time }}
**Duration:** {{ $booking->service?->duration_minutes }} minutes

<x-mail::button :url="config('app.url')">
Visit Our Website
</x-mail::button>

Thank you for choosing us!
{{ config('app.name') }}
</x-mail::message>
