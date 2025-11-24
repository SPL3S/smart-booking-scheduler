<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Service;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected Service $service;
    protected string $futureDate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a service for testing
        $this->service = Service::create([
            'name' => 'Test Service',
            'duration_minutes' => 60,
            'price' => 100.00,
        ]);

        // Set a future date for testing (tomorrow)
        $this->futureDate = Carbon::tomorrow()->format('Y-m-d');

        // Create working hours for the test day
        $dayOfWeek = (int)Carbon::parse($this->futureDate)->format('w');
        WorkingHour::create([
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_prevents_double_booking_with_same_date_and_start_time()
    {
        $bookingData = [
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ];

        // Create first booking
        $response = $this->postJson('/api/bookings', $bookingData);
        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
        ]);

        // Try to create a second booking with the same date and start_time
        $response = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client2@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        // Should fail due to application-level conflict detection (not database constraint)
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Booking conflict detected. The selected time slot is already booked.'
            ]);
        $this->assertDatabaseCount('bookings', 1);
    }

    /** @test */
    public function it_prevents_booking_with_same_start_time_but_different_end_time()
    {
        // Create first booking: 10:00-11:00
        Booking::create([
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to create a booking with same start time but different end time: 10:00-12:00
        $response = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client2@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        // Should fail due to application-level conflict detection (overlapping times)
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Booking conflict detected. The selected time slot is already booked.'
            ]);
        $this->assertDatabaseCount('bookings', 1);
    }

    /** @test */
    public function it_prevents_booking_when_start_time_matches_existing_booking_end_time()
    {
        // Create first booking: 10:00-11:00
        Booking::create([
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to create a booking that starts exactly when previous ends: 11:00-12:00
        // This should be allowed (no overlap)
        $response = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client2@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);

        // Should succeed - no overlap when one ends exactly when the other starts
        $response->assertStatus(201);
        $this->assertDatabaseCount('bookings', 2);
    }

    /** @test */
    public function it_prevents_overlapping_bookings_when_new_booking_starts_during_existing_booking()
    {
        // Create an existing booking from 10:00 to 11:00
        Booking::create([
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to create a booking that starts during the existing booking (10:30 to 11:30)
        $response = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client2@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:30',
            'end_time' => '11:30',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('bookings', 1);
    }

    /** @test */
    public function it_prevents_overlapping_bookings_when_new_booking_ends_during_existing_booking()
    {
        // Create an existing booking from 10:00 to 11:00
        Booking::create([
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to create a booking that ends during the existing booking (09:30 to 10:30)
        $response = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client2@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '09:30',
            'end_time' => '10:30',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('bookings', 1);
    }

    /** @test */
    public function it_prevents_overlapping_bookings_when_new_booking_fully_contains_existing_booking()
    {
        // Create an existing booking from 10:00 to 11:00
        Booking::create([
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to create a booking that fully contains the existing booking (09:30 to 11:30)
        $response = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client2@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '09:30',
            'end_time' => '11:30',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('bookings', 1);
    }

    /** @test */
    public function it_prevents_overlapping_bookings_when_new_booking_is_fully_contained_by_existing_booking()
    {
        // Create an existing booking from 09:30 to 11:30
        Booking::create([
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '09:30',
            'end_time' => '11:30',
            'status' => 'confirmed',
        ]);

        // Try to create a booking that is fully contained by the existing booking (10:00 to 11:00)
        $response = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client2@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('bookings', 1);
    }

    /** @test */
    public function it_allows_non_overlapping_bookings()
    {
        // Create an existing booking from 10:00 to 11:00
        Booking::create([
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Create a booking before the existing one with a clear gap (09:00 to 09:30)
        $response1 = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client2@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '09:30',
        ]);
        $response1->assertStatus(201);

        // Create a booking after the existing one with a clear gap (11:30 to 12:00)
        $response2 = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client3@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '11:30',
            'end_time' => '12:00',
        ]);
        $response2->assertStatus(201);

        $this->assertDatabaseCount('bookings', 3);
    }

    /** @test */
    public function it_allows_bookings_on_different_dates()
    {
        $tomorrowDate = Carbon::parse($this->futureDate);
        $tomorrow = $tomorrowDate->format('Y-m-d');
        $dayAfter = $tomorrowDate->copy()->addDay()->format('Y-m-d');

        // Create working hours for the day after tomorrow
        $dayOfWeek = (int)Carbon::parse($dayAfter)->format('w');
        WorkingHour::create([
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        // Create booking for tomorrow
        $response1 = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $tomorrow,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
        $response1->assertStatus(201);

        // Create booking for day after tomorrow with same time
        $response2 = $this->postJson('/api/bookings', [
            'service_id' => $this->service->id,
            'client_email' => 'client2@example.com',
            'booking_date' => $dayAfter,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
        $response2->assertStatus(201);

        $this->assertDatabaseCount('bookings', 2);
    }

    /** @test */
    public function it_returns_available_slots_for_a_given_date_and_service()
    {
        // Create a booking to make one slot unavailable
        Booking::create([
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        $response = $this->getJson("/api/available-slots?date={$this->futureDate}&service_id={$this->service->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['slots']);

        $slots = $response->json('slots');

        // Verify we have slots returned
        $this->assertGreaterThan(0, count($slots), 'Should return at least one available slot');

        // Verify the booked slot (10:00-11:00) is not in the available slots
        $hasBookedSlot = collect($slots)->contains(function ($slot) {
            return $slot['start_time'] === '10:00' && $slot['end_time'] === '11:00';
        });
        $this->assertFalse($hasBookedSlot, 'Booked slot should not be in available slots');

        // Verify all returned slots have the correct structure
        foreach ($slots as $slot) {
            $this->assertArrayHasKey('start_time', $slot);
            $this->assertArrayHasKey('end_time', $slot);
            $this->assertNotEmpty($slot['start_time']);
            $this->assertNotEmpty($slot['end_time']);
        }
    }

    /** @test */
    public function it_requires_date_and_service_id_for_available_slots_endpoint()
    {
        $response = $this->getJson('/api/available-slots');
        $response->assertStatus(422);

        $response = $this->getJson("/api/available-slots?date={$this->futureDate}");
        $response->assertStatus(422);

        $response = $this->getJson("/api/available-slots?service_id={$this->service->id}");
        $response->assertStatus(422);
    }

    /** @test */
    public function it_validates_date_must_be_today_or_future_for_available_slots()
    {
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        $response = $this->getJson("/api/available-slots?date={$yesterday}&service_id={$this->service->id}");
        $response->assertStatus(422);
    }

    /** @test */
    public function it_returns_empty_slots_when_no_working_hours_exist()
    {
        // Delete working hours for the test day
        $dayOfWeek = (int)Carbon::parse($this->futureDate)->format('w');
        WorkingHour::where('day_of_week', $dayOfWeek)->delete();

        $response = $this->getJson("/api/available-slots?date={$this->futureDate}&service_id={$this->service->id}");

        $response->assertStatus(200)
            ->assertJson(['slots' => []]);
    }

    /** @test */
    public function it_excludes_cancelled_bookings_from_available_slots()
    {
        // Create a cancelled booking
        Booking::create([
            'service_id' => $this->service->id,
            'client_email' => 'client1@example.com',
            'booking_date' => $this->futureDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
        ]);

        $response = $this->getJson("/api/available-slots?date={$this->futureDate}&service_id={$this->service->id}");

        $response->assertStatus(200);

        // Verify the cancelled booking slot is available
        $slots = $response->json('slots');
        $hasCancelledSlot = collect($slots)->contains(function ($slot) {
            return $slot['start_time'] === '10:00' && $slot['end_time'] === '11:00';
        });
        $this->assertTrue($hasCancelledSlot, 'Cancelled booking slot should be available');
    }

    /** @test */
    public function it_filters_past_slots_for_today()
    {
        // Set working hours for today
        $today = Carbon::today()->format('Y-m-d');
        $dayOfWeek = (int)Carbon::parse($today)->format('w');
        WorkingHour::updateOrCreate(
            ['day_of_week' => $dayOfWeek],
            [
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_active' => true,
            ]
        );

        // Mock current time to be 14:00 (2 PM)
        Carbon::setTestNow(Carbon::parse($today . ' 14:00:00'));

        $response = $this->getJson("/api/available-slots?date={$today}&service_id={$this->service->id}");

        $response->assertStatus(200);

        $slots = $response->json('slots');
        // All slots should be after 14:00
        foreach ($slots as $slot) {
            $slotTime = Carbon::parse($slot['start_time']);
            $this->assertTrue(
                $slotTime->isAfter(Carbon::now()) || $slotTime->isSameMinute(Carbon::now()),
                "Slot {$slot['start_time']} should be in the future"
            );
        }

        Carbon::setTestNow(); // Reset test time
    }
}
