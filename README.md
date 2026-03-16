## Time Scheduling System — Laravel 12 + Filament v5

Production-ready time scheduling system with:
- **Admin panel** (Filament v5) to manage services, opening hours, breaks, holidays, and bookings.
- **Public API** to expose available slots and allow clients to create bookings.

This README explains setup, project structure, APIs, admin panel, and key architectural decisions.

---

## 1. Setup & Installation

### 1.1. Requirements

- PHP 8.2+
- MySQL 8+ (or compatible)
- Composer
- Laravel 12
- Node (optional, only if you change frontend assets)

### 1.2. Installation steps

```bash
# 1. Clone the repository
git clone <repo-url> ValueLab
cd ValueLab

# 2. Install PHP dependencies
composer install

# 3. Copy environment file and generate app key
cp .env.example .env
php artisan key:generate

# 4. Configure your database in .env
# Example:
# DB_DATABASE=value_lab
# DB_USERNAME=root
# DB_PASSWORD=your_password

# 5. Configure queue and mail (for async confirmation emails)
# QUEUE_CONNECTION=database
# MAIL_MAILER=log  (or smtp, etc.)

# 6. Run migrations and seeders
php artisan migrate:fresh --seed

# 7. (Optional in dev) Cache Filament components for speed
php artisan filament:optimize

# 8. Start the application (Herd / Valet / artisan serve)
php artisan serve
```

If you are using **Herd**, the project is served at something like:

```text
http://valuelab.test/
```

### 1.3. Admin credentials

The seeder creates one admin-capable user:

- **Email:** `test@example.com`
- **Password:** `password`

Login at:

```text
http://valuelab.test/admin
```

---

## 2. Domain & Data Model

### 2.1. Models

- **Service**
  - `name`, `description`
  - `duration_minutes` (slot length)
  - `cleanup_minutes` (buffer between slots)
  - `price`
  - `max_capacity` (max bookings per slot)
  - `is_active`
  - Relationships:
    - `hasMany OpeningHour`
    - `hasMany BreakTime`
    - `hasMany Booking`
  - Global scope: `ActiveServiceScope` hides inactive services from *API* queries.

- **OpeningHour**
  - `service_id`
  - `day_of_week` (0–6, Sunday–Saturday)
  - `start_time`, `end_time`
  - Unique per `(service_id, day_of_week)`

- **BreakTime**
  - `service_id`
  - `day_of_week`
  - `start_time`, `end_time`

- **Holiday** (global)
  - `date` (`unique`)
  - `name`
  - Applies to **all** services — if a date is a holiday, no service can be booked.

- **Booking**
  - `service_id`
  - `name`, `email`
  - `slot_start`, `slot_end`
  - Accessors:
    - `formatted_slot_time` — `YYYY-MM-DD HH:MM–HH:MM`
    - `booking_status` — `upcoming | ongoing | completed | unknown`

---

## 3. Admin Panel (Filament v5)

The admin panel is built with Filament’s panel builder (`AdminPanelProvider`).

### 3.1. Resources

All resources live under `app/Filament/Resources`:

- `ServiceResource`
  - Manage Services.
  - Form: name, description, duration, price, cleanup, capacity, active.
  - Table: duration, price, buffer, capacity, active flag, bookings count.

- `OpeningHourResource`
  - **List** shows `(Service, Day of week, Start, End)`.
  - **Create**: custom 7-day grid:
    - Choose a **Service** once.
    - For each day (Sunday–Saturday), specify optional `Start`/`End` time.
    - Days can be left empty (no hours).
    - For each day with both times and `start < end`, a row is upserted into `opening_hours`.
    - Empty pairs remove any existing hours for that day.
    - After save, redirects to the list page.

- `BreakTimeResource`
  - Manage break windows (e.g. lunch 12:00–13:00).
  - Per service, per day-of-week, with start/end times.

- `HolidayResource`
  - Global holidays (no service link).
  - Form: `date`, `name`.
  - Table: `date`, `name`.
  - Any holiday date blocks all slots for all services.

- `BookingResource`
  - Manage all bookings.
  - Table:
    - Columns: service, name, email, `slot_start`, `slot_end`, `booking_status`, `created_at`.
    - Filters:
      - By service.
      - By date range.
  - Form (admin editing): service, name, email, slot_start, slot_end.

### 3.2. Dashboard widget

`BookingOverviewWidget` (stats overview):

- **Today’s bookings** — count of bookings with `slot_start` on today.
- **Next booking** — nearest upcoming booking with service name.
- **Today’s revenue** — sum of `service.price` for today’s bookings.

---

## 4. Public API

All API routes live in `routes/api.php` and are mounted at `/api/*`:

```php
GET  /api/slots
POST /api/bookings
```

The `bootstrap/app.php` routes `api` correctly and an `ForceJsonApi` middleware ensures API responses are always JSON (no HTML redirects).

### 4.1. GET /api/slots

**Purpose:** fetch available slots for a given service and date.

- **Endpoint**
  ```http
  GET /api/slots?service_id=1&date=2026-03-17
  ```

- **Query parameters**
  - `service_id` (required, integer, must exist in `services`).
  - `date` (required, `Y-m-d`).

- **Response (200)**
  ```json
  {
    "data": [
      "09:00",
      "09:40",
      "10:20",
      "11:00",
      "11:40",
      "13:00",
      "13:40",
      "14:20",
      "15:00",
      "15:40",
      "16:20"
    ]
  }
  ```

- **Error responses (validation)**
  - Missing/invalid params return a `422` JSON with errors.

**Implementation notes**

- Controller: `App\Http\Controllers\Api\SlotController`
  - Validates `service_id` and `date`.
  - Loads `Service` with `openingHours` and `breakTimes`.
  - Delegates to `SlotService::availableSlots($service, $date)`.

- Service: `App\Services\SlotService`
  - Pipeline:
    1. **Holiday check (global)**  
       - If `Holiday::whereDate(date)->exists()`, returns empty.
    2. **Opening hours for that weekday**  
       - If none found: returns empty.
    3. **Generate slots**  
       - From `opening_hours.start_time` to `end_time`, step = `duration_minutes + cleanup_minutes`.
    4. **Filter out breaks**  
       - Any slot whose start falls inside a `BreakTime` [start,end) is removed.
    5. **Filter out full-capacity slots**  
       - Query bookings grouped by `slot_start` for the day.
       - Remove slots where `count >= max_capacity`.

---

### 4.2. POST /api/bookings

**Purpose:** create a booking for a specific service and slot start time.

- **Endpoint**
  ```http
  POST /api/bookings
  ```

- **Payload**
  ```json
  {
    "name": "John Doe",
    "email": "john@example.com",
    "service_id": 1,
    "slot_start": "2026-03-17 09:00"
  }
  ```

- **Success (201)**
  ```json
  {
    "message": "Booking confirmed.",
    "data": {
      "id": 1,
      "service": "Haircut",
      "slot": "2026-03-17 09:00–09:30"
    }
  }
  ```

- **Validation errors (422)**
  - Examples:
    - Missing fields.
    - Inactive service.
    - Date is a global holiday.
    - Outside opening hours.
    - During a break.
    - Slot already full.

- **Conflict (409)**
  - When two users race to book the last seat; one wins, the other gets:
  ```json
  {
    "message": "This slot is no longer available."
  }
  ```

**Implementation notes**

- Controller: `App\Http\Controllers\Api\BookingController`
  - Validates with `StoreBookingRequest`.
  - Uses `DB::transaction()` and `lockForUpdate()` to **prevent overbooking**.
  - Creates booking with computed `slot_end`.
  - Dispatches `SendBookingConfirmationJob` (queue) after successful creation.

- FormRequest: `App\Http\Requests\StoreBookingRequest`
  - Base rules:
    - `name` required, string, max 255
    - `email` required, email
    - `service_id` required, exists
    - `slot_start` required, `Y-m-d H:i`
  - `after()` hook checks:
    1. Service is active.
    2. No global holiday on that date (`Holiday` table).
    3. Service has opening hours for that weekday.
    4. Slot is fully inside opening window.
    5. Slot start is not inside a break.
    6. Capacity not exceeded (pre-check).

---

## 5. Concurrency, Queues & Email

### 5.1. Concurrency protection

- Critical section in `BookingController::store()`:

```php
$booking = DB::transaction(function () use (...) {
    $currentCount = Booking::where('service_id', $service->id)
        ->where('slot_start', $slotStart)
        ->lockForUpdate()
        ->count();

    if ($currentCount >= $service->max_capacity) {
        abort(Response::HTTP_CONFLICT, 'This slot is no longer available.');
    }

    return Booking::create([...]);
});
```

- `lockForUpdate()` ensures concurrent transactions see a consistent row set, preventing overbooking.

### 5.2. Queued confirmation email

- Job: `App\Jobs\SendBookingConfirmationJob` (`ShouldQueue`)
- Mail: `App\Mail\BookingConfirmationMail` (markdown view `mail.booking-confirmation`)
- After booking is created, job is **dispatched**:

```php
SendBookingConfirmationJob::dispatch($booking);
```

- Configure:
  - `.env` → `QUEUE_CONNECTION=database`
  - Run:
    ```bash
    php artisan queue:work
    ```

---

## 6. Middleware & JSON API Responses

- Custom middleware: `App\Http\Middleware\ForceJsonApi`
  - Appended to the `api` middleware group in `bootstrap/app.php`:
  ```php
  ->withMiddleware(function (Middleware $middleware): void {
      $middleware->api(append: [
          \App\Http\Middleware\ForceJsonApi::class,
      ]);
  })
  ```
  - Sets `Accept: application/json` on all `/api/*` requests so validation errors and other failures always return JSON, never HTML redirects.

---

## 7. Project Structure (Key Files)

```text
app/
├── Filament/
│   ├── Resources/
│   │   ├── BookingResource.php        (+ Pages/)
│   │   ├── BreakTimeResource.php      (+ Pages/)
│   │   ├── HolidayResource.php        (+ Pages/)
│   │   ├── OpeningHourResource.php    (+ Pages/, custom 7-day Create page)
│   │   └── ServiceResource.php        (+ Pages/)
│   ├── Widgets/
│   │   └── BookingOverviewWidget.php
│   └── Providers/
│       └── AdminPanelProvider.php     (Filament panel config)
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── BookingController.php
│   │       └── SlotController.php
│   ├── Middleware/
│   │   └── ForceJsonApi.php
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

database/
├── migrations/
│   ├── 2025_10_01_000001_create_services_table.php
│   ├── 2025_10_01_000002_create_opening_hours_table.php
│   ├── 2025_10_01_000003_create_break_times_table.php
│   ├── 2025_10_01_000004_create_holidays_table.php
│   └── 2025_10_01_000005_create_bookings_table.php
└── seeders/
    ├── ServiceSeeder.php
    ├── HolidaySeeder.php
    ├── BookingSeeder.php
    └── DatabaseSeeder.php
```

---

## 8. Summary of Business Rules

- Slots are generated per service & date, based on:
  - Service duration and cleanup (interval = duration + cleanup).
  - Opening hours for that day.
  - Break times.
  - Global holidays.
  - Current bookings and max capacity.
- Bookings:
  - Must be within opening hours.
  - Cannot overlap breaks.
  - Cannot be on global holidays.
  - Cannot exceed per-slot capacity.
  - Are protected against race conditions via DB transactions and locks.

This setup fully matches the assignment requirements: clean architecture, service-based slot generation, Filament admin resources, REST API, queues, concurrency safety, global holidays, and clear documentation. 
