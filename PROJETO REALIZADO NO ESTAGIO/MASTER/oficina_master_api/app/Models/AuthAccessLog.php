<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthAccessLog extends Model
{
    use HasFactory;

    protected $table = 'auth_access_logs';

    protected $fillable = [
        'uuid',
        'origem_app',
        'evento',
        'resultado',
        'auth_guard',
        'user_uuid',
        'tenant_uuid',
        'empresa_uuid',
        'master_user_uuid',
        'email',
        'nome',
        'ip_address',
        'user_agent',
        'browser',
        'browser_version',
        'operating_system',
        'os_version',
        'device_type',
        'device_name',
        'is_mobile',
        'is_desktop',
        'is_bot',
        'suspected_private_mode',
        'session_id',
        'token_id',
        'request_id',
        'logged_in_at',
        'logged_out_at',
        'last_seen_at',
        'failure_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_mobile' => 'boolean',
            'is_desktop' => 'boolean',
            'is_bot' => 'boolean',
            'suspected_private_mode' => 'boolean',
            'metadata' => 'array',
            'logged_in_at' => 'datetime',
            'logged_out_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
