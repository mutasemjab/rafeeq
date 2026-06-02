<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SpecialistController;
use App\Models\Specialist;
use App\Models\SpecialistAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SpecialistAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_availabilities_are_filtered_by_date_and_active_flag(): void
    {
        $specialist = Specialist::factory()->create();

        SpecialistAvailability::query()->create([
            'specialist_id' => $specialist->id,
            'available_date' => '2026-06-10',
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'slot_duration_minutes' => 30,
            'is_available' => true,
            'capacity' => 2,
        ]);

        SpecialistAvailability::query()->create([
            'specialist_id' => $specialist->id,
            'available_date' => '2026-06-10',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'slot_duration_minutes' => 30,
            'is_available' => false,
            'capacity' => 1,
        ]);

        SpecialistAvailability::query()->create([
            'specialist_id' => $specialist->id,
            'available_date' => '2026-06-11',
            'start_time' => '11:00:00',
            'end_time' => '11:30:00',
            'slot_duration_minutes' => 30,
            'is_available' => true,
            'capacity' => 1,
        ]);

        $request = Request::create('/api/v1/specialists/'.$specialist->id.'/availabilities', 'GET', [
            'date' => '2026-06-10',
        ]);

        $response = app(SpecialistController::class)->availabilities($request, $specialist);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2026-06-10', $payload['date']);
        $this->assertCount(1, $payload['slots']);
        $this->assertSame('2026-06-10', $payload['slots'][0]['available_date']);
        $this->assertSame(3, $payload['slots'][0]['day_of_week']);
        $this->assertSame('09:00:00', $payload['slots'][0]['start_time']);
        $this->assertSame('09:30:00', $payload['slots'][0]['end_time']);
        $this->assertSame(30, $payload['slots'][0]['slot_duration_minutes']);
        $this->assertSame(2, $payload['slots'][0]['capacity']);
    }
}
