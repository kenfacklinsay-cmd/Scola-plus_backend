<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Montant total des frais applicable à une classe et une année scolaire. */
#[Fillable(['class_id', 'school_year_id', 'total_amount'])]
class TuitionFee extends Model
{
    protected $primaryKey = 'tuition_fee_id';

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2'];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id', 'class_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id', 'school_year_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'tuition_fee_id', 'tuition_fee_id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class, 'tuition_fee_id', 'tuition_fee_id');
    }
}
