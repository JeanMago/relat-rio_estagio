<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'tenants';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'name',
        'nome_suporte',
        'telefone_suporte',
        'plano',
        'agenciador_uuid',
        'empresa_uuid',
        'url_banco',
        'porta',
        'database',
        'username',
        'password',
        'status',
        'licenca_valida_ate',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'plano' => 'integer',
            'porta' => 'integer',
            'licenca_valida_ate' => 'date',
            'password' => 'encrypted',
        ];
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_uuid', 'uuid');
    }

    public function users()
    {
        return $this->hasMany(TenantUser::class, 'tenant_uuid', 'uuid');
    }
}
