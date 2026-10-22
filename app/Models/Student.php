<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'matricule',
    'last_name',
    'first_name',
    'birth_date',
    'birth_place',
    'gender',
    'address',
    'photo',
])]
class Student extends Model
{
    protected $table = 'students';

    protected $primaryKey = 'student_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    /**
     * Récupérer toutes les inscriptions de l'élève.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(
            Enrollment::class,
            'student_id',
            'student_id'
        );
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentAccount::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot('relationship');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'student_id', 'student_id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class, 'student_id', 'student_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'student_id', 'student_id');
    }

    /**
     * Récupérer la classe de la dernière inscription.
     */
    public function currentClass(): ?SchoolClass
    {
        return $this->enrollments()
            ->with('schoolClass')
            ->latest('created_at')
            ->first()
            ?->schoolClass;
    }
}
