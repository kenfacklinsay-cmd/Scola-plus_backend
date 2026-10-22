<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Période de notation rattachée à une année scolaire. */
#[Fillable(['label', 'start_date', 'end_date', 'school_year_id'])]
class Period extends Model
{
    protected $primaryKey = 'period_id';

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id', 'school_year_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'period_id', 'period_id');
    }
}
