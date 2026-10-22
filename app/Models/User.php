<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'firstname',
    'lastname',
    'email',
    'telephone',
    'password',
    'role',
    'status',
    'must_change_password',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function parentAccount(): HasOne
    {
        return $this->hasOne(ParentAccount::class, 'user_id');
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(Teacher::class, 'user_id');
    }

    /**
     * Conversion des attributs.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Nom complet de l'utilisateur.
     */
    protected function nomComplet(): Attribute
    {
        return Attribute::make(
            get: fn () => trim($this->firstname.' '.$this->lastname),
        );
    }

    /**
     * Vérifier si le compte est actif.
     */
    public function estActif(): bool
    {
        return $this->status === 'ACTIF';
    }

    /**
     * Vérifier le rôle de l'utilisateur.
     */
    public function aRole(string $role): bool
    {
        return $this->role === $role;
    }
}
