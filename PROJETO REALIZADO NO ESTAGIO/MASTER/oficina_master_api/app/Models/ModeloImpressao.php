<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModeloImpressao extends Model
{
    protected $table = 'modelos_impressao_catalogo';

    protected $fillable = [
        'slug',
        'nome',
        'contexto',
        'engine',
        'formato_documento',
        'impressora_tipo_default',
        'descricao',
        'imagem_exemplo_url',
        'payload_exemplo',
        'campos_configuraveis',
        'layout_bloqueado',
        'sistema',
        'ativo',
        'ordem',
    ];

    protected $casts = [
        'payload_exemplo' => 'array',
        'campos_configuraveis' => 'array',
        'layout_bloqueado' => 'boolean',
        'sistema' => 'boolean',
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];
}
