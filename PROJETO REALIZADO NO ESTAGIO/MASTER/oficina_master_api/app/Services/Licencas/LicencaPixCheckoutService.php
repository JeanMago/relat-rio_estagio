<?php

namespace App\Services\Licencas;

use App\Models\Licenca;
use App\Models\LicencaCobranca;
use App\Models\LicencaCheckoutToken;
use App\Models\Plano;
use App\Models\Pagamento;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\Transacao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LicencaPixCheckoutService
{
    public function __construct(
        private readonly LicencaCheckoutTokenService $licencaCheckoutTokenService,
        private readonly MercadoPagoPixService $mercadoPagoPixService,
        private readonly LicencaRenewalService $licencaRenewalService,
        private readonly LicencaStatusService $licencaStatusService,
    ) {
    }

    public function getOrCreateCheckout(string $tenantUuid, ?string $userUuid = null): array
    {
        $tenant = Tenant::query()->with('empresa')->where('uuid', $tenantUuid)->firstOrFail();
        $user = $userUuid
            ? TenantUser::query()->where('tenant_uuid', $tenant->uuid)->where('uuid', $userUuid)->first()
            : $tenant->users()->orderBy('created_at')->first();

        $licenca = $this->resolvePendingLicense($tenant);

        $cobranca = LicencaCobranca::query()
            ->where('licenca_id', $licenca->id)
            ->whereIn('status', ['pending', 'created'])
            ->orderByDesc('created_at')
            ->first();

        if (!$cobranca) {
            $cobranca = LicencaCobranca::query()->create([
                'uuid' => (string) Str::uuid(),
                'licenca_id' => $licenca->id,
                'empresa_uuid' => $licenca->empresa_uuid,
                'tenant_uuid' => $licenca->tenant_uuid,
                'user_uuid' => $user?->uuid,
                'provider' => 'mercado_pago',
                'status' => 'pending',
                'external_reference' => 'licenca-' . $licenca->id . '-' . Str::lower(Str::random(12)),
                'amount' => $licenca->valor ?: 0,
                'description' => 'Renovacao de licenca ' . ($tenant->name ?: $tenant->uuid),
                'metadata' => [
                    'tenant_uuid' => $tenant->uuid,
                    'user_uuid' => $user?->uuid,
                    'licenca_id' => $licenca->id,
                ],
            ]);

            $payload = $this->mercadoPagoPixService->createPixPayment($cobranca, $tenant, $user);
            $this->hydrateChargeFromProviderPayload($cobranca, $payload);
        }

        return [
            'licenca' => $licenca->fresh(),
            'cobranca' => $cobranca->fresh(),
        ];
    }

    public function getCheckoutContext(string $rawToken): array
    {
        $token = $this->licencaCheckoutTokenService->resolve($rawToken);
        [$tenant, $user] = $this->resolveTokenContext($token);

        $pendingLicense = $this->findPendingLicense($tenant);
        $currentLicense = $this->findCurrentLicense($tenant);

        return [
            'tenant' => $tenant,
            'user' => $user,
            'current_license' => $currentLicense,
            'pending_license' => $pendingLicense,
            'plans' => Plano::query()->where('status', 1)->orderBy('nome')->get(),
        ];
    }

    public function selectPlan(string $rawToken, int $planoId): array
    {
        $token = $this->licencaCheckoutTokenService->resolve($rawToken);
        [$tenant, $user] = $this->resolveTokenContext($token);
        $plano = Plano::query()->findOrFail($planoId);
        $licenca = $this->licencaRenewalService->createOrUpdatePendingLicenseFromPlan($tenant, $plano);

        return [
            'tenant' => $tenant,
            'user' => $user,
            'pending_license' => $licenca->fresh(),
        ];
    }

    public function getOrCreateCheckoutByToken(string $rawToken): array
    {
        $token = $this->licencaCheckoutTokenService->resolve($rawToken);
        [$tenant, $user] = $this->resolveTokenContext($token);
        $licenca = $this->findPendingLicense($tenant);

        if (!$licenca) {
            throw new RuntimeException('Nenhuma licenca pendente encontrada para este checkout.');
        }

        $cobranca = LicencaCobranca::query()
            ->where('licenca_id', $licenca->id)
            ->whereIn('status', ['pending', 'created'])
            ->orderByDesc('created_at')
            ->first();

        if (!$cobranca) {
            $cobranca = LicencaCobranca::query()->create([
                'uuid' => (string) Str::uuid(),
                'licenca_id' => $licenca->id,
                'empresa_uuid' => $licenca->empresa_uuid,
                'tenant_uuid' => $licenca->tenant_uuid,
                'user_uuid' => $user?->uuid,
                'provider' => 'mercado_pago',
                'status' => 'pending',
                'external_reference' => 'licenca-' . $licenca->id . '-' . Str::lower(Str::random(12)),
                'amount' => $licenca->valor ?: 0,
                'description' => 'Renovacao de licenca ' . ($tenant->name ?: $tenant->uuid),
                'metadata' => [
                    'tenant_uuid' => $tenant->uuid,
                    'user_uuid' => $user?->uuid,
                    'licenca_id' => $licenca->id,
                    'checkout_token_uuid' => $token->uuid,
                ],
            ]);

            $payload = $this->mercadoPagoPixService->createPixPayment($cobranca, $tenant, $user);
            $this->hydrateChargeFromProviderPayload($cobranca, $payload);
        } elseif ($this->shouldGeneratePixPayload($cobranca)) {
            $payload = $this->mercadoPagoPixService->createPixPayment($cobranca, $tenant, $user);
            $this->hydrateChargeFromProviderPayload($cobranca, $payload);
        }

        return [
            'tenant' => $tenant,
            'user' => $user,
            'licenca' => $licenca->fresh(),
            'cobranca' => $cobranca->fresh(),
        ];
    }

    public function getCheckoutStatusByToken(string $rawToken): array
    {
        $token = $this->licencaCheckoutTokenService->resolve($rawToken);
        [$tenant, $user] = $this->resolveTokenContext($token);

        $currentLicense = $this->findCurrentLicense($tenant);
        $pendingLicense = $this->findPendingLicense($tenant);
        $charge = null;

        if ($pendingLicense) {
            $charge = LicencaCobranca::query()
                ->where('licenca_id', $pendingLicense->id)
                ->orderByDesc('created_at')
                ->first();

            if ($charge && !blank($charge->external_payment_id) && in_array($charge->status, ['pending', 'created'], true)) {
                $refreshedCharge = $this->handleProviderPayment([
                    'id' => $charge->external_payment_id,
                ]);

                if ($refreshedCharge) {
                    $charge = $refreshedCharge;
                    $pendingLicense = $this->findPendingLicense($tenant);
                    $currentLicense = $this->findCurrentLicense($tenant);
                }
            }
        }

        return [
            'tenant' => $tenant,
            'user' => $user,
            'current_license' => $currentLicense,
            'pending_license' => $pendingLicense,
            'cobranca' => $charge?->fresh(),
            'approved' => (bool) ($charge && $charge->status === 'approved'),
        ];
    }

    public function simulateApprovedPaymentByToken(string $rawToken): array
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Simulacao de pagamento nao permitida em producao.');
        }

        $checkout = $this->getOrCreateCheckoutByToken($rawToken);
        $charge = $checkout['cobranca']->fresh();

        $charge->update([
            'status' => 'approved',
            'paid_at' => CarbonImmutable::now(),
            'external_payment_id' => $charge->external_payment_id ?: 'simulated-' . Str::lower(Str::random(12)),
            'last_status_payload' => [
                'id' => $charge->external_payment_id ?: 'simulated',
                'status' => 'approved',
                'simulated' => true,
            ],
        ]);

        $this->releaseLicenseForPaidCharge($charge->fresh());

        return $this->getCheckoutStatusByToken($rawToken);
    }

    public function handleProviderPayment(array $payload): ?LicencaCobranca
    {
        $externalId = (string) ($payload['id'] ?? '');
        if ($externalId === '') {
            return null;
        }

        $payment = $this->mercadoPagoPixService->fetchPayment($externalId);
        $externalReference = (string) ($payment['external_reference'] ?? '');

        $cobranca = LicencaCobranca::query()
            ->where('external_reference', $externalReference)
            ->orWhere('external_payment_id', $externalId)
            ->first();

        if (!$cobranca) {
            return null;
        }

        $this->hydrateChargeFromProviderPayload($cobranca, $payment, true);

        if (($payment['status'] ?? null) === 'approved') {
            $this->releaseLicenseForPaidCharge($cobranca);
        }

        return $cobranca->fresh();
    }

    public function expirePendingCharges(?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();

        LicencaCobranca::query()
            ->whereIn('status', ['pending', 'created'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->update(['status' => 'expired']);
    }

    private function resolvePendingLicense(Tenant $tenant): Licenca
    {
        $today = CarbonImmutable::now()->startOfDay();

        $currentBlocked = Licenca::query()
            ->where('tenant_uuid', $tenant->uuid)
            ->where('bloqueada', true)
            ->whereDate('data_inicio', '<=', $today->toDateString())
            ->whereDate('data_expiracao', '>=', $today->toDateString())
            ->orderBy('data_inicio')
            ->first();

        if ($currentBlocked) {
            return $currentBlocked;
        }

        $futureBlocked = Licenca::query()
            ->where('tenant_uuid', $tenant->uuid)
            ->where('bloqueada', true)
            ->whereDate('data_inicio', '>', $today->toDateString())
            ->orderBy('data_inicio')
            ->first();

        if ($futureBlocked) {
            return $futureBlocked;
        }

        $current = Licenca::query()
            ->where('tenant_uuid', $tenant->uuid)
            ->where('renovacao_automatica', true)
            ->whereDate('data_expiracao', '>=', $today->toDateString())
            ->orderByDesc('data_expiracao')
            ->firstOrFail();

        return $this->licencaRenewalService->ensureRenewalLicense($current);
    }

    private function findPendingLicense(Tenant $tenant): ?Licenca
    {
        $today = CarbonImmutable::now()->startOfDay();

        return Licenca::query()
            ->where('tenant_uuid', $tenant->uuid)
            ->where('bloqueada', true)
            ->whereDate('data_expiracao', '>=', $today->toDateString())
            ->orderBy('data_inicio')
            ->first();
    }

    private function findCurrentLicense(Tenant $tenant): ?Licenca
    {
        $today = CarbonImmutable::now()->startOfDay();

        return Licenca::query()
            ->where('tenant_uuid', $tenant->uuid)
            ->whereDate('data_inicio', '<=', $today->toDateString())
            ->whereDate('data_expiracao', '>=', $today->toDateString())
            ->orderByDesc('data_inicio')
            ->first();
    }

    private function resolveTokenContext(LicencaCheckoutToken $token): array
    {
        $tenant = Tenant::query()->with('empresa')->where('uuid', $token->tenant_uuid)->firstOrFail();
        $user = $token->user_uuid
            ? TenantUser::query()->where('tenant_uuid', $tenant->uuid)->where('uuid', $token->user_uuid)->first()
            : $tenant->users()->orderBy('created_at')->first();

        return [$tenant, $user];
    }

    private function shouldGeneratePixPayload(LicencaCobranca $cobranca): bool
    {
        if (!in_array($cobranca->status, ['pending', 'created'], true)) {
            return false;
        }

        return blank($cobranca->external_payment_id)
            || blank($cobranca->qr_code)
            || blank($cobranca->qr_code_base64);
    }

    private function hydrateChargeFromProviderPayload(LicencaCobranca $cobranca, array $payload, bool $statusPayload = false): void
    {
        $transactionData = $payload['point_of_interaction']['transaction_data'] ?? [];

        $attributes = [
            'external_payment_id' => isset($payload['id']) ? (string) $payload['id'] : $cobranca->external_payment_id,
            'status' => (string) ($payload['status'] ?? $cobranca->status),
            'qr_code' => $transactionData['qr_code'] ?? $cobranca->qr_code,
            'qr_code_base64' => $transactionData['qr_code_base64'] ?? $cobranca->qr_code_base64,
            'ticket_url' => $transactionData['ticket_url'] ?? $cobranca->ticket_url,
            'response_payload' => $statusPayload ? $cobranca->response_payload : $payload,
            'last_status_payload' => $statusPayload ? $payload : $cobranca->last_status_payload,
        ];

        if (!empty($payload['date_of_expiration'])) {
            $attributes['expires_at'] = CarbonImmutable::parse($payload['date_of_expiration']);
        }

        if (($payload['status'] ?? null) === 'approved') {
            $attributes['paid_at'] = CarbonImmutable::now();
        }

        $cobranca->update($attributes);
    }

    private function releaseLicenseForPaidCharge(LicencaCobranca $cobranca): void
    {
        $licenca = Licenca::query()->findOrFail($cobranca->licenca_id);

        if ($licenca->bloqueada) {
            $this->licencaStatusService->activateLicense($licenca);
        }

        DB::transaction(function () use ($licenca, $cobranca) {
            $transacao = Transacao::query()
                ->where('empresa_uuid', $licenca->empresa_uuid)
                ->where('tipo', 'pagamento_licenca')
                ->where('referencia_id', $licenca->id)
                ->first();

            if (!$transacao) {
                $transacao = Transacao::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'tipo' => 'pagamento_licenca',
                    'referencia_id' => $licenca->id,
                    'descricao' => 'Pagamento de renovacao da licenca ' . $licenca->id,
                    'valor' => $cobranca->amount,
                    'data' => CarbonImmutable::now()->toDateString(),
                    'status' => 2,
                    'empresa_uuid' => $licenca->empresa_uuid,
                    'user_uuid' => $cobranca->user_uuid,
                    'forma_pagamento' => 'pix_mercado_pago',
                ]);
            }

            Pagamento::query()->firstOrCreate(
                [
                    'empresa_uuid' => $licenca->empresa_uuid,
                    'referencia_transacao' => $licenca->id,
                    'usuario_uuid' => $cobranca->user_uuid,
                ],
                [
                    'valor_pago' => $cobranca->amount,
                    'data_pagamento' => CarbonImmutable::now(),
                    'forma_pagamento' => 'pix_mercado_pago',
                ]
            );
        });
    }
}
