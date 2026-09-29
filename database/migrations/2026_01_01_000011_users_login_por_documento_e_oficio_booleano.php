<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users: CPF passa a servir para o login, junto com o NIP.
        // O e-mail NAO sai aqui: o login atual ainda depende dele. A remocao
        // de email e email_verified_at vai em migration propria, na tarefa de login.
        Schema::table('users', function (Blueprint $table) {
            $table->char('cpf', 11)->nullable()->unique();
        });

        // documentos: oficio judicial anexo vira sim/nao. O legado gravava "SIM" ou vazio.
        DB::statement("ALTER TABLE documentos ALTER COLUMN oficio_judicial_anexo
            TYPE boolean USING (UPPER(TRIM(COALESCE(oficio_judicial_anexo, ''))) = 'SIM')");
        DB::statement('ALTER TABLE documentos ALTER COLUMN oficio_judicial_anexo SET DEFAULT false');
        DB::statement('ALTER TABLE documentos ALTER COLUMN oficio_judicial_anexo SET NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE documentos ALTER COLUMN oficio_judicial_anexo DROP NOT NULL');
        DB::statement('ALTER TABLE documentos ALTER COLUMN oficio_judicial_anexo DROP DEFAULT');
        DB::statement("ALTER TABLE documentos ALTER COLUMN oficio_judicial_anexo
            TYPE text USING (CASE WHEN oficio_judicial_anexo THEN 'SIM' ELSE '' END)");

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropColumn('cpf');
        });
    }
};
