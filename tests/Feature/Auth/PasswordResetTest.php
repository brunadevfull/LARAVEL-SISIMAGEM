<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recuperação pública de senha por e-mail está desativada: login é por
 * NIP, e-mail não é credencial, e reset de senha será administrativo
 * (feito pelo Administrador), não por link enviado por e-mail. Estes
 * testes são de regressão — garantem que o fluxo continua indisponível.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_is_not_available(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(404);
    }

    public function test_password_reset_link_cannot_be_requested(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertStatus(404);
    }

    public function test_reset_password_screen_is_not_available(): void
    {
        $response = $this->get('/reset-password/qualquer-token');

        $response->assertStatus(404);
    }

    public function test_password_cannot_be_reset_via_public_token_route(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/reset-password', [
            'token' => 'qualquer-token',
            'email' => $user->email,
            'password' => 'nova-senha',
            'password_confirmation' => 'nova-senha',
        ]);

        $response->assertStatus(404);
    }
}
