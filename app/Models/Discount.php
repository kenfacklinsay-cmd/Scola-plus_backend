<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Réduction accordée à un élève sur les frais totaux de sa classe. */
#[Fillable(['student_id', 'tuition_fee_id', 'amount', 'reason'])]
class Discount extends Model
{
    protected $primaryKey = 'discount_id';

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function tuitionFee(): BelongsTo
    {
        return $this->belongsTo(TuitionFee::class, 'tuition_fee_id', 'tuition_fee_id');
    }
}
