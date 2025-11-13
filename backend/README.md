# Smart Booking Scheduler

Appointment booking system built with Laravel and Vue.js.

## Features Implemented

Service selection
calendar date picker.  
Real-time available time slot display.  
Admin panel for working hours management.  
Prevention for double booking with locking the time slot, disable previous dates hide unavailable time slots.

---

## Quick Start

### Stack

- PHP >= 8.2
- Node.js >= 18
- MySQL 8.0.3
- Laravel 10.10
- VUE 3.4
- Vite 5

### Backend Setup

```
cd backend

# Install and configure
composer install
cp .env.example .env
php artisan key:generate

# Database setup
Hook up a Db of your flavour (I stay with mysql)

# Run migrations with sample data
php artisan migrate:fresh --seed

# Start server
php artisan serve
```

Backend: `http://localhost:8000`

### Frontend Setup

```
cd frontend
npm install
npm run dev
```

Frontend: `http://localhost:5173`

---

## Testing

1. Open `http://localhost:5173`
2. Book Appointment > select service > pick date > choose time slot > enter email > confirm
3. Admin Panel > Manage working hours (add/edit/delete)

Sample Data
- Services: Haircut (30min), Hair Coloring (90min), Beard Trim (15min)
- Working Hours: Monday-Friday, 9AM - 5PM (can add weekends in admin panel as test)

---

## API Endpoints

```
GET    /api/services
GET    /api/available-slots?date=YYYY-MM-DD&service_id=1
POST   /api/bookings
GET    /api/admin/working-hours
POST   /api/admin/working-hours
PUT    /api/admin/working-hours/{id}
DELETE /api/admin/working-hours/{id}
```

## Project Structure

```
backend/
├── app/Http/Controllers/     # API endpoints
├── app/Models/               # Booking, Service, WorkingHour
├── app/Services/             # SlotGeneratorService
└── database/migrations/      # Database schema, seeder

frontend/
├── src/components/           # BookingForm, AdminPanel
└── src/App.vue               # Main component
```


---

## Key Details

**Double booking prevention** Database transactions with `lockForUpdate()` prevent double bookings

**Time Slot Generation:** Time slots calculated based on working hours, service duration, and existing bookings

**Edge Cases Handled:** Past dates disabled, booking conflicts prevented

---

## With more time:

- Tests: Ill be honest, I haven't implemented TTD in my workflow. I know its unwanted practice and I am slowly picking up the process of writting tests.

- Queue jobs for email notifications witch dispatch: 
    Mail::to($booking->client_email)->send(new BookingConfirmed($booking));
    build nice minimal email template, if phone number present, send SMS.

- Booking cancellation/rescheduling also user auth is required for the process:
Cancell the booking with  Route::delete('/api/bookings/{id}', [BookingController::class, 'cancel']); with cancelled_at timestamp to keept the records of the transaction.

Reshedule with Update method.

- Multi-staff support, adding staff option before picking up the service: if staff is available > show available slots.

---

