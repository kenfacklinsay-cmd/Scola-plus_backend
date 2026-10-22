<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Profil enseignant associé à un compte utilisateur. */
#[Fillable(['user_id', 'teacher_registration_code', 'specialty'])]
class Teacher extends Model
{
    protected $primaryKey = 'teacher_id';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'teacher_id', 'teacher_id');
    }

    public function titularClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'titular_teacher_id', 'teacher_id');
    }
}
