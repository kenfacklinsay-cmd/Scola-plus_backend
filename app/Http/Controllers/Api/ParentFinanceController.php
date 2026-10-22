<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\ParentAccount;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Student;
use App\Services\Finance\CalculateTuitionBalance;
use App\Services\Gradebook\BuildStudentBulletin;
use App\Services\Gradebook\CalculateClassAverages;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Exposer à un parent uniquement la situation financière de ses enfants. */
class ParentFinanceController extends Controller
{
    /** Lister uniquement les enfants associés au compte parent connecté. */
    public function children(Request $request, CalculateClassAverages $calculateAverages): JsonResponse
    {
        $parent = ParentAccount::query()->where('user_id', $request->user()->getKey())->firstOrFail();
        $students = $parent->students()
            ->with(['enrollments' => fn ($query) => $query
                ->with(['schoolClass', 'schoolYear'])
                ->orderByDesc('school_year_id')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'data' => $students->map(fn (Student $student): array => [
                'id' => $student->student_id,
                'matricule' => $student->matricule,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'enrollments' => $student->enrollments->map(function ($enrollment) use ($student, $calculateAverages): array {
                    $periods = Period::query()
                        ->where('school_year_id', $enrollment->school_year_id)
                        ->orderBy('start_date')
                        ->orderBy('period_id')
                        ->get();
                    $today = Carbon::today()->toDateString();
                    $period = $periods->first(fn (Period $candidate): bool => $candidate->start_date->toDateString() <= $today
                        && $candidate->end_date->toDateString() >= $today)
                        ?? $periods->last();
                    $summary = $period === null
                        ? null
                        : $calculateAverages->calculate($enrollment->schoolClass, $period);
                    $studentResult = collect($summary['students'] ?? [])
                        ->firstWhere('student_id', $student->student_id);

                    return [
                        'school_year' => [
                            'id' => $enrollment->school_year_id,
                            'label' => $enrollment->schoolYear->label,
                        ],
                        'class' => [
                            'id' => $enrollment->class_id,
                            'name' => $enrollment->schoolClass->name,
                        ],
                        'period' => $period === null ? null : [
                            'id' => $period->period_id,
                            'label' => $period->label,
                        ],
                        'periods' => $periods->map(fn (Period $item): array => [
                            'id' => $item->period_id,
                            'label' => $item->label,
                        ])->values(),
                        'average' => $studentResult['average'] ?? null,
                        'rank' => $studentResult['rank'] ?? null,
                        'class_size' => $summary['students_with_average_count'] ?? 0,
                    ];
                }),
            ]),
        ]);
    }

    public function childBalance(Request $request, Student $student, CalculateTuitionBalance $balance): JsonResponse
    {
        $parent = ParentAccount::query()->where('user_id', $request->user()->getKey())->firstOrFail();

        abort_unless(
            $parent->students()->where('students.student_id', $student->student_id)->exists(),
            403,
            'Vous ne pouvez consulter que la situation financière de vos enfants.',
        );

        $filters = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,school_year_id'],
        ]);

        $enrollments = Enrollment::query()
            ->with(['schoolClass.tuitionFees' => fn ($query) => $query
                ->with(['installments', 'discounts' => fn ($discounts) => $discounts
                    ->where('student_id', $student->student_id)])])
            ->where('student_id', $student->student_id)
            ->when($filters['school_year_id'] ?? null, fn ($query, $yearId) => $query->where('school_year_id', $yearId))
            ->orderByDesc('school_year_id')
            ->get();

        $balances = [];
        $payments = [];

        foreach ($enrollments as $enrollment) {
            $fee = $enrollment->schoolClass->tuitionFees
                ->firstWhere('school_year_id', $enrollment->school_year_id);

            if ($fee !== null) {
                $balances[] = [
                    'school_year_id' => $enrollment->school_year_id,
                    'class' => [
                        'id' => $enrollment->schoolClass->class_id,
                        'name' => $enrollment->schoolClass->name,
                    ],
                    ...$balance->calculate($fee, $student),
                ];
                $payments = [
                    ...$payments,
                    ...Payment::query()
                        ->with(['installment.tuitionFee.schoolClass', 'installment.tuitionFee.schoolYear'])
                        ->where('student_id', $student->student_id)
                        ->whereHas('installment', fn ($query) => $query->where('tuition_fee_id', $fee->tuition_fee_id))
                        ->where('status', 'PAID')
                        ->latest('payment_date')
                        ->latest('payment_id')
                        ->get()
                        ->map(fn (Payment $payment): array => [
                            'id' => $payment->payment_id,
                            'receipt_number' => $payment->receipt_number,
                            'amount' => $payment->amount,
                            'payment_date' => $payment->payment_date->toIso8601String(),
                            'payment_method' => $payment->payment_method,
                            'installment' => $payment->installment->label,
                            'school_year_id' => $enrollment->school_year_id,
                            'class' => $enrollment->schoolClass->name,
                        ])
                        ->all(),
                ];
            }
        }

        return response()->json([
            'data' => [
                'balances' => $balances,
                'payments' => collect($payments)->sortByDesc('payment_date')->values(),
            ],
        ]);
    }

    /** Consulter les notes détaillées d'un enfant pour une période de son année inscrite. */
    public function childPeriod(
        Request $request,
        Student $student,
        Period $period,
        BuildStudentBulletin $buildBulletin,
    ): JsonResponse {
        $parent = ParentAccount::query()->where('user_id', $request->user()->getKey())->firstOrFail();
        abort_unless(
            $parent->students()->where('students.student_id', $student->student_id)->exists(),
            403,
            'Vous ne pouvez consulter que les résultats de vos enfants.',
        );
        abort_unless(
            Enrollment::query()
                ->where('student_id', $student->student_id)
                ->where('school_year_id', $period->school_year_id)
                ->exists(),
            404,
        );

        $bulletin = $buildBulletin->build($student, $period);

        return response()->json([
            'data' => [
                'student' => [
                    'id' => $student->student_id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                ],
                'class' => ['name' => $bulletin['class']->name],
                'school_year' => ['label' => $bulletin['schoolYear']->label],
                'period' => ['id' => $period->period_id, 'label' => $period->label],
                'average' => $bulletin['average'],
                'mention' => $bulletin['mention'],
                'rank' => $bulletin['rank'],
                'class_size' => $bulletin['class_size'],
                'subjects' => $bulletin['subjects'],
            ],
        ]);
    }
}
