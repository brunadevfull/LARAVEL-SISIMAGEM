<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipos de documento passam a pertencer a um setor.
        // "Oficio" pode existir uma vez em cada setor.
        Schema::table('tipos_documento', function (Blueprint $table) {
            $table->dropUnique(['nome']);
            $table->smallInteger('setor_id');
            $table->foreign('setor_id')->references('id')->on('setores')->restrictOnDelete();
            $table->unique(['setor_id', 'nome']);
        });

        // Usuario ganha setor e os perfis novos.
        Schema::table('users', function (Blueprint $table) {
            $table->smallInteger('setor_id')->nullable();
            $table->foreign('setor_id')->references('id')->on('setores')->nullOnDelete();
        });

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_perfil_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_perfil_check
            CHECK (perfil IN ('admin', 'gestor_setor', 'operador_setor', 'padrao'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_setor_perfil_check
            CHECK (perfil IN ('admin', 'padrao') OR setor_id IS NOT NULL)");

        // Arquivos ficam dentro do banco: sai o caminho, conteudo e hash sao obrigatorios.
        Schema::table('arquivos', function (Blueprint $table) {
            $table->dropColumn('caminho');
        });
        DB::statement('ALTER TABLE arquivos ALTER COLUMN conteudo SET NOT NULL');
        DB::statement('ALTER TABLE arquivos ALTER COLUMN sha256 SET NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE arquivos ALTER COLUMN sha256 DROP NOT NULL');
        DB::statement('ALTER TABLE arquivos ALTER COLUMN conteudo DROP NOT NULL');
        Schema::table('arquivos', function (Blueprint $table) {
            $table->text('caminho')->nullable();
        });

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_setor_perfil_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_perfil_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_perfil_check
            CHECK (perfil IN ('adm', 'papem40', 'sasm', 'padrao'))");
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['setor_id']);
            $table->dropColumn('setor_id');
        });

        Schema::table('tipos_documento', function (Blueprint $table) {
            $table->dropUnique(['setor_id', 'nome']);
            $table->dropForeign(['setor_id']);
            $table->dropColumn('setor_id');
            $table->unique('nome');
        });
    }
};
