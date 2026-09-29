<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->foreignId('documento_pai_id')->nullable()
                ->constrained('documentos')->restrictOnDelete();
            $table->string('natureza', 20)->default('documento');
        });

        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_natureza_check
            CHECK (natureza IN ('documento', 'processo', 'resposta', 'anexo'))");

        DB::statement('CREATE INDEX idx_doc_pai ON documentos (documento_pai_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_doc_pai');
        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_natureza_check');
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('documento_pai_id');
            $table->dropColumn('natureza');
        });
    }
};
