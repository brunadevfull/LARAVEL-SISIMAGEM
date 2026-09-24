<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sem FK de propósito: precisa aceitar registro de log mesmo
        // quando o dado de origem é inválido ao ponto de não ter
        // contrapartida em nenhuma tabela nova.
        Schema::create('etl_rejeitos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('uri_legado')->nullable();
            $table->text('tabela')->nullable();
            $table->text('campo')->nullable();
            $table->text('valor_bruto')->nullable();
            $table->text('motivo')->nullable();
            $table->timestampTz('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etl_rejeitos');
    }
};
