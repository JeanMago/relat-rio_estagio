<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantUser extends Model
{
    use SoftDeletes;

    protected $table = 'users';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        'permission_id',
        'telefone',
        'foto',
        'por_page',
        'senha_rapida',
        'pessoa_id',
        'depositoatual',
        'cpf',
        'rg',
        'cep',
        'endereco',
        'bairro',
        'complemento',
        'cidade',
        'uf',
        'dashboard',
        'is_tecnico',
        'status',
        'tenant_uuid',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'is_tecnico' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_uuid', 'uuid');
    }
}
