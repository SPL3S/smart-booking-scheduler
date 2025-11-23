<?php

namespace Tests\Unit;

use App\Repositories\WorkingDayRepository;
use Tests\TestCase;

class WorkingDayRepositoryTest extends TestCase
{
    protected WorkingDayRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new WorkingDayRepository();
    }

    /** @test */
    public function it_returns_correct_day_name(): void
    {
        // Monday (day index 1)
        $mondayName = $this->repository->getDayName(1);
        $this->assertEquals('Monday', $mondayName);

        // Friday (day index 5)
        $fridayName = $this->repository->getDayName(5);
        $this->assertEquals('Friday', $fridayName);
    }

    /** @test */
    public function it_returns_correct_day_name_in_spanish(): void
    {
        // Monday (day index 1) in Spanish
        $mondayName = $this->repository->getDayName(1, 'es');
        $this->assertEquals('Lunes', $mondayName);
    }

    /** @test */
    public function it_returns_correct_day_name_in_french(): void
    {
        // Monday (day index 1) in French
        $mondayName = $this->repository->getDayName(1, 'fr');
        $this->assertEquals('Lundi', $mondayName);
    }

    /** @test */
    public function it_identifies_weekends(): void
    {
        // Sunday (day index 0) is a weekend
        $this->assertTrue($this->repository->isWeekend(0));

        // Saturday (day index 6) is a weekend
        $this->assertTrue($this->repository->isWeekend(6));

        // Monday (day index 1) is not a weekend
        $this->assertFalse($this->repository->isWeekend(1));
    }

    /** @test */
    public function it_returns_all_day_names(): void
    {
        $allDayNames = $this->repository->getAllDayNames();

        $this->assertIsArray($allDayNames);
        $this->assertCount(7, $allDayNames);
        $this->assertEquals('Sunday', $allDayNames[0]);
        $this->assertEquals('Monday', $allDayNames[1]);
        $this->assertEquals('Saturday', $allDayNames[6]);
    }

    /** @test */
    public function it_validates_day_index(): void
    {
        // Valid day indices
        $this->assertTrue($this->repository->isValidDayIndex(0));
        $this->assertTrue($this->repository->isValidDayIndex(6));

        // Invalid day indices
        $this->assertFalse($this->repository->isValidDayIndex(7));
        $this->assertFalse($this->repository->isValidDayIndex(-1));
    }
}

