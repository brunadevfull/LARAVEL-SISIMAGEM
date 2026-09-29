<?php

namespace Tests\Unit;

use App\Models\Setor;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Testes do Model User que não tocam banco — perfil, casts e helpers
 * de perfil. `perfil`, `setor_id`, `senha_temporaria`, `bloqueado_em` e
 * `tentativas_login` ficam fora do Fillable de propósito, então os
 * valores são atribuídos direto na propriedade, sem passar por fill().
 */
class UserTest extends TestCase
{
    /**
     * Perfil => helper que deve responder true. `adm` é o nome antigo e
     * não pode ser reconhecido por nenhum helper.
     *
     * @return array<string, array{string, ?string}>
     */
    public static function perfis(): array
    {
        return [
            'admin' => ['admin', 'isAdmin'],
            'gestor_setor' => ['gestor_setor', 'isGestorSetor'],
            'operador_setor' => ['operador_setor', 'isOperadorSetor'],
            'padrao' => ['padrao', 'isPadrao'],
            'adm (antigo)' => ['adm', null],
        ];
    }

    #[DataProvider('perfis')]
    public function test_only_the_matching_helper_returns_true(string $perfil, ?string $esperado): void
    {
        $user = new User;
        $user->perfil = $perfil;

        foreach (['isAdmin', 'isGestorSetor', 'isOperadorSetor', 'isPadrao'] as $helper) {
            $this->assertSame($helper === $esperado, $user->{$helper}(), "{$helper}() com perfil {$perfil}");
        }
    }

    public function test_old_profile_helpers_no_longer_exist(): void
    {
        $this->assertFalse(method_exists(User::class, 'isSasm'));
        $this->assertFalse(method_exists(User::class, 'isPapem40'));
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
     * Teste de regressão para a decisão de mass assignment: estado de
     * autorização/autenticação nunca pode voltar ao Fillable por acidente.
     */
    public function test_authorization_state_is_not_fillable(): void
    {
        $user = new User;

        $this->assertTrue($user->isFillable('nip'));
        $this->assertTrue($user->isFillable('cpf'));

        foreach (['perfil', 'setor_id', 'senha_temporaria', 'bloqueado_em', 'tentativas_login'] as $campo) {
            $this->assertFalse($user->isFillable($campo), "{$campo} não pode ser fillable");
        }
    }

    public function test_perfil_is_ignored_on_mass_assignment(): void
    {
        $user = new User(['name' => 'Fulano', 'perfil' => 'admin', 'setor_id' => Setor::PAPEM_42]);

        $this->assertSame('Fulano', $user->name);
        $this->assertNull($user->perfil);
        $this->assertNull($user->setor_id);
        $this->assertFalse($user->isAdmin());
    }

    public function test_setor_relation_belongs_to_setor(): void
    {
        $relation = (new User)->setor();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Setor::class, $relation->getRelated());
        $this->assertSame('setor_id', $relation->getForeignKeyName());
    }
}
