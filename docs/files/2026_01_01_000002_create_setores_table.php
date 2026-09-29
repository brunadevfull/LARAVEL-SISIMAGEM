<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setores', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->integer('uri_legado')->nullable()->unique();
            $table->string('nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setores');
    }
};
