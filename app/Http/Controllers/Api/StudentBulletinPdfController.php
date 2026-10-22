<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Student;
use App\Services\Gradebook\BuildStudentBulletin;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Générer le bulletin PDF d'un élève pour une période scolaire. */
class StudentBulletinPdfController extends Controller
{
    public function __invoke(
        Request $request,
        Student $student,
        Period $period,
        BuildStudentBulletin $buildBulletin,
    ): Response {
        if ($request->user()->role === 'PARENT') {
            abort_unless(
                $request->user()->parentAccount?->students()->whereKey($student->student_id)->exists(),
                404,
            );
        }

        $bulletin = $buildBulletin->build($student, $period);
        $filename = 'bulletin-'.Str::slug($student->matricule).'-'.Str::slug($period->label).'.pdf';

        return Pdf::loadView('gradebook.bulletins.print', [
            'bulletin' => $bulletin,
            'schoolName' => config('app.school_name', 'ScolaPlus'),
            'isPdf' => true,
        ])->setPaper('a4')->download($filename);
    }
}
