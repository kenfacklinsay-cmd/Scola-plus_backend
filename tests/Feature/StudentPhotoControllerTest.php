<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentPhotoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_view_a_private_student_photo_as_a_streamed_response(): void
    {
        Storage::fake('local');
        $student = Student::query()->create([
            'matricule' => 'E-PHOTO-001',
            'last_name' => 'Mukeba',
            'first_name' => 'Amani',
            'birth_date' => '2012-03-12',
            'birth_place' => 'Kinshasa',
            'gender' => 'F',
            'address' => 'Kinshasa',
        ]);
        $photoPath = "students/{$student->student_id}/photos/photo.png";
        Storage::disk('local')->put($photoPath, 'private-image-content');
        $student->update(['photo' => $photoPath]);
        $secretary = User::factory()->create(['role' => 'SECRETARIAT']);

        $response = $this->actingAs($secretary)->get("/api/students/{$student->student_id}/photo");

        $response->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeaderContains('cache-control', 'private')
            ->assertHeaderContains('cache-control', 'no-store')
            ->assertHeader('x-content-type-options', 'nosniff');
    }
}
