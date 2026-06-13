<?php

namespace App\Services\Licencas;

use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

class LicencaStatusService
{
    public function syncStatuses(?CarbonImmutable $today = null): void
    {
        $today ??= CarbonImmutable::now()->startOfDay();

        Tenant::query()->with('empresa')->get()->each(function (Tenant $tenant) use ($today) {
            $licencaAtual = Licenca::query()
                ->where('tenant_uuid', $tenant->uuid)
                ->whereDate('data_inicio', '<=', $today->toDateString())
                ->whereDate('data_expiracao', '>=', $today->toDateString())
                ->orderByDesc('data_inicio')
                ->orderByDesc('id')
                ->first();

            if (!$licencaAtual || $licencaAtual->bloqueada) {
                $this->blockTenantAndEmpresa($tenant);
                return;
            }

            $tenant->update([
                'status' => 'ativo',
                'plano' => $licencaAtual->plano_id,
                'licenca_valida_ate' => $licencaAtual->data_expiracao,
            ]);

            if ($tenant->empresa && (int) $tenant->empresa->status !== 1) {
                $tenant->empresa->update(['status' => 1]);
            }
        });
    }

    public function activateLicense(Licenca $licenca): void
    {
        $licenca->update(['bloqueada' => false]);

        $tenant = Tenant::query()->where('uuid', $licenca->tenant_uuid)->first();
        if (!$tenant) {
            return;
        }

        $tenant->update([
            'status' => 'ativo',
            'plano' => $licenca->plano_id,
            'licenca_valida_ate' => $licenca->data_expiracao,
        ]);

        Empresa::query()
            ->where('uuid', $licenca->empresa_uuid)
            ->update(['status' => 1]);
    }

    private function blockTenantAndEmpresa(Tenant $tenant): void
    {
        if ($tenant->status !== 'bloqueado') {
            $tenant->update(['status' => 'bloqueado']);
        }

        if ($tenant->empresa && (int) $tenant->empresa->status !== 2) {
            $tenant->empresa->update(['status' => 2]);
        }
    }
}
