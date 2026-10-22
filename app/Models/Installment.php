<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tranche de paiement prévue pour des frais de scolarité. */
#[Fillable(['tuition_fee_id', 'label', 'amount', 'due_date'])]
class Installment extends Model
{
    protected $primaryKey = 'installment_id';

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date'];
    }

    public function tuitionFee(): BelongsTo
    {
        return $this->belongsTo(TuitionFee::class, 'tuition_fee_id', 'tuition_fee_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'installment_id', 'installment_id');
    }
}
