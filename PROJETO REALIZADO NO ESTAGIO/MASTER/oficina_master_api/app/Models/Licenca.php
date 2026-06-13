<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Licenca extends Model
{
    protected $table = 'licencas';

    protected $fillable = [
        'empresa_uuid',
        'tenant_uuid',
        'plano_id',
        'vendedor_uuid',
        'tipo_contratacao',
        'tipo_experiencia',
        'tipo_demonstracao',
        'renovacao_automatica',
        'ocultar_mensagem_vencimento',
        'forma_pagamento_renovacao',
        'bloqueada',
        'data_inicio',
        'data_expiracao',
        'usuario_adicionais',
        'limit_user',
        'espaco_disco',
        'espaco_disco_adicional',
        'empresas_disponiveis',
        'valor_empresa_disponivel',
        'valor_usuario_adicional',
        'valor_espaco_adicional',
        'valor',
        'valor_revenda',
        'forma_pagamento',
        'modulos',
        'empresa_para_nfse',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'tipo_contratacao' => 'boolean',
            'tipo_experiencia' => 'boolean',
            'tipo_demonstracao' => 'boolean',
            'renovacao_automatica' => 'boolean',
            'ocultar_mensagem_vencimento' => 'boolean',
            'bloqueada' => 'boolean',
            'data_inicio' => 'date',
            'data_expiracao' => 'date',
            'usuario_adicionais' => 'integer',
            'limit_user' => 'integer',
            'espaco_disco' => 'integer',
            'espaco_disco_adicional' => 'integer',
            'empresas_disponiveis' => 'integer',
            'valor_empresa_disponivel' => 'decimal:2',
            'valor_usuario_adicional' => 'decimal:2',
            'valor_espaco_adicional' => 'decimal:2',
            'valor' => 'decimal:2',
            'valor_revenda' => 'decimal:2',
            'modulos' => 'array',
            'empresa_para_nfse' => 'integer',
        ];
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_uuid', 'uuid');
    }

    public function plano()
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    public function cobrancas()
    {
        return $this->hasMany(LicencaCobranca::class, 'licenca_id');
    }
}
