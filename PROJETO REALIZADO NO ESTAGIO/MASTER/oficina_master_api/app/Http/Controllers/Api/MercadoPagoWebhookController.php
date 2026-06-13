<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Licencas\LicencaPixCheckoutService;
use Illuminate\Http\Request;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private readonly LicencaPixCheckoutService $licencaPixCheckoutService,
    ) {
    }

    public function __invoke(Request $request)
    {
        $paymentPayload = $request->input('data', []);
        $paymentId = $paymentPayload['id']
            ?? $request->input('id')
            ?? $request->query('id')
            ?? $request->query('data.id');

        if (!$paymentId) {
            return response()->json(['status' => true, 'message' => 'Webhook recebido sem pagamento.']);
        }

        $this->licencaPixCheckoutService->handleProviderPayment([
            'id' => $paymentId,
        ]);

        return response()->json(['status' => true]);
    }
}
