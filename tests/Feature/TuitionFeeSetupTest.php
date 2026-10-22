<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\TuitionFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuitionFeeSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_create_class_fee_and_installments_together(): void
    {
        $director = User::factory()->create(['role' => 'DIRECTEUR']);
        $schoolYear = SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);
        $schoolClass = SchoolClass::query()->create([
            'name' => '6ème A',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $schoolYear->school_year_id,
        ]);

        $this->actingAs($director)
            ->postJson('/api/tuition-fees', [
                'class_id' => $schoolClass->class_id,
                'school_year_id' => $schoolYear->school_year_id,
                'total_amount' => 225000,
                'installments' => [
                    ['label' => 'Tranche 1', 'amount' => 75000, 'due_date' => '2026-10-01'],
                    ['label' => 'Tranche 2', 'amount' => 75000, 'due_date' => '2027-01-01'],
                    ['label' => 'Tranche 3', 'amount' => 75000, 'due_date' => '2027-04-01'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.total_amount', '225000.00')
            ->assertJsonCount(3, 'data.installments')
            ->assertJsonPath('data.installments.0.label', 'Tranche 1');

        $fee = TuitionFee::query()->firstOrFail();
        $this->assertSame(3, $fee->installments()->count());
    }

    public function test_class_fee_creation_rejects_installments_that_do_not_match_annual_total(): void
    {
        $director = User::factory()->create(['role' => 'DIRECTEUR']);
        $schoolYear = SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);
        $schoolClass = SchoolClass::query()->create([
            'name' => '6ème A',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $schoolYear->school_year_id,
        ]);

        $this->actingAs($director)
            ->postJson('/api/tuition-fees', [
                'class_id' => $schoolClass->class_id,
                'school_year_id' => $schoolYear->school_year_id,
                'total_amount' => 225000,
                'installments' => [
                    ['label' => 'Tranche 1', 'amount' => 75000, 'due_date' => '2026-10-01'],
                    ['label' => 'Tranche 2', 'amount' => 75000, 'due_date' => '2027-01-01'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('installments');

        $this->assertDatabaseCount('tuition_fees', 0);
        $this->assertDatabaseCount('installments', 0);
    }
}
