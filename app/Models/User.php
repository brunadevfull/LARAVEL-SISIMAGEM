<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'nip'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * `perfil`, `senha_temporaria`, `bloqueado_em` e `tentativas_login`
     * representam estado de autorização/autenticação e ficam fora do
     * Fillable de propósito — nunca devem ser atribuídos por
     * mass assignment (`$request->all()`, `fill()`, `create()` com
     * entrada bruta da requisição), só de forma explícita pelo código
     * responsável (ex.: tela de administração de usuários).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'senha_temporaria' => 'boolean',
            'bloqueado_em' => 'datetime',
            'tentativas_login' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->perfil === 'adm';
    }

    public function isPapem40(): bool
    {
        return $this->perfil === 'papem40';
    }

    public function isSasm(): bool
    {
        return $this->perfil === 'sasm';
    }

    public function isPadrao(): bool
    {
        return $this->perfil === 'padrao';
    }
}
