<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_can_be_requested_without_revealing_unknown_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'parent@example.test']);
        $message = 'Si cette adresse correspond à un compte, un lien de réinitialisation lui sera envoyé.';

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', $message);

        $this->postJson('/api/forgot-password', ['email' => 'unknown@example.test'])
            ->assertOk()
            ->assertJsonPath('message', $message);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_email_links_to_the_frontend_reset_screen(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'parent@example.test']);

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return str_contains($mail->actionUrl, 'http://localhost:5173/reset-password?')
                && str_contains($mail->actionUrl, 'email=parent%40example.test');
        });
    }

    public function test_password_can_be_reset_once_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'parent@example.test',
            'must_change_password' => true,
        ]);
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $payload = [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'NouveauMotDePasse2026!',
                'password_confirmation' => 'NouveauMotDePasse2026!',
            ];

            $this->postJson('/api/reset-password', $payload)
                ->assertOk()
                ->assertJsonPath('message', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');

            $this->assertTrue(Hash::check('NouveauMotDePasse2026!', $user->fresh()->password));
            $this->assertFalse($user->fresh()->must_change_password);

            $this->postJson('/api/reset-password', $payload)->assertUnprocessable();

            return true;
        });
    }
}
