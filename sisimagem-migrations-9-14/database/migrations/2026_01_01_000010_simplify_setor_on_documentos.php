<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // documentos: sai o setor responsavel; o setor de origem vira o setor dono (setor_id)
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign(['setor_responsavel_id']);
            $table->dropColumn('setor_responsavel_id');
            $table->dropForeign(['setor_origem_id']);
        });

        DB::statement('ALTER TABLE documentos RENAME COLUMN setor_origem_id TO setor_id');

        Schema::table('documentos', function (Blueprint $table) {
            $table->foreign('setor_id')->references('id')->on('setores')->restrictOnDelete();
        });

        DB::statement('CREATE INDEX idx_doc_setor_criado ON documentos (setor_id, criado_em DESC)');

        // users: apagar setor com usuario vinculado passa a ser recusado
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['setor_id']);
            $table->foreign('setor_id')->references('id')->on('setores')->restrictOnDelete();
        });

        // Usuarios criados antes desta regra (so existem os de teste) ficariam sem setor.
        // Viram admin para nao violarem a trava nova.
        DB::statement("UPDATE users SET perfil = 'admin' WHERE setor_id IS NULL");

        // Todo usuario que nao e admin precisa de setor, inclusive o perfil padrao.
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_setor_perfil_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_setor_perfil_check
            CHECK (perfil = 'admin' OR setor_id IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_setor_perfil_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_setor_perfil_check
            CHECK (perfil IN ('admin', 'padrao') OR setor_id IS NOT NULL)");

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['setor_id']);
            $table->foreign('setor_id')->references('id')->on('setores')->nullOnDelete();
        });

        DB::statement('DROP INDEX IF EXISTS idx_doc_setor_criado');

        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign(['setor_id']);
        });

        DB::statement('ALTER TABLE documentos RENAME COLUMN setor_id TO setor_origem_id');

        Schema::table('documentos', function (Blueprint $table) {
            $table->foreign('setor_origem_id')->references('id')->on('setores')->nullOnDelete();
            $table->smallInteger('setor_responsavel_id')->nullable();
            $table->foreign('setor_responsavel_id')->references('id')->on('setores')->nullOnDelete();
        });
    }
};
