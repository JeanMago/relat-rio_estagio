<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\MasterUser;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmpresaUsuarioController extends Controller
{
    public function index(string $empresaId)
    {
        $empresa = $this->findEmpresa($empresaId);
        $tenant = $empresa->tenant;

        $rows = $tenant
            ? TenantUser::query()->where('tenant_uuid', $tenant->uuid)->orderBy('name')->get()
            : collect();

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $rows,
            'message' => null,
            'errors' => null,
            'meta' => [
                'total' => $rows->count(),
            ],
        ]);
    }

    public function store(Request $request, string $empresaId)
    {
        $empresa = $this->findEmpresa($empresaId);
        $tenant = $empresa->tenant;

        abort_if(!$tenant, 422, 'A empresa precisa ter um tenant para cadastrar usuarios.');

        $payload = $this->validatePayload($request);
        $payload['uuid'] = (string) Str::uuid();
        $payload['tenant_uuid'] = $tenant->uuid;

        $user = TenantUser::query()->create($payload);

        // Sincronizar com MasterUser
        $this->syncMasterUser($empresa, $user, $payload['password'] ?? null);

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $user->fresh(),
            'message' => 'Usuario criado com sucesso.',
            'errors' => null,
            'meta' => null,
        ], 201);
    }

    public function update(Request $request, string $empresaId, string $userId)
    {
        $empresa = $this->findEmpresa($empresaId);
        $user = $this->findEmpresaUser($empresa, $userId);
        $payload = $this->validatePayload($request, true, $user);

        $password = null;
        if (array_key_exists('password', $payload)) {
            if (!$payload['password']) {
                unset($payload['password']);
            } else {
                $password = $payload['password'];
            }
        }

        $user->update($payload);

        // Sincronizar com MasterUser
        $this->syncMasterUser($empresa, $user->fresh(), $password);

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $user->fresh(),
            'message' => 'Usuario atualizado com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }

    public function destroy(string $empresaId, string $userId)
    {
        $empresa = $this->findEmpresa($empresaId);
        $user = $this->findEmpresaUser($empresa, $userId);

        // Remover do MasterUser
        MasterUser::query()
            ->where('email', $user->email)
            ->where('empresa_uuid', $empresa->uuid)
            ->delete();

        $user->delete();

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => null,
            'message' => 'Usuario removido com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }

    private function findEmpresa(string $empresaId): Empresa
    {
        $user = request()->user();
        if ($user && $user->perfil !== 'super_admin') {
            if ($empresaId !== $user->empresa_uuid) {
                 throw new \Illuminate\Auth\Access\AuthorizationException('Acesso negado.');
            }
        }

        $empresa = Empresa::query()
            ->with('tenant.users')
            ->where('uuid', trim($empresaId))
            ->first();

        if (!$empresa) {
            $tenant = Tenant::query()->where('uuid', trim($empresaId))->first();
            
            if (!$tenant) {
                // Se não achou nem por empresa nem por tenant ID, dá o erro padrão
                return Empresa::query()->where('uuid', trim($empresaId))->firstOrFail();
            }
            
            if ($tenant->empresa_uuid) {
                return $this->findEmpresa($tenant->empresa_uuid);
            }

            // Fallback para empresas que só existem em 'tenants' (como teste e teste2)
            $empresa = new Empresa();
            $empresa->uuid = $tenant->uuid;
            $empresa->nome = $tenant->name;
            $empresa->setRelation('tenant', $tenant);
        }

        return $empresa;
    }

    private function findEmpresaUser(Empresa $empresa, string $userId): TenantUser
    {
        $tenantUuid = $empresa->tenant?->uuid ?? $empresa->uuid;

        return TenantUser::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where(function ($query) use ($userId) {
                $query->where('uuid', trim($userId))
                    ->orWhere('email', trim($userId));
            })
            ->firstOrFail();
    }

    private function validatePayload(Request $request, bool $partial = false, ?TenantUser $user = null): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $passwordRule = $partial ? 'nullable' : 'required';
        $emailRule = Rule::unique('users', 'email');

        if ($user) {
            $emailRule = $emailRule->ignore($user->uuid, 'uuid');
        }

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255', $emailRule],
            'password' => [$passwordRule, 'string', 'min:6', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:16'],
            'role' => ['nullable', Rule::in(['master', 'agenciador', 'admin', 'user'])],
            'status' => ['nullable', 'boolean'],
            'dashboard' => ['nullable', 'string', 'max:40'],
            'is_tecnico' => ['nullable', 'boolean'],
            'cpf' => ['nullable', 'string', 'max:15'],
            'rg' => ['nullable', 'string', 'max:15'],
            'cep' => ['nullable', 'string', 'max:15'],
            'endereco' => ['nullable', 'string', 'max:50'],
            'bairro' => ['nullable', 'string', 'max:30'],
            'complemento' => ['nullable', 'string', 'max:30'],
            'cidade' => ['nullable', 'string', 'max:25'],
            'uf' => ['nullable', 'string', 'max:2'],
        ]);
    }

    private function syncMasterUser(Empresa $empresa, TenantUser $user, ?string $plainPassword = null): void
    {
        // 1. Tenta achar o MasterUser pelo UUID (mais confiável) ou pelo Email
        $masterUser = MasterUser::query()->where('uuid', $user->uuid)->first();
        if (!$masterUser) {
            $masterUser = MasterUser::query()->where('email', $user->email)->first();
        }

        $data = [
            'nome' => $user->name,
            'email' => $user->email,
            'telefone' => $user->telefone,
            'status' => $user->status,
            'perfil' => ($user->role === 'admin' || $user->role === 'master') ? 'admin' : 'user',
            'empresa_uuid' => $empresa->uuid,
        ];

        if ($plainPassword) {
            $data['password'] = $plainPassword;
        } elseif (!$masterUser) {
            // Se o usuário não existe no master, precisamos de uma senha para o insert.
            // Pegamos o hash atual do banco do tenant.
            $data['password'] = $user->password;
        }

        if ($masterUser) {
            $masterUser->update($data);
            // Garante que o UUID seja sincronizado caso tenha achado pelo e-mail
            if ($masterUser->uuid !== $user->uuid) {
                $masterUser->update(['uuid' => $user->uuid]);
            }
        } else {
            MasterUser::query()->create(array_merge(['uuid' => $user->uuid], $data));
        }
    }
}
