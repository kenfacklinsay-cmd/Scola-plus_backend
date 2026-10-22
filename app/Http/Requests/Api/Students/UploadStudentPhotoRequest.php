<?php

namespace App\Http\Requests\Api\Students;

use Illuminate\Foundation\Http\FormRequest;

/** Valider une photo d'élève avant son stockage privé. */
class UploadStudentPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updatePhoto', $this->route('student')) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=3000,max_height=3000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photo.required' => 'Veuillez choisir une photo.',
            'photo.image' => 'Le fichier doit être une image valide.',
            'photo.mimes' => 'La photo doit être au format JPG, PNG ou WebP.',
            'photo.max' => 'La photo ne peut pas dépasser 5 Mo.',
            'photo.dimensions' => 'La photo ne peut pas dépasser 3000 pixels de largeur ou de hauteur.',
        ];
    }
}
