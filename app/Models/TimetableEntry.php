<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Créneau hebdomadaire associé à une affectation pédagogique. */
#[Fillable(['assignment_id', 'day_of_week', 'start_time', 'end_time', 'room'])]
class TimetableEntry extends Model
{
    protected $primaryKey = 'timetable_entry_id';

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'assignment_id', 'assignment_id');
    }
}
