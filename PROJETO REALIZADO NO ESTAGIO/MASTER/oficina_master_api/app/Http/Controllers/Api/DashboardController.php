<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\AuthAccessLog;
use App\Models\Pagamento;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function overview()
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();

        $empresasAtivas = Empresa::where('status', 1)->count();
        $licencasAtivas = Licenca::where('bloqueada', false)
            ->whereDate('data_expiracao', '>=', $now->toDateString())
            ->count();
        
        $acessosHoje = AuthAccessLog::whereDate('logged_in_at', $now->toDateString())->count();
        
        $faturacaoMes = Pagamento::whereDate('data_pagamento', '>=', $startOfMonth->toDateString())
            ->sum('valor_pago');

        // Trends (simple logic for demonstration)
        $lastMonth = $now->copy()->subMonth();
        $startOfLastMonth = $lastMonth->copy()->startOfMonth();
        $endOfLastMonth = $lastMonth->copy()->lastOfMonth();

        $faturacaoMesAnterior = Pagamento::whereBetween('data_pagamento', [
            $startOfLastMonth->startOfDay()->toDateTimeString(), 
            $endOfLastMonth->endOfDay()->toDateTimeString()
        ])->sum('valor_pago');
        
        $trendFaturacao = '';
        if ($faturacaoMesAnterior > 0) {
            $diff = (($faturacaoMes - $faturacaoMesAnterior) / $faturacaoMesAnterior) * 100;
            $trendFaturacao = ($diff >= 0 ? '+' : '') . number_format($diff, 1) . '%';
        }

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => [
                'empresas_ativas' => $empresasAtivas,
                'licencas_ativas' => $licencasAtivas,
                'acessos_hoje' => $acessosHoje,
                'faturacao_mes' => (float) $faturacaoMes,
                'trends' => [
                    'empresas' => '+0%',
                    'licencas' => '+0%',
                    'acessos' => '+0%',
                    'faturacao' => $trendFaturacao,
                ]
            ],
            'message' => null,
            'errors' => null,
            'meta' => null,
        ]);
    }
}
