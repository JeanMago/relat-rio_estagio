<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transacao extends Model
{
    protected $table = 'transacoes';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'tipo',
        'referencia_id',
        'descricao',
        'valor',
        'desconto',
        'juros',
        'multa',
        'outros',
        'valorpago',
        'troco',
        'data',
        'status',
        'empresa_uuid',
        'user_uuid',
        'forma_pagamento',
    ];

    protected function casts(): array
    {
        return [
            'referencia_id' => 'integer',
            'valor' => 'decimal:2',
            'desconto' => 'decimal:2',
            'juros' => 'decimal:2',
            'multa' => 'decimal:2',
            'outros' => 'decimal:2',
            'valorpago' => 'decimal:2',
            'troco' => 'decimal:2',
            'data' => 'date',
            'status' => 'integer',
        ];
    }
}
