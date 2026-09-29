<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement(<<<'SQL'
            CREATE INDEX idx_doc_busca ON documentos
            USING GIN (to_tsvector('portuguese',
                       coalesce(titulo,'') || ' ' ||
                       coalesce(beneficiario,'') || ' ' ||
                       coalesce(observacoes,'')))
        SQL);

        // Habilitar só se a busca real exigir trecho no meio da palavra:
        // DB::statement("CREATE INDEX idx_doc_benef_trgm ON documentos
        //                USING GIN (beneficiario gin_trgm_ops)");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_doc_busca');
    }
};
