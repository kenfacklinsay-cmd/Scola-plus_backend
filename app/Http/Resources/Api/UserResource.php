<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Exposer uniquement les informations publiques du compte connecté. */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'role' => $this->role,
            'status' => $this->status,
            'must_change_password' => $this->must_change_password,
        ];
    }
}
