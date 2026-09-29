<?php

namespace Tests\Unit;

use App\Models\Setor;
use Tests\TestCase;

class SetorTest extends TestCase
{
    public function test_uses_setores_table_without_timestamps(): void
    {
        $setor = new Setor;

        $this->assertSame('setores', $setor->getTable());
        $this->assertFalse($setor->usesTimestamps());
    }

    public function test_fixed_ids_match_the_migration(): void
    {
        $this->assertSame(1, Setor::PAPEM_41);
        $this->assertSame(2, Setor::PAPEM_42);
    }

    public function test_only_nome_is_fillable(): void
    {
        $setor = new Setor(['id' => 99, 'nome' => 'PAPEM-41', 'uri_legado' => 5]);

        $this->assertSame('PAPEM-41', $setor->nome);
        $this->assertNull($setor->id);
        $this->assertNull($setor->uri_legado);
    }
}
