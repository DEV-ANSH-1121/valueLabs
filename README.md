# Time Scheduling System — Laravel 12 + Filament v5

A production-quality appointment scheduling system with an admin panel (Filament) and a public API for slot availability and booking.

---

## Setup Instructions

```bash
# 1. Clone the repository
git clone <repo-url> && cd ValueLab

# 2. Install PHP dependencies
composer install

# 3. Copy environment file and generate app key
cp .env.example .env
php artisan key:generate

# 4. Configure your database in .env
# DB_DATABASE=value_lab
# DB_USERNAME=root
# DB_PASSWORD=your_password

# 5. Run migrations and seed the database
php artisan migrate:fresh --seed

# 6. Start the development server
php artisan serve

# 7. (Optional) Start the queue worker for async email jobs
php artisan queue:work
```

**Admin Panel:** Visit `/admin` and log in with:
- **Email:** `test@example.com`
- **Password:** `password`

---

## API Testing Examples

### 1. Get Available Slots

```bash
curl -X GET "http://localhost:8000/api/slots?service_id=1&date=2025-10-15" \
  -H "Accept: application/json"
```

**Response (200):**
```json
{
  "data": ["09:00", "09:40", "10:20", "11:00", "11:40", "13:00", "13:40", "14:20", "15:00", "15:40", "16:20"]
}
```

### 2. Create a Booking

```bash
curl -X POST "http://localhost:8000/api/bookings" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "service_id": 1,
    "slot_start": "2025-10-15 09:00"
  }'
```

**Response (201):**
```json
{
  "message": "Booking confirmed.",
  "data": {
    "id": 1,
    "service": "Haircut",
    "slot": "2025-10-15 09:00–09:30"
  }
}
```

**Error — Slot Full (409):**
```json
{
  "message": "This slot is no longer available."
}
```

---

## Architecture Decisions

### Slot Generation Logic

The slot generation algorithm lives in `app/Services/SlotService.php`, completely decoupled from controllers and Filament. The `availableSlots(Service, Carbon)` method follows a pipeline approach:

1. **Holiday gate** — checks the service's holidays first; if the date is a holiday, return empty immediately (cheapest check).
2. **Opening hours lookup** — finds the opening window for the day-of-week; if none exists (e.g., Sunday), return empty.
3. **Slot generation** — iterates from the opening start time in increments of `duration_minutes + cleanup_minutes`, ensuring every slot's end time (`slot_start + duration`) does not exceed closing time.
4. **Break filtering** — removes any slot whose start time falls within a break window.
5. **Capacity filtering** — runs a single grouped query (`GROUP BY slot_start`) to count existing bookings per slot, then excludes slots that have met `max_capacity`.

This ordering is intentional: steps 1–4 are in-memory (no extra DB queries), and step 5 performs exactly one query regardless of slot count.

### Booking Validation

Validation is split into two layers for clarity and defense-in-depth:

- **`StoreBookingRequest`** uses Laravel's `after()` callback to validate business rules (holiday, opening hours, break overlap, capacity) with readable, specific error messages.
- **`BookingController::store()`** wraps the actual insert inside a `DB::transaction` with `lockForUpdate()` for concurrency safety (see below).

This separation keeps the FormRequest focused on user-facing validation while the controller handles the critical section.

### Concurrency Protection

Two users booking the last available slot simultaneously could cause overbooking. We prevent this with pessimistic locking:

```
DB::transaction(function () {
    $count = Booking::where(...)->lockForUpdate()->count();
    if ($count >= $service->max_capacity) abort(409);
    Booking::create(...);
});
```

`lockForUpdate()` acquires a row-level exclusive lock on the matching booking rows. The second concurrent transaction blocks until the first commits, at which point it re-reads the count and sees the slot is full. This trades a small amount of latency for correctness — acceptable for a booking system where correctness is paramount.

### Trade-offs

- **Global scope on Service:** The `ActiveServiceScope` automatically hides inactive services from API queries, keeping controllers clean. Filament resources explicitly call `withoutGlobalScopes()` so admins always see everything.
- **Email via queue:** `SendBookingConfirmationJob` is dispatched after booking creation and runs asynchronously. The `QUEUE_CONNECTION=database` in `.env` means jobs are stored in the `jobs` table — run `php artisan queue:work` to process them.
- **Eager loading:** The `SlotController` loads `openingHours`, `breakTimes`, and `holidays` in a single query (`with()`), then passes the loaded model to `SlotService` which operates entirely in-memory — zero N+1 queries.

---

## Project Structure

```
app/
├── Filament/
│   ├── Resources/
│   │   ├── BookingResource.php        (+ Pages/)
│   │   ├── BreakTimeResource.php      (+ Pages/)
│   │   ├── HolidayResource.php        (+ Pages/)
│   │   ├── OpeningHourResource.php    (+ Pages/)
│   │   └── ServiceResource.php        (+ Pages/)
│   └── Widgets/
│       └── BookingOverviewWidget.php
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── BookingController.php
│   │       └── SlotController.php
│   └── Requests/
│       └── StoreBookingRequest.php
├── Jobs/
│   └── SendBookingConfirmationJob.php
├── Mail/
│   └── BookingConfirmationMail.php
├── Models/
│   ├── Booking.php
│   ├── BreakTime.php
│   ├── Holiday.php
│   ├── OpeningHour.php
│   └── Service.php
├── Scopes/
│   └── ActiveServiceScope.php
└── Services/
    └── SlotService.php
```
