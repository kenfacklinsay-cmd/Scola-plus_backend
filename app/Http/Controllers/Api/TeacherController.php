<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TeacherResource;
use App\Models\Teacher;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Fournir les enseignants actifs pour les formulaires de structure scolaire. */
class TeacherController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $teachers = Teacher::query()
            ->whereHas('user', fn ($query) => $query
                ->where('role', 'ENSEIGNANT')
                ->where('status', 'ACTIF'))
            ->with('user')
            ->orderBy('teacher_registration_code')
            ->get();

        return TeacherResource::collection($teachers);
    }
}
