<?php

namespace App\Services\Students;

use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Stocker les photos d'élèves hors du répertoire public. */
class StudentPhotoService
{
    public function replace(Student $student, UploadedFile $photo): Student
    {
        $newPath = $photo->store('students/'.$student->student_id.'/photos', 'local');

        if (! is_string($newPath)) {
            throw new RuntimeException('Le stockage de la photo a échoué.');
        }

        $oldPath = $student->photo;

        try {
            DB::transaction(fn () => $student->update(['photo' => $newPath]));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);

            throw $exception;
        }

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $student->refresh();
    }

    public function delete(Student $student): void
    {
        $oldPath = $student->photo;

        if (! $oldPath) {
            return;
        }

        DB::transaction(fn () => $student->update(['photo' => null]));
        Storage::disk('local')->delete($oldPath);
    }
}
