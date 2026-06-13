<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthAccessLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'origem_app' => ['nullable', 'string', 'max:80'],
            'evento' => ['nullable', 'string', 'max:50'],
            'resultado' => ['nullable', 'string', 'max:30'],
            'tenant_uuid' => ['nullable', 'uuid'],
            'empresa_uuid' => ['nullable', 'uuid'],
            'user_uuid' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = AuthAccessLog::query();

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('email', 'like', "%{$search}%")
                    ->orWhere('nome', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('operating_system', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%");
            });
        }

        foreach (['origem_app', 'evento', 'resultado', 'user_uuid'] as $column) {
            if (!empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        if (!empty($filters['tenant_uuid'])) {
            $query->where('tenant_uuid', $filters['tenant_uuid']);
        }

        if (!empty($filters['empresa_uuid'])) {
            $empresaUuid = $filters['empresa_uuid'];
            $query->where(function ($q) use ($empresaUuid) {
                $q->where('empresa_uuid', $empresaUuid)
                    ->orWhere('tenant_uuid', $empresaUuid);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('logged_in_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('logged_in_at', '<=', $filters['date_to']);
        }

        $rows = $query
            ->orderByDesc('logged_in_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['limit'] ?? 30));

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $rows->items(),
            'message' => null,
            'errors' => null,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'origem_app' => ['nullable', 'string', 'max:80'],
            'evento' => ['nullable', 'string', 'max:50'], // login, logout, create, update, delete, download, test_connection
            'resultado' => ['nullable', 'string', 'max:30'],
            'auth_guard' => ['nullable', 'string', 'max:50'],
            'user_uuid' => ['nullable', 'uuid'],
            'tenant_uuid' => ['nullable', 'uuid'],
            'empresa_uuid' => ['nullable', 'uuid'],
            'master_user_uuid' => ['nullable', 'uuid'],
            'email' => ['nullable', 'string', 'max:255'],
            'nome' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'user_agent' => ['nullable', 'string'],
            'browser' => ['nullable', 'string', 'max:120'],
            'browser_version' => ['nullable', 'string', 'max:60'],
            'operating_system' => ['nullable', 'string', 'max:120'],
            'os_version' => ['nullable', 'string', 'max:60'],
            'device_type' => ['nullable', 'string', 'max:60'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'is_mobile' => ['nullable', 'boolean'],
            'is_desktop' => ['nullable', 'boolean'],
            'is_bot' => ['nullable', 'boolean'],
            'suspected_private_mode' => ['nullable', 'boolean'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'token_id' => ['nullable', 'integer'],
            'request_id' => ['nullable', 'string', 'max:120'],
            'logged_in_at' => ['nullable', 'date'],
            'logged_out_at' => ['nullable', 'date'],
            'last_seen_at' => ['nullable', 'date'],
            'failure_reason' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $payload['uuid'] = $payload['uuid'] ?? (string) Str::uuid();
        $payload['origem_app'] = $payload['origem_app'] ?? 'oficina_api';
        $payload['evento'] = $payload['evento'] ?? 'login';
        $payload['resultado'] = $payload['resultado'] ?? 'sucesso';
        $payload['master_user_uuid'] = $payload['master_user_uuid'] ?? $request->user()?->uuid;

        $row = AuthAccessLog::query()->create($payload);

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $row,
            'message' => 'Log de autenticacao registrado com sucesso.',
            'errors' => null,
            'meta' => null,
        ], 201);
    }

    public function show(string $id)
    {
        $row = AuthAccessLog::query()
            ->where('uuid', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $row,
            'message' => null,
            'errors' => null,
            'meta' => null,
        ]);
    }
}
