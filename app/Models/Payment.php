<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Paiement effectué par un élève pour une tranche de frais. */
#[Fillable(['student_id', 'installment_id', 'receipt_number', 'amount', 'payment_date', 'payment_method', 'status'])]
class Payment extends Model
{
    protected $primaryKey = 'payment_id';

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_date' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class, 'installment_id', 'installment_id');
    }
}
