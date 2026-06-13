<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IbptVersao extends Model
{
    protected $connection = 'master';

    protected $table = 'ibpt_versoes';

    protected $fillable = [
        'versao',
        'vigencia_inicio',
        'vigencia_fim',
        'fonte',
        'arquivo_path',
        'hash_arquivo',
        'ativa',
        'publicada_em',
        'meta',
    ];

    protected $casts = [
        'vigencia_inicio' => 'date:Y-m-d',
        'vigencia_fim' => 'date:Y-m-d',
        'publicada_em' => 'datetime',
        'ativa' => 'boolean',
        'meta' => 'array',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(IbptItem::class, 'ibpt_versao_id');
    }
}
