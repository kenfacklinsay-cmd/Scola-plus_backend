<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Matière enseignée, avec son coefficient de calcul. */
#[Fillable(['name', 'coefficient'])]
class Subject extends Model
{
    protected $primaryKey = 'subject_id';

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'subject_id', 'subject_id');
    }
}
