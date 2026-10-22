<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Users\CreateParentAccountRequest;
use App\Http\Requests\Api\Users\CreateStaffAccountRequest;
use App\Http\Requests\Api\Users\CreateTeacherAccountRequest;
use App\Http\Requests\Api\Users\UpdateAccountStatusRequest;
use App\Http\Requests\Api\Users\UpdateOwnPasswordRequest;
use App\Http\Resources\Api\TeacherResource;
use App\Http\Resources\Api\UserResource;
use App\Models\ParentAccount;
use App\Models\Student;
use App\Models\User;
use App\Services\Users\CreateSchoolAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Créer les accès du personnel et des familles et gérer son mot de passe. */
class UserAccountController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()
                ->where('role', '!=', 'DIRECTEUR')
                ->orderBy('role')
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get(),
        );
    }

    public function createStaff(CreateStaffAccountRequest $request, CreateSchoolAccount $accounts): JsonResponse
    {
        $user = $accounts->createStaff($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function createTeacher(CreateTeacherAccountRequest $request, CreateSchoolAccount $accounts): JsonResponse
    {
        $teacher = $accounts->createTeacher($request->validated());

        return (new TeacherResource($teacher))->response()->setStatusCode(201);
    }

    public function createParent(
        CreateParentAccountRequest $request,
        ParentAccount $parent,
        CreateSchoolAccount $accounts,
    ): JsonResponse {
        $user = $accounts->createParent($parent, $request->validated());

        return (new UserResource($user->load('parentAccount.students')))->response()->setStatusCode(201);
    }

    public function updateStatus(UpdateAccountStatusRequest $request, User $account): UserResource
    {
        abort_if(
            $account->role === 'DIRECTEUR' || (int) $account->getKey() === (int) $request->user()->getKey(),
            403,
            'Vous ne pouvez pas suspendre ce compte.',
        );

        $account->forceFill(['status' => $request->validated('status')])->save();

        if ($account->status === 'INACTIF') {
            DB::table('sessions')->where('user_id', $account->getKey())->delete();
        }

        return new UserResource($account);
    }

    public function resetAccountPassword(Request $request, User $account): JsonResponse
    {
        abort_if(
            $account->role === 'DIRECTEUR' || (int) $account->getKey() === (int) $request->user()->getKey(),
            403,
            'Vous ne pouvez pas réinitialiser ce compte.',
        );

        $temporaryPassword = Str::password(18);

        DB::transaction(function () use ($account, $temporaryPassword): void {
            $account->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ])->save();

            DB::table('sessions')->where('user_id', $account->getKey())->delete();
        });

        return response()->json([
            'message' => 'Un mot de passe provisoire a été créé. Il ne sera affiché qu’une seule fois.',
            'data' => [
                'account_id' => $account->getKey(),
                'temporary_password' => $temporaryPassword,
            ],
        ]);
    }

    /** Ajouter un frère ou une sœur au profil d'une famille déjà rattachée à un compte. */
    public function linkStudent(Request $request, ParentAccount $parent, Student $student): JsonResponse
    {
        abort_if($parent->user_id === null, 409, 'Créez d’abord le compte parent avant de rattacher un autre élève.');

        $data = $request->validate([
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($parent, $student, $data): void {
            $parent->students()->syncWithoutDetaching([
                $student->student_id => ['relationship' => $data['relationship'] ?? null],
            ]);
        });

        return response()->json([
            'message' => 'L’élève est maintenant rattaché à ce compte parent.',
            'data' => [
                'parent_id' => $parent->parent_id,
                'student_id' => $student->student_id,
            ],
        ]);
    }

    public function updateOwnPassword(UpdateOwnPasswordRequest $request): JsonResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        return response()->json(['message' => 'Votre mot de passe a été modifié.']);
    }
}
