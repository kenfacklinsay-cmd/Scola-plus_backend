<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Présence journalière d'un élève inscrit. */
#[Fillable(['enrollment_id', 'attendance_date', 'status', 'notes', 'marked_by'])]
class AttendanceRecord extends Model
{
    protected $primaryKey = 'attendance_id';

    protected function casts(): array
    {
        return ['attendance_date' => 'date'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id', 'enrollment_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
