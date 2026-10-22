<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'start_date', 'end_date', 'status'])]
class SchoolYear extends Model
{
    protected $table = 'school_years';

    protected $primaryKey = 'school_year_id';

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'school_year_id', 'school_year_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class, 'school_year_id', 'school_year_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'school_year_id', 'school_year_id');
    }

    public function estEnCours(): bool
    {
        return $this->status === 'ONGOING';
    }
}
