<?php

namespace App\Policies;

use App\Models\User;

/** Autoriser l'administration des référentiels de l'établissement. */
class SchoolClassPolicy
{
    public function manageSchoolStructure(User $user): bool
    {
        return $user->aRole('DIRECTEUR') || $user->aRole('SECRETARIAT');
    }
}
