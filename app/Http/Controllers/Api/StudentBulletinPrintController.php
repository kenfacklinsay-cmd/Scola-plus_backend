<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Student;
use App\Services\Gradebook\BuildStudentBulletin;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Afficher le bulletin imprimable d'un élève pour une période scolaire. */
class StudentBulletinPrintController extends Controller
{
    public function __invoke(Request $request, Student $student, Period $period, BuildStudentBulletin $buildBulletin): Response
    {
        if ($request->user()->role === 'PARENT') {
            abort_unless(
                $request->user()->parentAccount?->students()->whereKey($student->student_id)->exists(),
                404,
            );
        }

        $bulletin = $buildBulletin->build($student, $period);

        return response()->view('gradebook.bulletins.print', [
            'bulletin' => $bulletin,
            'schoolName' => config('app.school_name', 'ScolaPlus'),
            'isPdf' => false,
        ])->header('Content-Disposition', 'inline; filename="bulletin-'.$student->matricule.'-'.$period->period_id.'.html"');
    }
}
