<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicencaCheckoutToken extends Model
{
    protected $table = 'licenca_checkout_tokens';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'token_hash',
        'tenant_uuid',
        'empresa_uuid',
        'user_uuid',
        'status',
        'expires_at',
        'used_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
