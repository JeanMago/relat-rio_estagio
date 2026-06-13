<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Pagamento;
use App\Models\Transacao;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmpresaFinanceiroController extends Controller
{
    public function index(string $empresaId)
    {
        $empresa = $this->findEmpresa($empresaId);

        $transacoes = Transacao::query()
            ->where('empresa_uuid', $empresa->uuid)
            ->orderByDesc('data')
            ->orderByDesc('created_at')
            ->get();

        $pagamentos = Pagamento::query()
            ->where('empresa_uuid', $empresa->uuid)
            ->orderByDesc('data_pagamento')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => [
                'transacoes' => $transacoes,
                'pagamentos' => $pagamentos,
                'resumo' => [
                    'total_transacoes' => $transacoes->count(),
                    'total_pagamentos' => $pagamentos->count(),
                    'valor_transacoes' => $transacoes->sum('valor'),
                    'valor_pagamentos' => $pagamentos->sum('valor_pago'),
                ],
            ],
            'message' => null,
            'errors' => null,
            'meta' => null,
        ]);
    }

    public function storeTransacao(Request $request, string $empresaId)
    {
        $empresa = $this->findEmpresa($empresaId);
        $payload = $request->validate([
            'tipo' => ['required', 'string', 'max:255'],
            'referencia_id' => ['nullable', 'integer'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'valor' => ['nullable', 'numeric'],
            'desconto' => ['nullable', 'numeric'],
            'juros' => ['nullable', 'numeric'],
            'multa' => ['nullable', 'numeric'],
            'outros' => ['nullable', 'numeric'],
            'valorpago' => ['nullable', 'numeric'],
            'troco' => ['nullable', 'numeric'],
            'data' => ['required', 'date'],
            'status' => ['nullable', 'integer'],
            'user_uuid' => ['nullable', 'uuid'],
            'forma_pagamento' => ['nullable', 'string', 'max:255'],
        ]);

        $payload['uuid'] = (string) Str::uuid();
        $payload['empresa_uuid'] = $empresa->uuid;
        $row = Transacao::query()->create($payload);

        return $this->successItem($row->fresh(), 'Transacao criada com sucesso.', 201);
    }

    public function updateTransacao(Request $request, string $empresaId, string $transacaoId)
    {
        $empresa = $this->findEmpresa($empresaId);
        $row = Transacao::query()->where('empresa_uuid', $empresa->uuid)->where('uuid', $transacaoId)->firstOrFail();

        $payload = $request->validate([
            'tipo' => ['sometimes', 'string', 'max:255'],
            'referencia_id' => ['nullable', 'integer'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'valor' => ['nullable', 'numeric'],
            'desconto' => ['nullable', 'numeric'],
            'juros' => ['nullable', 'numeric'],
            'multa' => ['nullable', 'numeric'],
            'outros' => ['nullable', 'numeric'],
            'valorpago' => ['nullable', 'numeric'],
            'troco' => ['nullable', 'numeric'],
            'data' => ['sometimes', 'date'],
            'status' => ['nullable', 'integer'],
            'user_uuid' => ['nullable', 'uuid'],
            'forma_pagamento' => ['nullable', 'string', 'max:255'],
        ]);

        $row->update($payload);

        return $this->successItem($row->fresh(), 'Transacao atualizada com sucesso.');
    }

    public function destroyTransacao(string $empresaId, string $transacaoId)
    {
        $empresa = $this->findEmpresa($empresaId);
        $row = Transacao::query()->where('empresa_uuid', $empresa->uuid)->where('uuid', $transacaoId)->firstOrFail();
        $row->delete();

        return $this->successMessage('Transacao removida com sucesso.');
    }

    public function storePagamento(Request $request, string $empresaId)
    {
        $empresa = $this->findEmpresa($empresaId);
        $payload = $request->validate([
            'valor_pago' => ['required', 'numeric'],
            'data_pagamento' => ['required', 'date'],
            'forma_pagamento' => ['nullable', 'string', 'max:255'],
            'referencia_transacao' => ['nullable', 'integer'],
            'usuario_uuid' => ['nullable', 'uuid'],
        ]);

        $payload['empresa_uuid'] = $empresa->uuid;
        $row = Pagamento::query()->create($payload);

        return $this->successItem($row->fresh(), 'Pagamento criado com sucesso.', 201);
    }

    public function updatePagamento(Request $request, string $empresaId, string $pagamentoId)
    {
        $empresa = $this->findEmpresa($empresaId);
        $row = Pagamento::query()->where('empresa_uuid', $empresa->uuid)->where('id', $pagamentoId)->firstOrFail();

        $payload = $request->validate([
            'valor_pago' => ['sometimes', 'numeric'],
            'data_pagamento' => ['sometimes', 'date'],
            'forma_pagamento' => ['nullable', 'string', 'max:255'],
            'referencia_transacao' => ['nullable', 'integer'],
            'usuario_uuid' => ['nullable', 'uuid'],
        ]);

        $row->update($payload);

        return $this->successItem($row->fresh(), 'Pagamento atualizado com sucesso.');
    }

    public function destroyPagamento(string $empresaId, string $pagamentoId)
    {
        $empresa = $this->findEmpresa($empresaId);
        $row = Pagamento::query()->where('empresa_uuid', $empresa->uuid)->where('id', $pagamentoId)->firstOrFail();
        $row->delete();

        return $this->successMessage('Pagamento removido com sucesso.');
    }

    private function findEmpresa(string $empresaId): Empresa
    {
        return Empresa::query()->where('uuid', trim($empresaId))->firstOrFail();
    }

    private function successItem($row, ?string $message = null, int $status = 200)
    {
        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $row,
            'message' => $message,
            'errors' => null,
            'meta' => null,
        ], $status);
    }

    private function successMessage(string $message)
    {
        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => null,
            'message' => $message,
            'errors' => null,
            'meta' => null,
        ]);
    }
}
