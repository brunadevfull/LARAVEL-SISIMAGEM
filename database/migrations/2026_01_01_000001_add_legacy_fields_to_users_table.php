<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip')->nullable()->unique()->after('email');
            $table->integer('uri_legado')->nullable()->unique();
            $table->string('perfil')->default('padrao');
            $table->smallInteger('tentativas_login')->default(0);
            $table->timestampTz('bloqueado_em')->nullable();
            $table->boolean('senha_temporaria')->default(true);
        });

        // CHECK constraint — o Schema builder do Laravel não expõe CHECK
        // diretamente, então vai por SQL puro.
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_perfil_check
             CHECK (perfil IN ('adm', 'papem40', 'sasm', 'padrao'))"
        );

        DB::statement("CREATE INDEX idx_user_perfil ON users(perfil)");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nip', 'uri_legado', 'perfil',
                'tentativas_login', 'bloqueado_em', 'senha_temporaria',
            ]);
        });
    }
};
