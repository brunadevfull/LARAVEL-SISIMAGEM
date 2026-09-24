<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arquivos', function (Blueprint $table) {
            $table->id();
            $table->char('sha256', 64)->nullable()->unique();
            $table->text('resid_legado')->nullable();
            $table->string('extensao')->nullable();
            $table->string('mime')->nullable();
            $table->bigInteger('bytes')->nullable();
            // caminho: preenchido se armazenamento em disco.
            // conteudo: preenchido se armazenamento em banco.
            // Decisão entre os dois ainda pendente — os dois ficam
            // nuláveis até o volume real do servidor ser confirmado.
            $table->text('caminho')->nullable();
            $table->binary('conteudo')->nullable();
            $table->boolean('sobrescrito')->default(false);
            $table->timestampTz('criado_em')->useCurrent();
        });

        Schema::table('arquivos', function (Blueprint $table) {
            $table->index('sha256', 'idx_arquivo_sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arquivos');
    }
};
