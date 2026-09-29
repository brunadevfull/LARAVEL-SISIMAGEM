<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome'])]
class Setor extends Model
{
    /**
     * Ids fixos, criados pela migration 15. O perfil padrao depende do 1.
     */
    public const PAPEM_41 = 1;

    public const PAPEM_42 = 2;

    protected $table = 'setores';

    public $timestamps = false;
}
