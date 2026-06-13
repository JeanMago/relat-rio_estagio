<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthAccessLog;
use App\Models\Empresa;
use App\Models\Licenca;
use App\Models\MasterUser;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\Tenants\TenantDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmpresaController extends Controller
{
    public function __construct(
        protected TenantDatabaseService $databaseService
    ) {}

    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'url_banco' => ['required', 'string'],
            'porta' => ['nullable', 'integer'],
            'database' => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->databaseService->testConnection($validated);

        // Registro de Log
        $this->logActivity($request, [
            'evento' => 'test_connection',
            'resultado' => $result['success'] ? 'sucesso' : 'falha',
            'metadata' => [
                'database' => $validated['database'],
                'url' => $validated['url_banco'],
                'message' => $result['message']
            ]
        ]);

        return response()->json($result);
    }

    public function migrateDatabase(Request $request, string $id)
    {
        $empresa = $this->findEmpresa($id);
        $tenant = $empresa->tenant;

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant não encontrado.'], 404);
        }

        $password = $request->input('password') ?: $tenant->password;

        if (!$password) {
            return response()->json(['success' => false, 'message' => 'Senha do banco não fornecida.'], 422);
        }

        $result = $this->databaseService->createAndInitialize($tenant, $password);

        // Registro de Log
        $this->logActivity($request, [
            'evento' => 'migrate_database',
            'resultado' => $result['success'] ? 'sucesso' : 'falha',
            'empresa_uuid' => $empresa->uuid,
            'tenant_uuid' => $tenant->uuid,
            'metadata' => [
                'database' => $tenant->database,
                'message' => $result['message']
            ]
        ]);

        return response()->json($result);
    }

    public function downloadDatabase(Request $request, string $id)
    {
        $empresa = $this->findEmpresa($id);
        $tenant = $empresa->tenant;

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant não encontrado.'], 404);
        }

        try {
            $path = $this->databaseService->exportDatabase($tenant, $request->input('password'));
            
            // Registro de Log
            $this->logActivity($request, [
                'evento' => 'download_database',
                'resultado' => 'sucesso',
                'empresa_uuid' => $empresa->uuid,
                'tenant_uuid' => $tenant->uuid,
                'metadata' => ['database' => $tenant->database]
            ]);

            return response()->download($path)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            $this->logActivity($request, [
                'evento' => 'download_database',
                'resultado' => 'falha',
                'empresa_uuid' => $empresa->uuid,
                'tenant_uuid' => $tenant?->uuid,
                'failure_reason' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function logActivity(Request $request, array $data)
    {
        try {
            $user = $request->user();
            \App\Models\AuthAccessLog::create(array_merge([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'origem_app' => 'oficina_master_app',
                'master_user_uuid' => $user?->uuid,
                'email' => $user?->email,
                'nome' => $user?->nome,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'logged_in_at' => now(),
            ], $data));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro ao gravar log de atividade: " . $e->getMessage());
        }
    }

    public function all(Request $request)
    {
        $user = $request->user();
        
        // Busca empresas e tenants que podem não estar vinculados a uma empresa ainda
        $query = DB::table('tenants')
            ->leftJoin('empresas', 'tenants.empresa_uuid', '=', 'empresas.uuid')
            ->select([
                DB::raw('COALESCE(empresas.uuid, tenants.uuid) as uuid'),
                DB::raw('COALESCE(empresas.nome, tenants.name) as nome'),
                'empresas.cpf_cnpj',
                'tenants.status as status_tenant',
                'empresas.status as status_empresa'
            ]);

        // Restrição de acesso para Admin
        if ($user->perfil !== 'super_admin') {
            $query->where(function($q) use ($user) {
                $q->where('empresas.uuid', $user->empresa_uuid)
                  ->orWhere('tenants.uuid', $user->empresa_uuid);
            });
        }

        $rows = $query->orderBy('nome')->get();

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

    public function index(Request $request)
    {
        $user = $request->user();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = DB::table('tenants')
            ->leftJoin('empresas', 'tenants.empresa_uuid', '=', 'empresas.uuid')
            ->select([
                DB::raw('COALESCE(empresas.uuid, tenants.uuid) as uuid'),
                DB::raw('COALESCE(empresas.nome, tenants.name) as nome'),
                'empresas.apelido',
                'empresas.cpf_cnpj',
                'empresas.email',
                'tenants.database',
                'tenants.status as tenant_status',
                'empresas.status as empresa_status',
            ]);

        // Restrição de acesso para Admin
        if ($user->perfil !== 'super_admin') {
            $query->where(function($q) use ($user) {
                $q->where('empresas.uuid', $user->empresa_uuid)
                  ->orWhere('tenants.uuid', $user->empresa_uuid);
            });
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('empresas.nome', 'like', "%{$search}%")
                    ->orWhere('tenants.name', 'like', "%{$search}%")
                    ->orWhere('empresas.apelido', 'like', "%{$search}%")
                    ->orWhere('empresas.cpf_cnpj', 'like', "%{$search}%")
                    ->orWhere('empresas.email', 'like', "%{$search}%")
                    ->orWhere('tenants.uuid', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status'])) {
            $status = (int) $filters['status'];
            $query->where(function($q) use ($status) {
                $tenantStatusMap = [
                    1 => 'ativo',
                    2 => 'desativado',
                    3 => 'bloqueado'
                ];
                $tStatus = $tenantStatusMap[$status] ?? 'ativo';
                
                $q->where('empresas.status', $status)
                  ->orWhere('tenants.status', $tStatus);
            });
        }

        $rows = $query->orderBy('nome')->paginate((int) ($filters['limit'] ?? 30));

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

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $createDatabase = (bool) ($request->input('create_database') ?? false);

        $empresa = DB::transaction(function () use ($validated, $createDatabase) {
            $empresaData = $validated['empresa'];
            $tenantData = $validated['tenant'];
            $userData = $validated['usuario'];

            $empresaData['uuid'] = (string) Str::uuid();
            $empresa = Empresa::query()->create($empresaData);

            $tenant = $this->saveTenant($empresa, null, $tenantData);
            $this->saveTenantUser($tenant, null, $userData);

            // Criar licença inicial de 30 dias usando o plano do tenant ou o primeiro disponível
            $this->createInitialLicence($empresa, $tenant, $tenantData['plano'] ?? null);

            if ($createDatabase) {
                $dbResult = $this->databaseService->createAndInitialize(
                    $tenant, 
                    $tenantData['password'], 
                    $userData
                );
                
                if (!$dbResult['success']) {
                    throw new \Exception("Empresa salva, mas falha ao criar banco: " . $dbResult['message']);
                }
            }

            return $this->findEmpresa($empresa->uuid);
        });

        return $this->successItem($empresa, 'Empresa criada com sucesso.', 201);
    }

    private function createInitialLicence(Empresa $empresa, Tenant $tenant, $planoId = null)
    {
        $plano = null;
        if ($planoId) {
            $plano = \App\Models\Plano::find($planoId);
        }
        
        if (!$plano) {
            $plano = \App\Models\Plano::where('status', 'ativo')->first() ?? \App\Models\Plano::first();
        }
        
        return Licenca::create([
            'empresa_uuid' => $empresa->uuid,
            'tenant_uuid' => $tenant->uuid,
            'plano_id' => $plano?->id,
            'data_inicio' => now(),
            'data_expiracao' => now()->addDays(30),
            'bloqueada' => false,
            'tipo_experiencia' => true,
            'limit_user' => $plano?->limit_user ?? 5,
            'espaco_disco' => $plano?->espaco_disco ?? 1024,
            'valor' => $plano?->valor ?? 0,
            'modulos' => $plano?->modulos ?? '[]',
        ]);
    }

    public function show(string $id)
    {
        return $this->successItem($this->findEmpresa($id));
    }

    public function update(Request $request, string $id)
    {
        $empresa = $this->findEmpresa($id);
        $validated = $this->validatePayload($request, true, $empresa);

        $empresa = DB::transaction(function () use ($validated, $empresa) {
            $tenant = $empresa->tenant;

            // 1. Se a empresa ainda não existe na tabela 'empresas' (caso de standalone tenants)
            if (!$empresa->exists && !empty($validated['empresa'])) {
                $empresaData = $validated['empresa'];
                $empresaData['uuid'] = (string) Str::uuid();
                $empresa = Empresa::query()->create($empresaData);
                
                // Vincula o tenant a esta nova empresa
                if ($tenant) {
                    $tenant->update(['empresa_uuid' => $empresa->uuid]);
                }
            } 
            // 2. Se a empresa já existe, apenas atualiza
            elseif ($empresa->exists && !empty($validated['empresa'])) {
                $empresa->update($validated['empresa']);
            }

            // Sincronizar status e nome com o Tenant
            if ($tenant) {
                $tenantUpdate = [];
                
                if (isset($validated['empresa']['nome'])) {
                    $tenantUpdate['name'] = $validated['empresa']['nome'];
                }

                if (isset($validated['empresa']['status'])) {
                    $statusMap = [1 => 'ativo', 2 => 'desativado', 3 => 'bloqueado'];
                    $tenantUpdate['status'] = $statusMap[$validated['empresa']['status']] ?? 'bloqueado';
                }

                if (!empty($tenantUpdate)) {
                    $tenant->update($tenantUpdate);
                }
            }

            if (array_key_exists('tenant', $validated)) {
                $tenant = $this->saveTenant($empresa, $tenant, $validated['tenant']);
            }

            if (array_key_exists('usuario', $validated)) {
                $tenant ??= $empresa->tenant;
                if ($tenant) {
                    $existingUser = $tenant->users()->orderBy('created_at')->first();
                    $this->saveTenantUser($tenant, $existingUser, $validated['usuario']);
                }
            }

            return $this->findEmpresa($empresa->uuid);
        });

        return $this->successItem($empresa, 'Empresa atualizada com sucesso.');
    }

    public function destroy(string $id)
    {
        $empresa = $this->findEmpresa($id);
        $empresa->delete();

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => null,
            'message' => 'Empresa removida com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }

    private function findEmpresa(string $id): Empresa
    {
        $user = request()->user();
        
        // Se não for super admin, só pode acessar a própria empresa
        if ($user && $user->perfil !== 'super_admin') {
            if ($id !== $user->empresa_uuid) {
                 throw new \Illuminate\Auth\Access\AuthorizationException('Acesso negado à empresa solicitada.');
            }
        }

        // 1. Tenta buscar na tabela de empresas
        $empresa = Empresa::query()
            ->with([
                'tenant',
                'tenant.users' => fn ($query) => $query->orderBy('created_at'),
                'licencas' => fn ($query) => $query->with('plano:id,nome,status')->orderByDesc('data_expiracao')->orderByDesc('id'),
            ])
            ->where('uuid', trim($id))
            ->first();

        if ($empresa) {
            return $empresa;
        }

        // 2. Se não achou, tenta buscar na tabela de tenants
        $tenant = Tenant::query()
            ->with(['users' => fn ($query) => $query->orderBy('created_at')])
            ->where('uuid', trim($id))
            ->first();

        if ($tenant) {
            // Se o tenant já tem empresa, busca a empresa real
            if ($tenant->empresa_uuid) {
                return $this->findEmpresa($tenant->empresa_uuid);
            }

            // Se o tenant não tem empresa (caso de teste/teste2), retorna uma "Empresa Virtual"
            // que será persistida no banco master assim que o usuário clicar em 'Salvar'
            $virtualEmpresa = new Empresa();
            $virtualEmpresa->uuid = $tenant->uuid; // Temporário
            $virtualEmpresa->nome = $tenant->name;
            $virtualEmpresa->status = $tenant->status === 'ativo' ? 1 : 3;
            $virtualEmpresa->setRelation('tenant', $tenant);
            $virtualEmpresa->setRelation('licencas', collect());
            
            return $virtualEmpresa;
        }

        throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Empresa::class, [$id]);
    }

    private function validatePayload(Request $request, bool $partial = false, ?Empresa $empresa = null): array
    {
        $user = $request->user();
        $empresaPrefix = $request->has('empresa.') || $request->has('tenant') || $request->has('usuario') ? 'empresa.' : '';
        $required = $partial ? 'sometimes' : 'required';
        $tenantRequired = $partial ? 'sometimes' : 'required';
        $usuarioRequired = $partial ? 'sometimes' : 'required';

        $existingTenant = $empresa?->tenant;
        $existingUser = $existingTenant?->users()->orderBy('created_at')->first();

        $emailRule = Rule::unique('users', 'email');
        if ($existingUser) {
            $emailRule = $emailRule->ignore($existingUser->uuid, 'uuid');
        }

        $rules = [
            "{$empresaPrefix}uuid" => ['nullable', 'uuid'],
            "{$empresaPrefix}status" => [$required, 'integer', 'min:1', 'max:3'],
            "{$empresaPrefix}nome" => [$required, 'string', 'max:250'],
            "{$empresaPrefix}apelido" => ['nullable', 'string', 'max:100'],
            "{$empresaPrefix}cpf_cnpj" => ['nullable', 'string', 'max:14'],
            "{$empresaPrefix}nascimento" => ['nullable', 'date'],
            "{$empresaPrefix}site" => ['nullable', 'string', 'max:150'],
            "{$empresaPrefix}rg" => ['nullable', 'string', 'max:15'],
            "{$empresaPrefix}regime_tributario" => ['nullable', 'integer'],
            "{$empresaPrefix}contribuinte" => ['nullable', 'integer'],
            "{$empresaPrefix}ie" => ['nullable', 'string', 'max:15'],
            "{$empresaPrefix}im" => ['nullable', 'string', 'max:15'],
            "{$empresaPrefix}ir" => ['nullable', 'string', 'max:15'],
            "{$empresaPrefix}suframa" => ['nullable', 'string', 'max:15'],
            "{$empresaPrefix}cnae" => ['nullable', 'string', 'max:7'],
            "{$empresaPrefix}sub_st" => ['nullable', 'string', 'max:15'],
            "{$empresaPrefix}email" => ['nullable', 'email', 'max:60'],
            "{$empresaPrefix}telefone" => ['nullable', 'string', 'max:15'],
            "{$empresaPrefix}cep" => ['nullable', 'string', 'max:15'],
            "{$empresaPrefix}logradouro" => ['nullable', 'string', 'max:30'],
            "{$empresaPrefix}uf" => ['nullable', 'string', 'max:2'],
            "{$empresaPrefix}cod_uf" => ['nullable', 'string', 'max:2'],
            "{$empresaPrefix}municipio" => ['nullable', 'string', 'max:30'],
            "{$empresaPrefix}numero" => ['nullable', 'string', 'max:5'],
            "{$empresaPrefix}complemento" => ['nullable', 'string', 'max:30'],
            "{$empresaPrefix}bairro" => ['nullable', 'string', 'max:30'],
            "{$empresaPrefix}cod_municipio" => ['nullable', 'string', 'max:15'],
            "{$empresaPrefix}agenciador_id" => ['nullable', 'integer'],
            "{$empresaPrefix}emite_nfce" => ['nullable', 'boolean'],
            "{$empresaPrefix}emite_nfse" => ['nullable', 'boolean'],

            'tenant' => [$tenantRequired, 'array'],
            'tenant.uuid' => ['nullable', 'uuid'],
            'tenant.name' => [$tenantRequired, 'string', 'max:255'],
            'tenant.nome_suporte' => ['nullable', 'string', 'max:255'],
            'tenant.telefone_suporte' => ['nullable', 'string', 'max:255'],
            'tenant.plano' => ['nullable', 'integer'],
            'tenant.agenciador_uuid' => ['nullable', 'uuid'],
            'tenant.url_banco' => [$tenantRequired, 'string', 'max:255'],
            'tenant.porta' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'tenant.database' => [$tenantRequired, 'string', 'max:255'],
            'tenant.username' => [$tenantRequired, 'string', 'max:255'],
            'tenant.password' => [($partial && $existingTenant) ? 'nullable' : $tenantRequired, 'string', 'max:255'],
            'tenant.status' => [$tenantRequired, Rule::in(['ativo', 'bloqueado', 'desativado'])],
            'tenant.licenca_valida_ate' => ['nullable', 'date'],

            'usuario' => [$usuarioRequired, 'array'],
            'usuario.uuid' => ['nullable', 'uuid'],
            'usuario.name' => [$usuarioRequired, 'string', 'max:255'],
            'usuario.email' => [$usuarioRequired, 'email', 'max:255', $emailRule],
            'usuario.password' => [($partial && $existingUser) ? 'nullable' : $usuarioRequired, 'string', 'min:6', 'max:255'],
            'usuario.telefone' => ['nullable', 'string', 'max:16'],
            'usuario.role' => ['nullable', Rule::in(['master', 'agenciador', 'admin', 'user'])],
            'usuario.status' => ['nullable', 'boolean'],
            'usuario.dashboard' => ['nullable', 'string', 'max:40'],
            'usuario.is_tecnico' => ['nullable', 'boolean'],
            'usuario.cpf' => ['nullable', 'string', 'max:15'],
            'usuario.rg' => ['nullable', 'string', 'max:15'],
            'usuario.cep' => ['nullable', 'string', 'max:15'],
            'usuario.endereco' => ['nullable', 'string', 'max:50'],
            'usuario.bairro' => ['nullable', 'string', 'max:30'],
            'usuario.complemento' => ['nullable', 'string', 'max:30'],
            'usuario.cidade' => ['nullable', 'string', 'max:25'],
            'usuario.uf' => ['nullable', 'string', 'max:2'],
        ];

        // Se não for super_admin, removemos campos sensíveis de banco do payload validado
        if ($user && $user->perfil !== 'super_admin') {
            unset($rules['tenant.url_banco']);
            unset($rules['tenant.database']);
            unset($rules['tenant.username']);
            unset($rules['tenant.password']);
            unset($rules['tenant.porta']);
        }

        $validated = $request->validate($rules);

        if ($empresaPrefix === '') {
            return [
                'empresa' => $validated,
            ];
        }

        $empresaData = $validated['empresa'] ?? [];
        if ($partial) {
            return array_filter([
                'empresa' => $empresaData,
                'tenant' => $validated['tenant'] ?? null,
                'usuario' => $validated['usuario'] ?? null,
            ], fn ($value) => $value !== null);
        }

        return [
            'empresa' => $empresaData,
            'tenant' => $validated['tenant'],
            'usuario' => $validated['usuario'],
        ];
    }

    private function saveTenant(Empresa $empresa, ?Tenant $tenant, array $payload): Tenant
    {
        // Mapeamento de status da Empresa (inteiro) para o Tenant (string)
        $statusMap = [
            1 => 'ativo',
            2 => 'desativado',
            3 => 'bloqueado'
        ];

        // O status da empresa manda no status do tenant durante a atualização/criação
        $novoStatus = $statusMap[$empresa->status] ?? ($payload['status'] ?? 'ativo');

        $attributes = [
            'name' => $payload['name'],
            'nome_suporte' => $payload['nome_suporte'] ?? null,
            'telefone_suporte' => $payload['telefone_suporte'] ?? null,
            'plano' => $payload['plano'] ?? null,
            'agenciador_uuid' => $payload['agenciador_uuid'] ?? null,
            'empresa_uuid' => $empresa->uuid,
            'url_banco' => $payload['url_banco'],
            'porta' => $payload['porta'] ?? 3306,
            'database' => $payload['database'],
            'username' => $payload['username'],
            'status' => $novoStatus,
            'licenca_valida_ate' => $payload['licenca_valida_ate'] ?? null,
        ];

        if (!empty($payload['password'])) {
            $attributes['password'] = $payload['password'];
        }

        if ($tenant) {
            $tenant->update($attributes);
            return $tenant->fresh();
        }

        $attributes['uuid'] = (string) Str::uuid();

        return Tenant::query()->create($attributes);
    }

    private function saveTenantUser(Tenant $tenant, ?TenantUser $user, array $payload): TenantUser
    {
        $attributes = [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'telefone' => $payload['telefone'] ?? null,
            'tenant_uuid' => $tenant->uuid,
            'role' => $payload['role'] ?? 'admin',
            'status' => $payload['status'] ?? true,
            'dashboard' => $payload['dashboard'] ?? null,
            'is_tecnico' => $payload['is_tecnico'] ?? false,
            'cpf' => $payload['cpf'] ?? null,
            'rg' => $payload['rg'] ?? null,
            'cep' => $payload['cep'] ?? null,
            'endereco' => $payload['endereco'] ?? null,
            'bairro' => $payload['bairro'] ?? null,
            'complemento' => $payload['complemento'] ?? null,
            'cidade' => $payload['cidade'] ?? null,
            'uf' => $payload['uf'] ?? null,
        ];

        if (!empty($payload['password'])) {
            $attributes['password'] = $payload['password'];
        }

        if ($user) {
            $user->update($attributes);
            $user = $user->fresh();
        } else {
            $attributes['uuid'] = (string) Str::uuid();
            $user = TenantUser::query()->create($attributes);
        }

        // Sincronizar com MasterUser
        $masterUser = MasterUser::query()->where('uuid', $user->uuid)->first();
        
        $masterData = [
            'nome' => $user->name,
            'email' => $user->email,
            'telefone' => $user->telefone,
            'status' => $user->status,
            'perfil' => ($user->role === 'admin' || $user->role === 'master') ? 'admin' : 'user',
            'empresa_uuid' => $tenant->empresa_uuid,
        ];

        if (!empty($payload['password'])) {
            $masterData['password'] = $payload['password'];
        } elseif (!$masterUser) {
            // Se o usuário não existe no master, precisamos de uma senha. 
            // Como não temos a senha pura aqui se não foi enviada no payload, 
            // e o model MasterUser usa hash automático, vamos atribuir a senha do TenantUser.
            // Nota: Se o MasterUser tiver o cast 'hashed', ele vai re-criptografar. 
            // Para evitar isso se já estiver criptografado, poderíamos usar DB::table ou verificar o cast.
            $masterData['password'] = $user->password; 
        }

        if ($masterUser) {
            $masterUser->update($masterData);
        } else {
            MasterUser::query()->create(array_merge(['uuid' => $user->uuid], $masterData));
        }

        return $user;
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

    private function successItem($empresa, ?string $message = null, int $status = 200)
    {
        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $empresa,
            'message' => $message,
            'errors' => null,
            'meta' => null,
        ], $status);
    }
}
