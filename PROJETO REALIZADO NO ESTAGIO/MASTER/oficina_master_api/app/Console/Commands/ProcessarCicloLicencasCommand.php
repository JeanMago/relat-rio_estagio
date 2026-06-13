<?php

namespace App\Console\Commands;

use App\Services\Licencas\LicencaPixCheckoutService;
use App\Services\Licencas\LicencaRenewalService;
use App\Services\Licencas\LicencaStatusService;
use Illuminate\Console\Command;

class ProcessarCicloLicencasCommand extends Command
{
    protected $signature = 'licencas:processar-ciclo';

    protected $description = 'Cria renovacoes automaticas, expira cobrancas pendentes e sincroniza bloqueio/liberacao de licencas.';

    public function __construct(
        private readonly LicencaRenewalService $licencaRenewalService,
        private readonly LicencaPixCheckoutService $licencaPixCheckoutService,
        private readonly LicencaStatusService $licencaStatusService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->licencaRenewalService->processAutomaticRenewals();
        $this->licencaPixCheckoutService->expirePendingCharges();
        $this->licencaStatusService->syncStatuses();

        $this->info('Ciclo de licencas processado com sucesso.');

        return self::SUCCESS;
    }
}
