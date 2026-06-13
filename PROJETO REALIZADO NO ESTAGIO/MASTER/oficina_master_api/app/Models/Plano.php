<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plano extends Model
{
    protected $table = 'planos';

    protected $fillable = [
        'nome',
        'dias',
        'limit_user',
        'espaco_disco',
        'espaco_disco_adicional',
        'valor_usuario_adicional',
        'valor_espaco_adicional',
        'valor',
        'valor_revenda',
        'descricao',
        'modulos',
        'status',
        'licenca_valida_ate',
    ];

    protected function casts(): array
    {
        return [
            'dias' => 'integer',
            'limit_user' => 'integer',
            'espaco_disco' => 'integer',
            'espaco_disco_adicional' => 'integer',
            'valor_usuario_adicional' => 'decimal:2',
            'valor_espaco_adicional' => 'decimal:2',
            'valor' => 'decimal:2',
            'valor_revenda' => 'decimal:2',
            'modulos' => 'array',
            'status' => 'integer',
            'licenca_valida_ate' => 'date',
        ];
    }
}
