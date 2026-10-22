<?php

namespace Tests\Feature;

use App\Models\Installment;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\TuitionFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_user_can_open_a_print_ready_payment_receipt(): void
    {
        $accountant = User::factory()->create(['role' => 'COMPTABILITE']);
        $payment = $this->createPayment();

        $this->actingAs($accountant)
            ->get("/api/payments/{$payment->payment_id}/receipt/print")
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('SP-2026-00000001')
            ->assertSee('Amani Mukeba')
            ->assertSee('6ème A')
            ->assertSee('75 000 FCFA')
            ->assertSee('Imprimer / Enregistrer en PDF');
    }

    public function test_parent_cannot_open_an_accounting_receipt_print_endpoint(): void
    {
        $parent = User::factory()->create(['role' => 'PARENT']);
        $payment = $this->createPayment();

        $this->actingAs($parent)
            ->get("/api/payments/{$payment->payment_id}/receipt/print")
            ->assertForbidden();

        $this->get("/api/payments/{$payment->payment_id}/receipt/pdf")
            ->assertForbidden();
    }

    public function test_unauthenticated_receipt_requests_return_json_unauthorized(): void
    {
        $payment = $this->createPayment();

        $this->get("/api/payments/{$payment->payment_id}/receipt/pdf")
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_accounting_user_can_download_the_receipt_as_a_pdf(): void
    {
        $accountant = User::factory()->create(['role' => 'COMPTABILITE']);
        $payment = $this->createPayment();

        $response = $this->actingAs($accountant)
            ->get("/api/payments/{$payment->payment_id}/receipt/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('recu-sp-2026-00000001.pdf', $response->headers->get('content-disposition'));
    }

    private function createPayment(): Payment
    {
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
        $student = Student::query()->create([
            'matricule' => 'E-TEST-001',
            'last_name' => 'Mukeba',
            'first_name' => 'Amani',
            'birth_date' => '2012-03-12',
            'birth_place' => 'Kinshasa',
            'gender' => 'F',
            'address' => 'Kinshasa',
        ]);
        $tuitionFee = TuitionFee::query()->create([
            'class_id' => $schoolClass->class_id,
            'school_year_id' => $schoolYear->school_year_id,
            'total_amount' => 225000,
        ]);
        $installment = Installment::query()->create([
            'tuition_fee_id' => $tuitionFee->tuition_fee_id,
            'label' => 'Tranche 1',
            'amount' => 75000,
            'due_date' => '2026-10-01',
        ]);

        return Payment::query()->create([
            'student_id' => $student->student_id,
            'installment_id' => $installment->installment_id,
            'receipt_number' => 'SP-2026-00000001',
            'amount' => 75000,
            'payment_date' => '2026-10-13 10:30:00',
            'payment_method' => 'Espèces',
            'status' => 'PAID',
        ]);
    }
}
