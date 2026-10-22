<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Profil parent lié à son compte et à un ou plusieurs élèves. */
#[Fillable(['user_id', 'first_name', 'last_name', 'telephone', 'address'])]
class ParentAccount extends Model
{
    protected $table = 'parents';

    protected $primaryKey = 'parent_id';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
            ->withPivot('relationship');
    }
}
