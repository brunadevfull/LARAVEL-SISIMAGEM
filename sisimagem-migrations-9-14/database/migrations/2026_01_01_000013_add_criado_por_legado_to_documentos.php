<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nome de usuario do legado que incluiu o documento (TSRECELEC.RENAMEURI,
        // nome em TSLOCATION). Fica mesmo quando a conta antiga nao virou usuario novo.
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('criado_por_legado')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn('criado_por_legado');
        });
    }
};
