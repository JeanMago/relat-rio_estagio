<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class MasterUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'master_users';

    protected $fillable = [
        'uuid',
        'empresa_uuid',
        'nome',
        'email',
        'telefone',
        'email_verified_at',
        'password',
        'perfil',
        'status',
        'ultimo_login_at',
        'avatar_url',
        'observacoes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
            'ultimo_login_at' => 'datetime',
        ];
    }
}
