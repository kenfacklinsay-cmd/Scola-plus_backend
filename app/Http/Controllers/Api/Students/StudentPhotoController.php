<?php

namespace App\Http\Controllers\Api\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Students\UploadStudentPhotoRequest;
use App\Models\Student;
use App\Services\Students\StudentPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Mime\MimeTypes;

/** Gérer le dépôt et la consultation autorisée des photos d'élèves. */
class StudentPhotoController extends Controller
{
    public function show(Student $student): BinaryFileResponse
    {
        abort_unless($student->photo && Storage::disk('local')->exists($student->photo), 404);

        $extension = strtolower(pathinfo($student->photo, PATHINFO_EXTENSION));
        $mimeType = MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream';

        return response()->file(Storage::disk('local')->path($student->photo), [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }

    public function store(
        UploadStudentPhotoRequest $request,
        Student $student,
        StudentPhotoService $photos,
    ): JsonResponse {
        $student = $photos->replace($student, $request->file('photo'));

        return response()->json([
            'message' => 'La photo de l’élève a été enregistrée.',
            'has_photo' => filled($student->photo),
        ]);
    }

    public function destroy(Student $student, StudentPhotoService $photos): JsonResponse
    {
        $photos->delete($student);

        return response()->json(['message' => 'La photo de l’élève a été supprimée.']);
    }
}
