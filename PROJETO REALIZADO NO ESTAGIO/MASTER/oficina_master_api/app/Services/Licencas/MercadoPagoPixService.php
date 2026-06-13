<?php

namespace App\Services\Licencas;

use App\Models\LicencaCobranca;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MercadoPagoPixService
{
    public function createPixPayment(LicencaCobranca $cobranca, Tenant $tenant, ?TenantUser $user = null): array
    {
        $response = Http::withToken((string) config('services.mercado_pago.access_token'))
            ->withHeaders([
                'X-Idempotency-Key' => $cobranca->uuid,
                'Accept' => 'application/json',
            ])
            ->post(rtrim((string) config('services.mercado_pago.base_url'), '/') . '/v1/payments', [
                'transaction_amount' => (float) $cobranca->amount,
                'description' => $cobranca->description ?: 'Renovacao de licenca',
                'payment_method_id' => 'pix',
                'external_reference' => $cobranca->external_reference,
                'notification_url' => $this->resolveWebhookUrl(),
                'payer' => [
                    'email' => $user?->email ?: $tenant->empresa?->email ?: 'pagamentos@example.com',
                    'first_name' => $user?->name ?: $tenant->name,
                ],
            ]);

        if ($response->failed()) {
            $message = $this->extractErrorMessage($response->json(), $response->body());

            Log::error('Falha ao criar pagamento PIX no Mercado Pago.', [
                'http_status' => $response->status(),
                'response_body' => $response->body(),
                'tenant_uuid' => $tenant->uuid,
                'user_uuid' => $user?->uuid,
                'cobranca_uuid' => $cobranca->uuid,
            ]);

            throw new RuntimeException(
                sprintf('Nao foi possivel criar o pagamento PIX no Mercado Pago. [%s] %s', $response->status(), $message)
            );
        }

        return (array) $response->json();
    }

    public function fetchPayment(string $paymentId): array
    {
        $response = Http::withToken((string) config('services.mercado_pago.access_token'))
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->get(rtrim((string) config('services.mercado_pago.base_url'), '/') . '/v1/payments/' . $paymentId);

        if ($response->failed()) {
            $message = $this->extractErrorMessage($response->json(), $response->body());

            Log::error('Falha ao consultar pagamento PIX no Mercado Pago.', [
                'http_status' => $response->status(),
                'response_body' => $response->body(),
                'payment_id' => $paymentId,
            ]);

            throw new RuntimeException(
                sprintf('Nao foi possivel consultar o pagamento PIX no Mercado Pago. [%s] %s', $response->status(), $message)
            );
        }

        return (array) $response->json();
    }

    private function resolveWebhookUrl(): string
    {
        return (string) (config('services.mercado_pago.webhook_url') ?: url('/api/public/payments/mercado-pago/webhook'));
    }

    private function extractErrorMessage(mixed $json, string $fallbackBody): string
    {
        if (is_array($json)) {
            $message = $json['message'] ?? $json['error'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }

            if (!empty($json['cause']) && is_array($json['cause'])) {
                $firstCause = $json['cause'][0] ?? null;
                if (is_array($firstCause)) {
                    $causeMessage = $firstCause['description'] ?? $firstCause['code'] ?? null;
                    if (is_string($causeMessage) && $causeMessage !== '') {
                        return $causeMessage;
                    }
                }
            }
        }

        return $fallbackBody !== '' ? $fallbackBody : 'Resposta vazia do provedor.';
    }
}
