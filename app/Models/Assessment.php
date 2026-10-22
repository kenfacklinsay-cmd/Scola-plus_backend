<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Évaluation d'une matière pour une classe et une période scolaire. */
#[Fillable(['assignment_id', 'period_id', 'label', 'assessment_date'])]
class Assessment extends Model
{
    protected $primaryKey = 'assessment_id';

    protected function casts(): array
    {
        return ['assessment_date' => 'date'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'assignment_id', 'assignment_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'period_id', 'period_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'assessment_id', 'assessment_id');
    }
}
