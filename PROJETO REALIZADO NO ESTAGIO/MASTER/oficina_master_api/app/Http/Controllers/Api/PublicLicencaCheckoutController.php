<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Licencas\LicencaPixCheckoutService;
use Illuminate\Http\Request;

class PublicLicencaCheckoutController extends Controller
{
    public function __construct(
        private readonly LicencaPixCheckoutService $licencaPixCheckoutService,
    ) {
    }

    public function gerarPix(Request $request)
    {
        $payload = $request->validate([
            'checkout_token' => ['required', 'string', 'min:20'],
        ]);

        $checkout = $this->licencaPixCheckoutService->getOrCreateCheckoutByToken($payload['checkout_token']);

        $cobranca = $checkout['cobranca'];
        $licenca = $checkout['licenca'];

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => [
                'tenant_uuid' => $checkout['tenant']->uuid,
                'licenca' => [
                    'id' => $licenca->id,
                    'data_inicio' => $licenca->data_inicio,
                    'data_expiracao' => $licenca->data_expiracao,
                    'bloqueada' => $licenca->bloqueada,
                    'plano_id' => $licenca->plano_id,
                    'valor' => $licenca->valor,
                ],
                'cobranca' => [
                    'uuid' => $cobranca->uuid,
                    'status' => $cobranca->status,
                    'amount' => $cobranca->amount,
                    'description' => $cobranca->description,
                    'expires_at' => $cobranca->expires_at,
                    'qr_code' => $cobranca->qr_code,
                    'qr_code_base64' => $cobranca->qr_code_base64,
                    'ticket_url' => $cobranca->ticket_url,
                ],
            ],
            'message' => null,
            'errors' => null,
            'meta' => null,
        ]);
    }

    public function context(Request $request)
    {
        $payload = $request->validate([
            'checkout_token' => ['required', 'string', 'min:20'],
        ]);

        $context = $this->licencaPixCheckoutService->getCheckoutContext($payload['checkout_token']);

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => [
                'tenant' => [
                    'uuid' => $context['tenant']->uuid,
                    'name' => $context['tenant']->name,
                ],
                'user' => $context['user'] ? [
                    'uuid' => $context['user']->uuid,
                    'name' => $context['user']->name,
                    'email' => $context['user']->email,
                ] : null,
                'current_license' => $context['current_license'],
                'pending_license' => $context['pending_license'],
                'plans' => $context['plans'],
            ],
            'message' => null,
            'errors' => null,
            'meta' => null,
        ]);
    }

    public function status(Request $request)
    {
        $payload = $request->validate([
            'checkout_token' => ['required', 'string', 'min:20'],
        ]);

        $context = $this->licencaPixCheckoutService->getCheckoutStatusByToken($payload['checkout_token']);
        $cobranca = $context['cobranca'];

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => [
                'tenant' => [
                    'uuid' => $context['tenant']->uuid,
                    'name' => $context['tenant']->name,
                ],
                'user' => $context['user'] ? [
                    'uuid' => $context['user']->uuid,
                    'name' => $context['user']->name,
                    'email' => $context['user']->email,
                ] : null,
                'current_license' => $context['current_license'],
                'pending_license' => $context['pending_license'],
                'approved' => $context['approved'],
                'test_mode' => !app()->environment('production'),
                'cobranca' => $cobranca ? [
                    'uuid' => $cobranca->uuid,
                    'status' => $cobranca->status,
                    'amount' => $cobranca->amount,
                    'description' => $cobranca->description,
                    'expires_at' => $cobranca->expires_at,
                    'paid_at' => $cobranca->paid_at,
                    'qr_code' => $cobranca->qr_code,
                    'qr_code_base64' => $cobranca->qr_code_base64,
                    'ticket_url' => $cobranca->ticket_url,
                    'external_payment_id' => $cobranca->external_payment_id,
                ] : null,
            ],
            'message' => null,
            'errors' => null,
            'meta' => null,
        ]);
    }

    public function simularPagamento(Request $request)
    {
        $payload = $request->validate([
            'checkout_token' => ['required', 'string', 'min:20'],
        ]);

        $context = $this->licencaPixCheckoutService->simulateApprovedPaymentByToken($payload['checkout_token']);
        $cobranca = $context['cobranca'];

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => [
                'tenant' => [
                    'uuid' => $context['tenant']->uuid,
                    'name' => $context['tenant']->name,
                ],
                'user' => $context['user'] ? [
                    'uuid' => $context['user']->uuid,
                    'name' => $context['user']->name,
                    'email' => $context['user']->email,
                ] : null,
                'current_license' => $context['current_license'],
                'pending_license' => $context['pending_license'],
                'approved' => $context['approved'],
                'test_mode' => !app()->environment('production'),
                'cobranca' => $cobranca ? [
                    'uuid' => $cobranca->uuid,
                    'status' => $cobranca->status,
                    'amount' => $cobranca->amount,
                    'description' => $cobranca->description,
                    'expires_at' => $cobranca->expires_at,
                    'paid_at' => $cobranca->paid_at,
                    'qr_code' => $cobranca->qr_code,
                    'qr_code_base64' => $cobranca->qr_code_base64,
                    'ticket_url' => $cobranca->ticket_url,
                    'external_payment_id' => $cobranca->external_payment_id,
                ] : null,
            ],
            'message' => 'Pagamento simulado com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }

    public function selecionarPlano(Request $request)
    {
        $payload = $request->validate([
            'checkout_token' => ['required', 'string', 'min:20'],
            'plano_id' => ['required', 'integer'],
        ]);

        $context = $this->licencaPixCheckoutService->selectPlan(
            $payload['checkout_token'],
            (int) $payload['plano_id'],
        );

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => [
                'tenant' => [
                    'uuid' => $context['tenant']->uuid,
                    'name' => $context['tenant']->name,
                ],
                'user' => $context['user'] ? [
                    'uuid' => $context['user']->uuid,
                    'name' => $context['user']->name,
                    'email' => $context['user']->email,
                ] : null,
                'pending_license' => $context['pending_license'],
            ],
            'message' => 'Plano selecionado com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }
}
