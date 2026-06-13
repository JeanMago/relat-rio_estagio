<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicencaCobranca extends Model
{
    protected $table = 'licenca_cobrancas';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'licenca_id',
        'empresa_uuid',
        'tenant_uuid',
        'user_uuid',
        'provider',
        'status',
        'external_reference',
        'external_payment_id',
        'amount',
        'description',
        'qr_code',
        'qr_code_base64',
        'ticket_url',
        'expires_at',
        'paid_at',
        'metadata',
        'response_payload',
        'last_status_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
            'response_payload' => 'array',
            'last_status_payload' => 'array',
        ];
    }

    public function licenca()
    {
        return $this->belongsTo(Licenca::class, 'licenca_id');
    }
}
