<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Licencas\LicencaCheckoutTokenService;
use Illuminate\Http\Request;

class LicencaCheckoutTokenController extends Controller
{
    public function __construct(
        private readonly LicencaCheckoutTokenService $licencaCheckoutTokenService,
    ) {
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'tenant_uuid' => ['required', 'uuid'],
            'user_uuid' => ['nullable', 'uuid'],
            'ttl_minutes' => ['nullable', 'integer', 'min:5', 'max:120'],
        ]);

        $issued = $this->licencaCheckoutTokenService->issue(
            $payload['tenant_uuid'],
            $payload['user_uuid'] ?? null,
            $payload['ttl_minutes'] ?? null,
        );

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => [
                'checkout_token' => $issued['token'],
                'expires_at' => $issued['record']->expires_at,
                'tenant_uuid' => $issued['record']->tenant_uuid,
                'empresa_uuid' => $issued['record']->empresa_uuid,
                'user_uuid' => $issued['record']->user_uuid,
            ],
            'message' => 'Token de checkout emitido com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }
}
