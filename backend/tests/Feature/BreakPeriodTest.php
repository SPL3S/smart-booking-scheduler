<?php

namespace Tests\Feature;

use App\Models\BreakPeriod;
use App\Models\Service;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class BreakPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected WorkingHour $workingHour;
    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a service for testing
        $this->service = Service::create([
            'name' => 'Test Service',
            'duration_minutes' => 60,
            'price' => 100.00,
        ]);

        // Create working hour for Monday (day_of_week=1) from 09:00-17:00
        $this->workingHour = WorkingHour::create([
            'day_of_week' => 1, // Monday
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_create_break_period()
    {
        $response = $this->postJson("/api/admin/working-hours/{$this->workingHour->id}/break-periods", [
            'start_time' => '12:00',
            'end_time' => '13:00',
            'name' => 'Lunch',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'working_hour_id',
                'start_time',
                'end_time',
                'name',
                'created_at',
                'updated_at',
            ]);

        $this->assertDatabaseHas('break_periods', [
            'working_hour_id' => $this->workingHour->id,
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'name' => 'Lunch',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_validates_break_period_within_working_hours()
    {
        $response = $this->postJson("/api/admin/working-hours/{$this->workingHour->id}/break-periods", [
            'start_time' => '18:00',
            'end_time' => '19:00',
            'name' => 'After Hours',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Break period must be within working hours',
            ])
            ->assertJsonValidationErrors(['start_time', 'end_time']);

        $this->assertDatabaseMissing('break_periods', [
            'working_hour_id' => $this->workingHour->id,
            'start_time' => '18:00',
        ]);
    }

    /** @test */
    public function it_can_list_break_periods()
    {
        // Create 2 break periods
        BreakPeriod::create([
            'working_hour_id' => $this->workingHour->id,
            'start_time' => '12:00',
            'end_time' => '13:00',
            'name' => 'Lunch',
            'is_active' => true,
        ]);

        BreakPeriod::create([
            'working_hour_id' => $this->workingHour->id,
            'start_time' => '15:00',
            'end_time' => '15:15',
            'name' => 'Coffee Break',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/admin/working-hours/{$this->workingHour->id}/break-periods");

        $response->assertStatus(200)
            ->assertJsonCount(2);

        $breakPeriods = $response->json();
        // Time format from database is H:i:s, so check that it starts with the expected time
        $this->assertStringStartsWith('12:00', $breakPeriods[0]['start_time']);
        $this->assertStringStartsWith('15:00', $breakPeriods[1]['start_time']);
    }

    /** @test */
    public function it_can_update_break_period()
    {
        $breakPeriod = BreakPeriod::create([
            'working_hour_id' => $this->workingHour->id,
            'start_time' => '12:00',
            'end_time' => '13:00',
            'name' => 'Lunch',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/admin/break-periods/{$breakPeriod->id}", [
            'start_time' => '12:30',
            'end_time' => '13:30',
            'name' => 'Extended Lunch',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Extended Lunch',
            ]);

        // Check time format (database stores as H:i:s)
        $responseData = $response->json();
        $this->assertStringStartsWith('12:30', $responseData['start_time']);
        $this->assertStringStartsWith('13:30', $responseData['end_time']);

        $this->assertDatabaseHas('break_periods', [
            'id' => $breakPeriod->id,
            'start_time' => '12:30:00',
            'end_time' => '13:30:00',
            'name' => 'Extended Lunch',
        ]);
    }

    /** @test */
    public function it_can_delete_break_period()
    {
        $breakPeriod = BreakPeriod::create([
            'working_hour_id' => $this->workingHour->id,
            'start_time' => '12:00',
            'end_time' => '13:00',
            'name' => 'Lunch',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/admin/break-periods/{$breakPeriod->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('break_periods', [
            'id' => $breakPeriod->id,
        ]);
    }

    /** @test */
    public function it_excludes_break_periods_from_available_slots()
    {
        // Get today's date and day of week
        $today = Carbon::today();
        $dayOfWeek = (int)$today->format('w');
        $todayDate = $today->format('Y-m-d');

        // Create or update working hour for today
        $workingHourForToday = WorkingHour::updateOrCreate(
            ['day_of_week' => $dayOfWeek],
            [
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_active' => true,
            ]
        );

        // Create break period 12:00-13:00
        BreakPeriod::create([
            'working_hour_id' => $workingHourForToday->id,
            'start_time' => '12:00',
            'end_time' => '13:00',
            'name' => 'Lunch',
            'is_active' => true,
        ]);

        // Get available slots for today
        $response = $this->getJson("/api/available-slots?date={$todayDate}&service_id={$this->service->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['slots']);

        $slots = $response->json('slots');

        // Find slots with start_time 11:00, 12:00, and 13:00
        $slot11 = collect($slots)->first(function ($slot) {
            return $slot['start_time'] === '11:00';
        });

        $slot12 = collect($slots)->first(function ($slot) {
            return $slot['start_time'] === '12:00';
        });

        $slot13 = collect($slots)->first(function ($slot) {
            return $slot['start_time'] === '13:00';
        });

        // Assert 11:00 slot exists
        $this->assertNotNull($slot11, 'Slot at 11:00 should exist');

        // Assert 12:00 slot does NOT exist (break period)
        $this->assertNull($slot12, 'Slot at 12:00 should NOT exist due to break period');

        // Assert 13:00 slot exists
        $this->assertNotNull($slot13, 'Slot at 13:00 should exist');
    }
}
