<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Envoyer un lien de réinitialisation et valider son jeton à usage unique. */
class PasswordResetController extends Controller
{
    public function sendLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
        ]);

        $status = Password::broker()->sendResetLink(['email' => Str::lower(trim($data['email']))]);

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Un lien vient d’être envoyé. Attendez une minute avant de faire une nouvelle demande.',
            ], 429);
        }

        if (! in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            throw ValidationException::withMessages([
                'email' => ['Impossible de traiter la demande de réinitialisation.'],
            ]);
        }

        return response()->json([
            'message' => 'Si cette adresse correspond à un compte, un lien de réinitialisation lui sera envoyé.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:12'],
        ]);

        $status = Password::broker()->reset(
            [
                'email' => Str::lower(trim($data['email'])),
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                ])->save();

                DB::table('sessions')->where('user_id', $user->getKey())->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['Ce lien de réinitialisation est invalide ou a expiré. Demandez un nouveau lien.'],
            ]);
        }

        return response()->json([
            'message' => 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.',
        ]);
    }
}
