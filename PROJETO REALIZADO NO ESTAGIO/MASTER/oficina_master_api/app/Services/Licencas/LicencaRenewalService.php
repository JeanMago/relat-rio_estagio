<?php

namespace App\Services\Licencas;

use App\Models\Licenca;
use App\Models\Plano;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

class LicencaRenewalService
{
    public function processAutomaticRenewals(?CarbonImmutable $today = null): void
    {
        $today ??= CarbonImmutable::now()->startOfDay();

        Licenca::query()
            ->where('renovacao_automatica', true)
            ->whereNotNull('data_expiracao')
            ->whereDate('data_expiracao', '>=', $today->toDateString())
            ->whereDate('data_expiracao', '<=', $today->addDays(5)->toDateString())
            ->orderBy('data_expiracao')
            ->get()
            ->each(fn (Licenca $licenca) => $this->ensureRenewalLicense($licenca));
    }

    public function ensureRenewalLicense(Licenca $licenca): Licenca
    {
        $inicioRenovacao = CarbonImmutable::parse($licenca->data_expiracao)->addDay();

        $existing = Licenca::query()
            ->where('tenant_uuid', $licenca->tenant_uuid)
            ->whereDate('data_inicio', $inicioRenovacao->toDateString())
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $plano = $licenca->plano_id ? Plano::query()->find($licenca->plano_id) : null;
        $dias = $plano?->dias ?: $this->resolveDurationInDays($licenca);
        $dataExpiracao = $inicioRenovacao->addDays(max(1, $dias) - 1);

        return Licenca::query()->create([
            'empresa_uuid' => $licenca->empresa_uuid,
            'tenant_uuid' => $licenca->tenant_uuid,
            'plano_id' => $licenca->plano_id,
            'vendedor_uuid' => $licenca->vendedor_uuid,
            'tipo_contratacao' => $licenca->tipo_contratacao,
            'tipo_experiencia' => $licenca->tipo_experiencia,
            'tipo_demosntracao' => $licenca->tipo_demosntracao,
            'renovacao_automatica' => true,
            'ocultar_mensagem_vencimento' => $licenca->ocultar_mensagem_vencimento,
            'forma_pagamento_renovacao' => $licenca->forma_pagamento_renovacao ?: 'pix_mercado_pago',
            'bloqueada' => true,
            'data_inicio' => $inicioRenovacao->toDateString(),
            'data_expiracao' => $dataExpiracao->toDateString(),
            'usuario_adicionais' => $licenca->usuario_adicionais,
            'limit_user' => $plano?->limit_user ?? $licenca->limit_user,
            'espaco_disco' => $plano?->espaco_disco ?? $licenca->espaco_disco,
            'espaco_disco_adicional' => $plano?->espaco_disco_adicional ?? $licenca->espaco_disco_adicional,
            'empresas_disponiveis' => $licenca->empresas_disponiveis,
            'valor_empresa_disponivel' => $licenca->valor_empresa_disponivel,
            'valor_usuario_adicional' => $plano?->valor_usuario_adicional ?? $licenca->valor_usuario_adicional,
            'valor_espaco_adicional' => $plano?->valor_espaco_adicional ?? $licenca->valor_espaco_adicional,
            'valor' => $plano?->valor ?? $licenca->valor,
            'valor_revenda' => $plano?->valor_revenda ?? $licenca->valor_revenda,
            'forma_pagamento' => $licenca->forma_pagamento,
            'modulos' => $plano?->modulos ?? $licenca->modulos,
            'empresa_para_nfse' => $licenca->empresa_para_nfse,
            'observacoes' => 'Licenca gerada automaticamente para renovacao.',
        ]);
    }

    public function createOrUpdatePendingLicenseFromPlan(Tenant $tenant, Plano $plano): Licenca
    {
        $today = CarbonImmutable::now()->startOfDay();

        $pending = Licenca::query()
            ->where('tenant_uuid', $tenant->uuid)
            ->where('bloqueada', true)
            ->whereDate('data_inicio', '>=', $today->toDateString())
            ->orderBy('data_inicio')
            ->first();

        $current = Licenca::query()
            ->where('tenant_uuid', $tenant->uuid)
            ->whereDate('data_inicio', '<=', $today->toDateString())
            ->whereDate('data_expiracao', '>=', $today->toDateString())
            ->orderByDesc('data_inicio')
            ->first();

        $dataInicio = $pending?->data_inicio
            ? CarbonImmutable::parse($pending->data_inicio)
            : ($current?->data_expiracao
                ? CarbonImmutable::parse($current->data_expiracao)->addDay()
                : $today);

        $dataExpiracao = $dataInicio->addDays(max(1, (int) ($plano->dias ?: 30)) - 1);

        $payload = [
            'empresa_uuid' => $tenant->empresa_uuid,
            'tenant_uuid' => $tenant->uuid,
            'plano_id' => $plano->id,
            'renovacao_automatica' => true,
            'forma_pagamento_renovacao' => 'pix_mercado_pago',
            'bloqueada' => true,
            'data_inicio' => $dataInicio->toDateString(),
            'data_expiracao' => $dataExpiracao->toDateString(),
            'limit_user' => $plano->limit_user,
            'espaco_disco' => $plano->espaco_disco,
            'espaco_disco_adicional' => $plano->espaco_disco_adicional,
            'valor_usuario_adicional' => $plano->valor_usuario_adicional,
            'valor_espaco_adicional' => $plano->valor_espaco_adicional,
            'valor' => $plano->valor,
            'valor_revenda' => $plano->valor_revenda,
            'forma_pagamento' => 'pix_mercado_pago',
            'modulos' => $plano->modulos,
            'observacoes' => 'Licenca preparada para checkout publico.',
        ];

        if ($pending) {
            $pending->update($payload);
            return $pending->fresh();
        }

        return Licenca::query()->create($payload);
    }

    private function resolveDurationInDays(Licenca $licenca): int
    {
        if ($licenca->data_inicio && $licenca->data_expiracao) {
            return CarbonImmutable::parse($licenca->data_inicio)
                ->diffInDays(CarbonImmutable::parse($licenca->data_expiracao)) + 1;
        }

        return 30;
    }
}
