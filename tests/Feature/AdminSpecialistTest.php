<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SpecialistsController;
use App\Models\Admin;
use App\Models\Specialist;
use App\Models\SpecialistAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminSpecialistTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_specialist_using_legacy_form_fields(): void
    {
        $indexRoute = route('admin.specialists.index');

        Admin::query()->create([
            'name' => 'Admin User',
            'email' => 'admin+'.uniqid().'@example.com',
            'username' => 'admin_'.uniqid(),
            'password' => bcrypt('secret'),
        ]);

        $request = Request::create('/admin/specialists', 'POST', [
            'name' => 'Mona Ali',
            'title' => 'Speech Therapist',
            'bio' => 'Experienced therapist',
            'session_fee' => '120',
            'currency' => 'USD',
            'specializations' => 'Autism, Speech Disorders',
            'languages' => 'Arabic, English',
            'is_active' => '1',
            'is_available' => '1',
        ]);

        $response = app(SpecialistsController::class)->store($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($indexRoute, $response->getTargetUrl());

        $specialist = Specialist::query()->sole();

        $this->assertSame('Mona', $specialist->first_name);
        $this->assertSame('Ali', $specialist->last_name);
        $this->assertSame('Mona Ali', $specialist->display_name);
        $this->assertSame('Mona Ali', $specialist->name);
        $this->assertSame('Speech Therapist', $specialist->title);
        $this->assertSame('Speech Therapist', $specialist->specialty);
        $this->assertSame(120.0, $specialist->session_fee);
        $this->assertSame(['Autism', 'Speech Disorders'], $specialist->specializations);
        $this->assertSame(['Arabic', 'English'], $specialist->languages);
        $this->assertTrue($specialist->is_active);
        $this->assertTrue($specialist->is_available);
        $this->assertNotSame('', $specialist->slug);
    }

    public function test_admin_can_add_specialist_availability_from_dashboard(): void
    {
        $specialist = Specialist::factory()->create([
            'name' => 'Mona Ali',
        ]);

        $editRoute = route('admin.specialists.edit', $specialist);

        $request = Request::create('/admin/specialists/'.$specialist->id.'/availabilities', 'POST', [
            'available_date' => '2026-06-10',
            'start_time' => '09:00',
            'end_time' => '09:30',
            'slot_duration_minutes' => '30',
            'capacity' => '2',
            'slot_is_available' => '1',
        ]);

        $response = app(SpecialistsController::class)->storeAvailability($request, $specialist);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($editRoute, $response->getTargetUrl());

        $availability = SpecialistAvailability::query()->sole();

        $this->assertSame($specialist->id, $availability->specialist_id);
        $this->assertSame('2026-06-10', $availability->available_date?->toDateString());
        $this->assertSame('09:00:00', $availability->start_time);
        $this->assertSame('09:30:00', $availability->end_time);
        $this->assertSame(30, $availability->slot_duration_minutes);
        $this->assertSame(2, $availability->capacity);
        $this->assertTrue($availability->is_available);
    }
}
