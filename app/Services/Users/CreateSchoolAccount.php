<?php

namespace App\Services\Users;

use App\Models\ParentAccount;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Créer les comptes de l'école avec leur profil métier dans une transaction. */
class CreateSchoolAccount
{
    /**
     * Créer un compte administratif non privilégié.
     *
     * @param  array{firstname: string, lastname: string, email: string, telephone?: ?string, role: string, initial_password: string}  $data
     */
    public function createStaff(array $data): User
    {
        return DB::transaction(fn () => User::query()->create([
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
            'telephone' => $data['telephone'] ?? null,
            'password' => $data['initial_password'],
            'role' => $data['role'],
            'status' => 'ACTIF',
            'must_change_password' => true,
        ]));
    }

    /**
     * Créer un compte enseignant avec son profil associé.
     *
     * @param  array{firstname: string, lastname: string, email: string, telephone?: ?string, teacher_registration_code: string, specialty: string, initial_password: string}  $data
     */
    public function createTeacher(array $data): Teacher
    {
        return DB::transaction(function () use ($data): Teacher {
            $user = User::query()->create([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'email' => $data['email'],
                'telephone' => $data['telephone'] ?? null,
                'password' => $data['initial_password'],
                'role' => 'ENSEIGNANT',
                'status' => 'ACTIF',
                'must_change_password' => true,
            ]);

            return Teacher::query()->create([
                'user_id' => $user->id,
                'teacher_registration_code' => $data['teacher_registration_code'],
                'specialty' => $data['specialty'],
            ])->load('user');
        });
    }

    /**
     * Créer un compte parent à partir de sa fiche déjà liée à un ou plusieurs élèves.
     *
     * @param  array{email: string, initial_password: string}  $data
     */
    public function createParent(ParentAccount $parent, array $data): User
    {
        return DB::transaction(function () use ($parent, $data): User {
            $lockedParent = ParentAccount::query()
                ->whereKey($parent->parent_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($lockedParent->user_id !== null, 409, 'Cette fiche parent possède déjà un compte utilisateur.');
            abort_if(! $lockedParent->students()->exists(), 422, 'Un compte parent doit être rattaché à au moins un élève.');

            $user = User::query()->create([
                'firstname' => $lockedParent->first_name,
                'lastname' => $lockedParent->last_name,
                'email' => $data['email'],
                'password' => $data['initial_password'],
                'role' => 'PARENT',
                'status' => 'ACTIF',
                'must_change_password' => true,
            ]);

            $lockedParent->update(['user_id' => $user->id]);

            return $user;
        });
    }
}
