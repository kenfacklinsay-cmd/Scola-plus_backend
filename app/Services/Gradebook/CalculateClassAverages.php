<?php

namespace App\Services\Gradebook;

use App\Models\Period;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Str;

/** Calculer les moyennes pondérées, mentions et rangs d'une classe. */
class CalculateClassAverages
{
    /** @return array<string, mixed> */
    public function calculate(SchoolClass $schoolClass, Period $period): array
    {
        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->where('class_id', $schoolClass->class_id)
                ->where('school_year_id', $period->school_year_id))
            ->with(['grades' => fn ($query) => $query
                ->whereHas('assessment', fn ($assessmentQuery) => $assessmentQuery
                    ->where('period_id', $period->period_id)
                    ->whereHas('assignment', fn ($assignmentQuery) => $assignmentQuery
                        ->where('class_id', $schoolClass->class_id)))
                ->with('assessment.assignment.subject')])
            ->get();

        $rows = $students->map(function (Student $student): array {
            $subjectResults = [];

            foreach ($student->grades as $grade) {
                $subject = $grade->assessment->assignment->subject;
                $subjectId = $subject->subject_id;

                $subjectResults[$subjectId] ??= [
                    'id' => $subjectId,
                    'name' => $subject->name,
                    'coefficient' => (int) $subject->coefficient,
                    'total' => 0.0,
                    'grades_count' => 0,
                ];

                $subjectResults[$subjectId]['total'] += (float) $grade->value;
                $subjectResults[$subjectId]['grades_count']++;
            }

            $weightedTotal = 0.0;
            $coefficientTotal = 0;

            foreach ($subjectResults as &$subjectResult) {
                $subjectResult['average'] = round(
                    $subjectResult['total'] / $subjectResult['grades_count'],
                    2,
                );
                unset($subjectResult['total']);
                $weightedTotal += $subjectResult['average'] * $subjectResult['coefficient'];
                $coefficientTotal += $subjectResult['coefficient'];
            }
            unset($subjectResult);

            $average = $coefficientTotal > 0
                ? round($weightedTotal / $coefficientTotal, 2)
                : null;

            return [
                'student_id' => $student->student_id,
                'matricule' => $student->matricule,
                'last_name' => $student->last_name,
                'first_name' => $student->first_name,
                'average' => $average,
                'mention' => $this->mention($average),
                'rank' => null,
                'subjects' => array_values($subjectResults),
            ];
        })->all();

        usort($rows, function (array $first, array $second): int {
            if ($first['average'] !== $second['average']) {
                if ($first['average'] === null) {
                    return 1;
                }

                if ($second['average'] === null) {
                    return -1;
                }

                return $second['average'] <=> $first['average'];
            }

            $lastNameOrder = strcmp($this->frenchSortKey($first['last_name']), $this->frenchSortKey($second['last_name']));

            if ($lastNameOrder !== 0) {
                return $lastNameOrder;
            }

            $firstNameOrder = strcmp($this->frenchSortKey($first['first_name']), $this->frenchSortKey($second['first_name']));

            return $firstNameOrder !== 0
                ? $firstNameOrder
                : $first['student_id'] <=> $second['student_id'];
        });

        $rank = 0;

        foreach ($rows as &$row) {
            if ($row['average'] !== null) {
                $row['rank'] = ++$rank;
            }
        }
        unset($row);

        return [
            'class' => [
                'id' => $schoolClass->class_id,
                'name' => $schoolClass->name,
                'level' => $schoolClass->level,
            ],
            'period' => [
                'id' => $period->period_id,
                'label' => $period->label,
            ],
            'students_count' => count($rows),
            'students_with_average_count' => $rank,
            'students' => $rows,
        ];
    }

    private function mention(?float $average): ?string
    {
        if ($average === null) {
            return null;
        }

        return match (true) {
            $average >= 0 && $average <= 7 => 'Médiocre',
            $average > 7 && $average < 10 => 'Insuffisant',
            $average >= 10 && $average < 12 => 'Passable',
            $average >= 12 && $average < 14 => 'Assez bien',
            $average >= 14 && $average < 16 => 'Bien',
            $average >= 16 && $average < 18 => 'Très bien',
            $average >= 18 && $average <= 20 => 'Excellent',
            default => null,
        };
    }

    private function frenchSortKey(string $value): string
    {
        return Str::ascii(mb_strtolower($value));
    }
}
