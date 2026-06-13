<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pagamento extends Model
{
    protected $table = 'pagamentos';

    protected $fillable = [
        'empresa_uuid',
        'valor_pago',
        'data_pagamento',
        'forma_pagamento',
        'referencia_transacao',
        'usuario_uuid',
    ];

    protected function casts(): array
    {
        return [
            'valor_pago' => 'decimal:2',
            'data_pagamento' => 'datetime',
            'referencia_transacao' => 'integer',
        ];
    }
}
