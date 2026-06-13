<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Licenca;
use Illuminate\Http\Request;

class LicencaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'bloqueada' => ['nullable', 'boolean'],
            'empresa_uuid' => ['nullable', 'uuid'],
            'tenant_uuid' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = $this->baseQuery();

        // Restrição de acesso para Admin
        if ($user->perfil !== 'super_admin') {
            $query->where('empresa_uuid', $user->empresa_uuid);
        } else {
            // Se for Super Admin, permite filtrar por empresa enviada no request
            foreach (['empresa_uuid', 'tenant_uuid'] as $column) {
                if (!empty($filters[$column])) {
                    $query->where($column, $filters[$column]);
                }
            }
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('empresa_uuid', 'like', "%{$search}%")
                    ->orWhere('tenant_uuid', 'like', "%{$search}%")
                    ->orWhere('forma_pagamento', 'like', "%{$search}%")
                    ->orWhere('modulos', 'like', "%{$search}%")
                    ->orWhereHas('empresa', function ($empresaQuery) use ($search) {
                        $empresaQuery->where('nome', 'like', "%{$search}%")
                            ->orWhere('apelido', 'like', "%{$search}%");
                    })
                    ->orWhereHas('plano', function ($planoQuery) use ($search) {
                        $planoQuery->where('nome', 'like', "%{$search}%");
                    });
            });
        }

        if (array_key_exists('bloqueada', $filters)) {
            $query->where('bloqueada', (bool) $filters['bloqueada']);
        }

        $rows = $query->paginate((int) ($filters['limit'] ?? 30));

        return $this->successList($rows);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        // Se não for super admin, o campo empresa_uuid deve ser o dele, 
        // e ele não pode definir a data de expiração real ainda.
        if ($user->perfil !== 'super_admin') {
            $request->merge([
                'empresa_uuid' => $user->empresa_uuid,
            ]);
        }

        $payload = $this->validatePayload($request);

        if ($user->perfil !== 'super_admin') {
            // Garante que a licença comece hoje se não informado
            $dataInicio = $payload['data_inicio'] ?? now();
            $payload['data_inicio'] = $dataInicio;
            
            // Trava de Segurança: A data de expiração é setada IGUAL à de início.
            // Isso cria a licença mas com 0 dias de crédito, até o Super Admin validar o pagamento.
            $payload['data_expiracao'] = $dataInicio;
            $payload['bloqueada'] = false; // Começa desbloqueada, mas sem dias não adianta.
        }

        $licenca = Licenca::query()->create($payload);
        $this->syncTenantStatus($licenca->empresa_uuid);
        
        $msg = $user->perfil === 'super_admin' 
            ? 'Licenca criada com sucesso.' 
            : 'Solicitação de licença enviada. Os dias serão liberados após a confirmação do pagamento.';

        return $this->successItem($this->baseQuery()->findOrFail($licenca->id), $msg, 201);
    }

    public function show(string $id)
    {
        $user = request()->user();
        $licenca = $this->baseQuery()->findOrFail($id);

        if ($user->perfil !== 'super_admin' && $licenca->empresa_uuid !== $user->empresa_uuid) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Acesso negado.');
        }

        return $this->successItem($licenca);
    }

    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $licenca = Licenca::query()->findOrFail($id);

        if ($user->perfil !== 'super_admin' && $licenca->empresa_uuid !== $user->empresa_uuid) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Acesso negado.');
        }

        $payload = $this->validatePayload($request, true);

        // Bloqueios para Admin comum na edição
        if ($user->perfil !== 'super_admin') {
            unset($payload['empresa_uuid']);
            unset($payload['data_expiracao']);
            unset($payload['valor']); // Evita que ele mude o preço sozinho
            unset($payload['bloqueada']);
        }

        $licenca->update($payload);
        $this->syncTenantStatus($licenca->empresa_uuid);
        return $this->successItem($this->baseQuery()->findOrFail($licenca->id), 'Licenca atualizada com sucesso.');
    }

    public function destroy(string $id)
    {
        $user = request()->user();
        $licenca = Licenca::query()->findOrFail($id);

        if ($user->perfil !== 'super_admin') {
            throw new \Illuminate\Auth\Access\AuthorizationException('Ação exclusiva para Super Admin.');
        }

        $empresaUuid = $licenca->empresa_uuid;
        $licenca->delete();
        $this->syncTenantStatus($empresaUuid);

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => null,
            'message' => 'Licenca removida com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }

    private function syncTenantStatus(string $empresaUuid): void
    {
        $tenant = \App\Models\Tenant::where('empresa_uuid', $empresaUuid)->first();
        if (!$tenant) return;

        // Busca a licença mais atual (maior data de expiração)
        $ultimaLicenca = Licenca::where('empresa_uuid', $empresaUuid)
            ->orderByDesc('data_expiracao')
            ->first();

        if (!$ultimaLicenca) {
            $tenant->update(['status' => 'bloqueado']);
            return;
        }

        $expirada = $ultimaLicenca->data_expiracao < now()->startOfDay();
        $bloqueada = (bool) $ultimaLicenca->bloqueada;

        $novoStatus = ($bloqueada || $expirada) ? 'bloqueado' : 'ativo';

        $tenant->update([
            'status' => $novoStatus,
            'licenca_valida_ate' => $ultimaLicenca->data_expiracao,
            'plano' => $ultimaLicenca->plano_id,
        ]);
    }

    private function baseQuery()
    {
        return Licenca::query()
            ->with([
                'empresa' => function($query) {
                    $query->withTrashed()->select('uuid', 'nome', 'apelido', 'cpf_cnpj');
                }, 
                'plano:id,nome,status'
            ])
            ->orderByDesc('data_expiracao')
            ->orderByDesc('id');
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        // Tenta converter modulos de string (JSON ou CSV) para array se necessário
        if ($request->has('modulos') && is_string($request->modulos)) {
            $decoded = json_decode($request->modulos, true);
            if (is_array($decoded)) {
                $request->merge(['modulos' => $decoded]);
            } else {
                // Fallback para string separada por vírgula (ex: "financeiro,estoque")
                $val = trim($request->modulos);
                $request->merge(['modulos' => $val ? explode(',', $val) : []]);
            }
        }

        return $request->validate([
            'empresa_uuid' => [$required, 'uuid'],
            'tenant_uuid' => ['nullable', 'uuid'],
            'plano_id' => ['nullable', 'integer'],
            'vendedor_uuid' => ['nullable', 'uuid'],
            'tipo_contratacao' => ['nullable', 'boolean'],
            'tipo_experiencia' => ['nullable', 'boolean'],
            'tipo_demonstracao' => ['nullable', 'boolean'],
            'renovacao_automatica' => ['nullable', 'boolean'],
            'ocultar_mensagem_vencimento' => ['nullable', 'boolean'],
            'forma_pagamento_renovacao' => ['nullable', 'string', 'max:255'],
            'bloqueada' => ['nullable', 'boolean'],
            'data_inicio' => ['nullable', 'date'],
            'data_expiracao' => ['nullable', 'date'],
            'usuario_adicionais' => ['nullable', 'integer'],
            'limit_user' => ['nullable', 'integer'],
            'espaco_disco' => ['nullable', 'integer'],
            'espaco_disco_adicional' => ['nullable', 'integer'],
            'empresas_disponiveis' => ['nullable', 'integer'],
            'valor_empresa_disponivel' => ['nullable', 'numeric'],
            'valor_usuario_adicional' => ['nullable', 'numeric'],
            'valor_espaco_adicional' => ['nullable', 'numeric'],
            'valor' => ['nullable', 'numeric'],
            'valor_revenda' => ['nullable', 'numeric'],
            'forma_pagamento' => ['nullable', 'string', 'max:255'],
            'modulos' => ['nullable', 'array'],
            'empresa_para_nfse' => ['nullable', 'integer'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }

    private function successList($rows)
    {
        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $rows->items(),
            'message' => null,
            'errors' => null,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    private function successItem(?Licenca $licenca, ?string $message = null, int $status = 200)
    {
        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $licenca,
            'message' => $message,
            'errors' => null,
            'meta' => null,
        ], $status);
    }
}
