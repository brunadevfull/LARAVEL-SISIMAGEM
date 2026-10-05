<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setores com id fixo: o perfil padrao depende do id 1.
     */
    private const SETORES = [1 => 'PAPEM-41', 2 => 'PAPEM-42'];

    public function up(): void
    {
        // Setor com id ou nome diferente do esperado precisa de decisao
        // manual antes; nao ha como encaixar os ids fixos sem mexer em dado.
        $existentes = DB::table('setores')->orderBy('id')->pluck('nome', 'id')->all();
        $inesperados = array_diff_assoc($existentes, self::SETORES);

        if ($inesperados !== []) {
            throw new RuntimeException(
                'Setores fora do esperado (id => nome): '.json_encode($inesperados, JSON_UNESCAPED_UNICODE)
                .'. Esperado apenas 1 => PAPEM-41 e 2 => PAPEM-42.'
            );
        }

        // A migration 10 ja transformou em admin quem estava sem setor.
        // Sobra conferir perfil padrao ligado a outro setor.
        $padraoForaDoPapem41 = DB::table('users')
            ->where('perfil', 'padrao')
            ->where(fn (Builder $q) => $q->whereNull('setor_id')->orWhere('setor_id', '<>', 1))
            ->pluck('id')
            ->all();

        if ($padraoForaDoPapem41 !== []) {
            throw new RuntimeException(
                'Usuarios com perfil padrao fora do PAPEM-41 (ids): '.implode(', ', $padraoForaDoPapem41)
            );
        }

        // Validacoes acima vem antes de qualquer escrita.
        foreach (self::SETORES as $id => $nome) {
            if (! array_key_exists($id, $existentes)) {
                DB::table('setores')->insert(['id' => $id, 'nome' => $nome]);
            }
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence('setores', 'id'), GREATEST(2, (SELECT MAX(id) FROM setores)))"
        );

        Schema::table('setores', function (Blueprint $table) {
            $table->unique('nome');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_padrao_papem41_check
            CHECK (perfil <> 'padrao' OR setor_id = 1)");

        // O UNIQUE de sha256 ja cria indice; este era redundante.
        DB::statement('DROP INDEX IF EXISTS idx_arquivo_sha256');
    }

    public function down(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS idx_arquivo_sha256 ON arquivos (sha256)');

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_padrao_papem41_check');

        Schema::table('setores', function (Blueprint $table) {
            $table->dropUnique(['nome']);
        });

        // So apaga setor que nada referencia; o resto fica.
        DB::table('setores')
            ->whereIn('id', array_keys(self::SETORES))
            ->whereNotExists(fn (Builder $q) => $q->from('users')->whereColumn('users.setor_id', 'setores.id'))
            ->whereNotExists(fn (Builder $q) => $q->from('tipos_documento')->whereColumn('tipos_documento.setor_id', 'setores.id'))
            ->whereNotExists(fn (Builder $q) => $q->from('documentos')->whereColumn('documentos.setor_id', 'setores.id'))
            ->delete();
    }
};
