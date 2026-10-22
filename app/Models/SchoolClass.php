<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Classe scolaire ouverte pendant une année scolaire donnée. */
#[Fillable(['name', 'level', 'capacity', 'school_year_id', 'titular_teacher_id'])]
class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $primaryKey = 'class_id';

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id', 'school_year_id');
    }

    public function titularTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'titular_teacher_id', 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id', 'class_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'class_id', 'class_id');
    }

    public function tuitionFees(): HasMany
    {
        return $this->hasMany(TuitionFee::class, 'class_id', 'class_id');
    }
}
