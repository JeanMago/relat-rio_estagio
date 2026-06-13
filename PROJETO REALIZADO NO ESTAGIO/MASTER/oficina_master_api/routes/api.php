<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthAccessLogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\EmpresaFinanceiroController;
use App\Http\Controllers\Api\EmpresaUsuarioController;
use App\Http\Controllers\Api\IbptController;
use App\Http\Controllers\Api\LicencaController;
use App\Http\Controllers\Api\LicencaCheckoutTokenController;
use App\Http\Controllers\Api\MasterUserController;
use App\Http\Controllers\Api\MercadoPagoWebhookController;
use App\Http\Controllers\Api\ModeloImpressaoController;
use App\Http\Controllers\Api\PlanoController;
use App\Http\Controllers\Api\DatabaseController;
use App\Http\Controllers\Api\PublicLicencaCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => true,
        'app' => 'fk_oficina_master_api',
    ]);
});

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/public/licencas/checkout-context', [PublicLicencaCheckoutController::class, 'context']);
Route::post('/public/licencas/checkout-status', [PublicLicencaCheckoutController::class, 'status']);
Route::post('/public/licencas/simular-pagamento', [PublicLicencaCheckoutController::class, 'simularPagamento']);
Route::post('/public/licencas/selecionar-plano', [PublicLicencaCheckoutController::class, 'selecionarPlano']);
Route::post('/public/licencas/pix', [PublicLicencaCheckoutController::class, 'gerarPix']);
Route::post('/public/payments/mercado-pago/webhook', MercadoPagoWebhookController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/master/auth-access-logs', [AuthAccessLogController::class, 'index']);
    Route::get('/master/dashboard/overview', [DashboardController::class, 'overview']);
    Route::post('/master/auth-access-logs', [AuthAccessLogController::class, 'store']);
    Route::get('/master/auth-access-logs/{id}', [AuthAccessLogController::class, 'show']);
    Route::post('/master/licencas/checkout-token', [LicencaCheckoutTokenController::class, 'store']);
    Route::get('/master/ibpt/versoes', [IbptController::class, 'index']);
    Route::post('/master/ibpt/importar', [IbptController::class, 'store']);
    Route::put('/master/ibpt/versoes/{id}', [IbptController::class, 'update']);
    Route::delete('/master/ibpt/versoes/{id}', [IbptController::class, 'destroy']);
    Route::get('/master/empresas/list-all', [EmpresaController::class, 'all']);
    Route::apiResource('/master/empresas', EmpresaController::class);
    Route::post('/master/empresas/test-connection', [EmpresaController::class, 'testConnection']);
    Route::post('/master/empresas/{id}/migrate-database', [EmpresaController::class, 'migrateDatabase']);
    Route::post('/master/empresas/{id}/download-database', [EmpresaController::class, 'downloadDatabase']);
    Route::get('/master/empresas/{empresa}/usuarios', [EmpresaUsuarioController::class, 'index']);
    Route::post('/master/empresas/{empresa}/usuarios', [EmpresaUsuarioController::class, 'store']);
    Route::put('/master/empresas/{empresa}/usuarios/{usuario}', [EmpresaUsuarioController::class, 'update']);
    Route::delete('/master/empresas/{empresa}/usuarios/{usuario}', [EmpresaUsuarioController::class, 'destroy']);
    Route::get('/master/empresas/{empresa}/financeiro', [EmpresaFinanceiroController::class, 'index']);
    Route::post('/master/empresas/{empresa}/financeiro/transacoes', [EmpresaFinanceiroController::class, 'storeTransacao']);
    Route::put('/master/empresas/{empresa}/financeiro/transacoes/{transacao}', [EmpresaFinanceiroController::class, 'updateTransacao']);
    Route::delete('/master/empresas/{empresa}/financeiro/transacoes/{transacao}', [EmpresaFinanceiroController::class, 'destroyTransacao']);
    Route::post('/master/empresas/{empresa}/financeiro/pagamentos', [EmpresaFinanceiroController::class, 'storePagamento']);
    Route::put('/master/empresas/{empresa}/financeiro/pagamentos/{pagamento}', [EmpresaFinanceiroController::class, 'updatePagamento']);
    Route::delete('/master/empresas/{empresa}/financeiro/pagamentos/{pagamento}', [EmpresaFinanceiroController::class, 'destroyPagamento']);
    Route::apiResource('/master/licencas', LicencaController::class);
    Route::apiResource('/master/modelos-impressao', ModeloImpressaoController::class);
    Route::get('/master/modelos-impressao-ativos', [ModeloImpressaoController::class, 'ativos']);
    
    // Rotas de Planos (Visualização permitida para Admin, edição apenas Super Admin)
    Route::get('/master/planos/available-modules', [PlanoController::class, 'availableModules']);
    Route::get('/master/planos', [PlanoController::class, 'index']);
    Route::get('/master/planos/{plano}', [PlanoController::class, 'show']);

    // Rotas restritas a Super Admin
    Route::middleware('super_admin')->group(function () {
        Route::post('/master/planos', [PlanoController::class, 'store']);
        Route::put('/master/planos/{plano}', [PlanoController::class, 'update']);
        Route::delete('/master/planos/{plano}', [PlanoController::class, 'destroy']);
        
        Route::apiResource('/master/master-users', MasterUserController::class);

        // Database Management
        Route::get('/master/databases', [DatabaseController::class, 'index']);
        Route::get('/master/databases/stats/{id?}', [DatabaseController::class, 'stats']);
        Route::post('/master/databases/migrate', [DatabaseController::class, 'migrate']);
        Route::post('/master/databases/execute', [DatabaseController::class, 'execute']);
        Route::get('/master/databases/tables/{target}', [DatabaseController::class, 'tables']);
        Route::get('/master/databases/data/{target}/{table}', [DatabaseController::class, 'tableData']);
        Route::post('/master/databases/update', [DatabaseController::class, 'updateRecord']);
    });
});
