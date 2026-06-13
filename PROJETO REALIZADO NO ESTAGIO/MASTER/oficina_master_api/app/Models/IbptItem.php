<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IbptItem extends Model
{
    protected $connection = 'master';

    protected $table = 'ibpt_itens';

    protected $fillable = [
        'ibpt_versao_id',
        'uf',
        'ncm',
        'ex_tipi',
        'descricao',
        'aliquota_federal_nacional',
        'aliquota_federal_importado',
        'aliquota_estadual',
        'aliquota_municipal',
        'chave',
        'fonte',
    ];

    protected $casts = [
        'aliquota_federal_nacional' => 'decimal:4',
        'aliquota_federal_importado' => 'decimal:4',
        'aliquota_estadual' => 'decimal:4',
        'aliquota_municipal' => 'decimal:4',
    ];
}
