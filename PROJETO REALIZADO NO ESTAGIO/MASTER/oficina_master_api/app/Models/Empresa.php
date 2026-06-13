<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Empresa extends Model
{
    use SoftDeletes;

    protected $table = 'empresas';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'status',
        'nome',
        'apelido',
        'cpf_cnpj',
        'nascimento',
        'site',
        'rg',
        'regime_tributario',
        'contribuinte',
        'ie',
        'im',
        'ir',
        'suframa',
        'cnae',
        'sub_st',
        'email',
        'telefone',
        'cep',
        'logradouro',
        'uf',
        'cod_uf',
        'municipio',
        'numero',
        'complemento',
        'bairro',
        'cod_municipio',
        'agenciador_id',
        'emite_nfce',
        'emite_nfse',
    ];

    protected function casts(): array
    {
        return [
            'nascimento' => 'date',
            'emite_nfce' => 'boolean',
            'emite_nfse' => 'boolean',
        ];
    }

    public function tenant()
    {
        return $this->hasOne(Tenant::class, 'empresa_uuid', 'uuid');
    }

    public function licencas()
    {
        return $this->hasMany(Licenca::class, 'empresa_uuid', 'uuid');
    }
}