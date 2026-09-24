<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'nip' => $user->nip,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'nip' => $user->nip,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_nonexistent_nip(): void
    {
        User::factory()->create();

        $this->post('/login', [
            'nip' => '99999999',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_nip_is_required_to_authenticate(): void
    {
        $response = $this->post('/login', [
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('nip');
        $this->assertGuest();
    }

    public function test_email_does_not_work_as_login_identifier(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Sem 'nip' no corpo da requisição, a validação falha antes de
        // qualquer tentativa de autenticação — provando que mandar e-mail
        // no lugar do NIP não loga o usuário.
        $response->assertSessionHasErrors('nip');
        $this->assertGuest();
    }

    public function test_login_credentials_error_message_is_generic(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'nip' => $user->nip,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['nip' => trans('auth.failed')]);
    }

    public function test_login_is_rate_limited_by_nip(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'nip' => $user->nip,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'nip' => $user->nip,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('nip');
        $this->assertGuest();
    }

    public function test_rate_limit_key_is_scoped_to_nip_not_only_ip(): void
    {
        $throttled = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'nip' => $throttled->nip,
                'password' => 'wrong-password',
            ]);
        }

        // Mesmo IP de teste, NIP diferente: não deve estar bloqueado.
        $response = $this->post('/login', [
            'nip' => $other->nip,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_session_is_regenerated_after_login(): void
    {
        $user = User::factory()->create();

        $this->get('/login');
        $idBeforeLogin = session()->getId();

        $this->post('/login', [
            'nip' => $user->nip,
            'password' => 'password',
        ]);

        $this->assertNotSame($idBeforeLogin, session()->getId());
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
