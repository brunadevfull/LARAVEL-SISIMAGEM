<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nome do arquivo como o usuario o enviou (TSRECELEC.REFILENAME).
        // Fica no documento, nao em arquivos: o mesmo arquivo (mesmo sha256)
        // pode servir a mais de um documento, cada um com seu nome.
        // Serve so para dar nome ao download; nunca e caminho.
        Schema::table('documentos', function (Blueprint $table) {
            $table->text('nome_arquivo_original')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn('nome_arquivo_original');
        });
    }
};
