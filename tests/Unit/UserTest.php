<?php

namespace Tests\Unit;

use App\Models\User;
use Carbon\CarbonInterface;
use Tests\TestCase;

/**
 * Testes do Model User que não tocam banco — perfil, casts e helpers
 * de perfil. `perfil`, `senha_temporaria`, `bloqueado_em` e
 * `tentativas_login` ficam fora do Fillable de propósito, então os
 * valores são atribuídos direto na propriedade, sem passar por fill().
 */
class UserTest extends TestCase
{
    public function test_is_admin_returns_true_only_for_adm_profile(): void
    {
        $admin = new User;
        $admin->perfil = 'adm';

        $outro = new User;
        $outro->perfil = 'papem40';

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($outro->isAdmin());
    }

    public function test_is_papem40_returns_true_only_for_papem40_profile(): void
    {
        $papem40 = new User;
        $papem40->perfil = 'papem40';

        $outro = new User;
        $outro->perfil = 'sasm';

        $this->assertTrue($papem40->isPapem40());
        $this->assertFalse($outro->isPapem40());
    }

    public function test_is_sasm_returns_true_only_for_sasm_profile(): void
    {
        $sasm = new User;
        $sasm->perfil = 'sasm';

        $outro = new User;
        $outro->perfil = 'padrao';

        $this->assertTrue($sasm->isSasm());
        $this->assertFalse($outro->isSasm());
    }

    public function test_is_padrao_returns_true_only_for_padrao_profile(): void
    {
        $padrao = new User;
        $padrao->perfil = 'padrao';

        $outro = new User;
        $outro->perfil = 'adm';

        $this->assertTrue($padrao->isPadrao());
        $this->assertFalse($outro->isPadrao());
    }

    public function test_senha_temporaria_is_cast_to_boolean(): void
    {
        $user = new User;
        $user->senha_temporaria = '1';

        $this->assertIsBool($user->senha_temporaria);
        $this->assertTrue($user->senha_temporaria);
    }

    public function test_tentativas_login_is_cast_to_integer(): void
    {
        $user = new User;
        $user->tentativas_login = '3';

        $this->assertIsInt($user->tentativas_login);
        $this->assertSame(3, $user->tentativas_login);
    }

    public function test_bloqueado_em_is_cast_to_datetime(): void
    {
        $user = new User;
        $user->bloqueado_em = '2026-01-15 10:00:00';

        $this->assertInstanceOf(CarbonInterface::class, $user->bloqueado_em);
    }

    /**
     * Teste de regressão para a decisão de mass assignment: `perfil`,
     * `senha_temporaria`, `bloqueado_em` e `tentativas_login` representam
     * estado de autorização/autenticação e nunca podem voltar ao Fillable
     * por acidente. `nip` é o único campo legado que é fillable.
     */
    public function test_only_nip_is_fillable_among_the_legacy_fields(): void
    {
        $user = new User;

        $this->assertTrue($user->isFillable('nip'));
        $this->assertFalse($user->isFillable('perfil'));
        $this->assertFalse($user->isFillable('senha_temporaria'));
        $this->assertFalse($user->isFillable('bloqueado_em'));
        $this->assertFalse($user->isFillable('tentativas_login'));
    }
}
