<?php

namespace App\Services\Dashboard;

use App\Models\Enrollment;
use App\Models\Period;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Services\Finance\BuildFinanceOverview;
use App\Services\Gradebook\CalculateClassAverages;

/** Assembler les indicateurs scolaires et financiers du tableau de bord. */
class BuildDashboardOverview
{
    public function __construct(
        private BuildFinanceOverview $financeOverview,
        private CalculateClassAverages $calculateClassAverages,
    ) {}

    /** @return array<string, mixed> */
    public function forPeriod(int $schoolYearId, int $periodId): array
    {
        $schoolYear = SchoolYear::query()->findOrFail($schoolYearId);
        $period = Period::query()
            ->where('school_year_id', $schoolYearId)
            ->findOrFail($periodId);
        $classes = SchoolClass::query()
            ->where('school_year_id', $schoolYearId)
            ->withCount(['enrollments as enrolled_students_count' => fn ($query) => $query->where('status', 'ACTIVE')])
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $classAverages = $classes->map(function (SchoolClass $schoolClass) use ($period): array {
            $summary = $this->calculateClassAverages->calculate($schoolClass, $period);
            $studentAverages = collect($summary['students'])
                ->pluck('average')
                ->filter(fn (?float $average): bool => $average !== null)
                ->values();

            return [
                'class_id' => $schoolClass->class_id,
                'class_name' => $schoolClass->name,
                'level' => $schoolClass->level,
                'students_count' => $schoolClass->enrolled_students_count,
                'students_with_average_count' => $studentAverages->count(),
                'average' => $studentAverages->isEmpty()
                    ? null
                    : round($studentAverages->avg(), 2),
            ];
        })->values()->all();

        return [
            'school_year' => [
                'id' => $schoolYear->school_year_id,
                'label' => $schoolYear->label,
            ],
            'period' => [
                'id' => $period->period_id,
                'label' => $period->label,
            ],
            'metrics' => [
                'students_enrolled' => Enrollment::query()
                    ->where('school_year_id', $schoolYearId)
                    ->where('status', 'ACTIVE')
                    ->count(),
                'classes_active' => $classes->count(),
                'teachers' => Teacher::query()
                    ->whereHas('user', fn ($query) => $query
                        ->where('role', 'ENSEIGNANT')
                        ->where('status', 'ACTIF'))
                    ->count(),
            ],
            'finance' => $this->financeOverview->forSchoolYear($schoolYearId),
            'class_averages' => $classAverages,
        ];
    }
}
