<?php

namespace App\Services\Licencas;

use App\Models\Empresa;
use App\Models\LicencaCheckoutToken;
use App\Models\Tenant;
use App\Models\TenantUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class LicencaCheckoutTokenService
{
    public function issue(string $tenantUuid, ?string $userUuid = null, ?int $ttlMinutes = null): array
    {
        $tenant = Tenant::query()->with('empresa')->where('uuid', $tenantUuid)->firstOrFail();
        $empresa = $tenant->empresa ?: Empresa::query()->where('uuid', $tenant->empresa_uuid)->firstOrFail();
        $user = $userUuid
            ? TenantUser::query()->where('tenant_uuid', $tenant->uuid)->where('uuid', $userUuid)->first()
            : $tenant->users()->orderBy('created_at')->first();

        $rawToken = Str::random(64);
        $token = LicencaCheckoutToken::query()->create([
            'uuid' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $rawToken),
            'tenant_uuid' => $tenant->uuid,
            'empresa_uuid' => $empresa->uuid,
            'user_uuid' => $user?->uuid,
            'status' => 'active',
            'expires_at' => CarbonImmutable::now()->addMinutes($ttlMinutes ?: 30),
            'metadata' => [
                'emitted_by' => 'fk_oficina_master_api',
            ],
        ]);

        return [
            'token' => $rawToken,
            'record' => $token,
        ];
    }

    public function resolve(string $rawToken): LicencaCheckoutToken
    {
        return LicencaCheckoutToken::query()
            ->where('token_hash', hash('sha256', $rawToken))
            ->where('status', 'active')
            ->where('expires_at', '>', CarbonImmutable::now())
            ->firstOrFail();
    }

    public function markUsed(LicencaCheckoutToken $token): void
    {
        $token->update([
            'used_at' => CarbonImmutable::now(),
            'status' => 'used',
        ]);
    }
}
