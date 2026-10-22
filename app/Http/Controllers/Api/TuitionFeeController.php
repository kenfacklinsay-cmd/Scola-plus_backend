<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Finance\SaveInstallmentScheduleRequest;
use App\Http\Requests\Api\Finance\StoreDiscountRequest;
use App\Http\Requests\Api\Finance\StoreTuitionFeeRequest;
use App\Http\Resources\Api\TuitionFeeResource;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\TuitionFee;
use App\Services\Finance\ApplyTuitionDiscount;
use App\Services\Finance\CalculateTuitionBalance;
use App\Services\Finance\SaveInstallmentSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Gérer les frais annuels, les échéanciers, les réductions et les soldes. */
class TuitionFeeController extends Controller
{
    /** Lister les frais et leurs tranches pour une année scolaire. */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'exists:school_years,school_year_id'],
        ]);

        $fees = TuitionFee::query()
            ->with(['schoolClass', 'schoolYear', 'installments' => fn ($query) => $query
                ->orderBy('due_date')->orderBy('installment_id')])
            ->where('school_year_id', $validated['school_year_id'])
            ->orderBy('class_id')
            ->paginate(50);

        return TuitionFeeResource::collection($fees)->response();
    }

    /** Rechercher les élèves inscrits dans la classe concernée par ces frais. */
    public function students(Request $request, TuitionFee $tuitionFee): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->where('class_id', $tuitionFee->class_id)
                ->where('school_year_id', $tuitionFee->school_year_id))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($studentQuery) use ($search): void {
                    $studentQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return response()->json([
            'data' => $students->getCollection()->map(fn (Student $student): array => [
                'id' => $student->student_id,
                'matricule' => $student->matricule,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
            ]),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    /** Définir les frais totaux d'une classe pour l'année sélectionnée. */
    public function store(StoreTuitionFeeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $installments = $validated['installments'] ?? null;
        unset($validated['installments']);

        $fee = DB::transaction(function () use ($validated, $installments): TuitionFee {
            $fee = TuitionFee::query()->create($validated);

            if ($installments !== null) {
                $fee->installments()->createMany($installments);
            }

            return $fee;
        });

        return (new TuitionFeeResource($fee->load([
            'schoolClass',
            'schoolYear',
            'installments' => fn ($query) => $query->orderBy('due_date')->orderBy('installment_id'),
        ])))
            ->response()->setStatusCode(201);
    }

    /** Enregistrer ou remplacer les tranches avant tout paiement ou réduction. */
    public function saveInstallments(
        SaveInstallmentScheduleRequest $request,
        TuitionFee $tuitionFee,
        SaveInstallmentSchedule $schedule,
    ): TuitionFeeResource {
        return new TuitionFeeResource($schedule->save($tuitionFee, $request->validated('installments')));
    }

    /** Accorder une réduction sur le total annuel d'un élève. */
    public function addDiscount(
        StoreDiscountRequest $request,
        TuitionFee $tuitionFee,
        ApplyTuitionDiscount $discount,
    ): JsonResponse {
        $record = $discount->apply($tuitionFee, $request->validated());

        return response()->json([
            'message' => 'La réduction a été enregistrée.',
            'data' => [
                'id' => $record->discount_id,
                'student_id' => $record->student_id,
                'amount' => $record->amount,
                'reason' => $record->reason,
            ],
        ], 201);
    }

    /** Consulter le solde détaillé d'un élève inscrit dans cette classe/année. */
    public function studentBalance(
        TuitionFee $tuitionFee,
        Student $student,
        CalculateTuitionBalance $balance,
    ): JsonResponse {
        $enrolled = Enrollment::query()
            ->where('student_id', $student->student_id)
            ->where('class_id', $tuitionFee->class_id)
            ->where('school_year_id', $tuitionFee->school_year_id)
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'student_id' => ['Cet élève n’est pas inscrit dans cette classe pour cette année scolaire.'],
            ]);
        }

        return response()->json(['data' => $balance->calculate($tuitionFee, $student)]);
    }
}
