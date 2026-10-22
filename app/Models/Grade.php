<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Note obtenue par un élève à une évaluation, sur une échelle de 20. */
#[Fillable(['student_id', 'assessment_id', 'value', 'appreciation'])]
class Grade extends Model
{
    protected $primaryKey = 'grade_id';

    protected function casts(): array
    {
        return ['value' => 'decimal:2'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id', 'assessment_id');
    }
}
