<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('uri_legado')->nullable()->unique();
            $table->string('record_id');
            $table->text('titulo');
            $table->boolean('titulo_legado_numerico')->default(false);

            $table->foreignId('arquivo_id')->nullable()
                ->constrained('arquivos')->nullOnDelete();

            $table->text('tipo_documento_texto')->nullable();

            $table->smallInteger('tipo_documento_id')->nullable();
            $table->foreign('tipo_documento_id')
                ->references('id')->on('tipos_documento')->nullOnDelete();

            $table->smallInteger('local_arquivo_id')->nullable();
            $table->foreign('local_arquivo_id')
                ->references('id')->on('locais_arquivo')->nullOnDelete();

            $table->smallInteger('setor_origem_id')->nullable();
            $table->foreign('setor_origem_id')
                ->references('id')->on('setores')->nullOnDelete();

            $table->smallInteger('setor_responsavel_id')->nullable();
            $table->foreign('setor_responsavel_id')
                ->references('id')->on('setores')->nullOnDelete();

            $table->text('consignado')->nullable();
            $table->text('nip_matricula')->nullable();
            $table->text('origem')->nullable();
            $table->text('numero_documento')->nullable();
            $table->text('beneficiario')->nullable();
            $table->text('protocolo')->nullable();
            $table->char('cpf', 11)->nullable();
            $table->text('entidade_consignataria')->nullable();
            $table->text('oficio_judicial_anexo')->nullable();
            $table->boolean('urgente')->default(false);
            $table->text('observacoes')->nullable();

            $table->date('data_protocolo')->nullable();
            $table->date('data_criacao_legado')->nullable();
            $table->date('data_protocolo_legado')->nullable();

            $table->timestampTz('criado_em')->nullable();
            $table->timestampTz('registrado_em')->nullable();
            $table->boolean('data_origem_invalida')->default(false);

            $table->foreignId('criado_por')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestampTz('atualizado_em')->nullable();
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->index('nip_matricula', 'idx_doc_nip');
            $table->index('cpf', 'idx_doc_cpf');
            $table->index('protocolo', 'idx_doc_protocolo');
            $table->index('numero_documento', 'idx_doc_numero');
            $table->index('tipo_documento_id', 'idx_doc_tipo');
            $table->index('criado_em', 'idx_doc_criado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
