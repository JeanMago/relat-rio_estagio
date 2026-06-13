<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenants\TenantDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class DatabaseController extends Controller
{
    public function __construct(
        protected TenantDatabaseService $databaseService
    ) {}

    /**
     * Lista o status de todos os bancos (Master + Tenants)
     */
    public function index()
    {
        // 1. Status do Master
        $masterStatus = $this->getDbStats(config('database.default'));
        $masterStatus['name'] = 'Master (Central)';
        $masterStatus['type'] = 'master';
        $masterStatus['connection'] = config('database.default');

        // 2. Status dos Tenants
        $tenants = Tenant::with('empresa')->get()->map(function($tenant) {
            return [
                'uuid' => $tenant->uuid,
                'name' => $tenant->empresa?->nome ?? $tenant->name,
                'type' => 'tenant',
                'database' => $tenant->database,
                'host' => $tenant->url_banco,
                'status' => $tenant->status,
                'last_sync' => $tenant->updated_at
            ];
        });

        return response()->json([
            'status' => true,
            'data' => [
                'master' => $masterStatus,
                'tenants' => $tenants
            ]
        ]);
    }

    /**
     * Obtém estatísticas detalhadas de um banco específico
     */
    public function stats(Request $request, $id = null)
    {
        if (!$id || $id === 'master') {
            return response()->json([
                'success' => true,
                'data' => $this->getDbStats(config('database.default'), true)
            ]);
        }

        $tenant = Tenant::findOrFail($id);
        try {
            // Configura conexão temporária
            $this->setupTemporaryConnection($tenant);
            $stats = $this->getDbStats('tenant_temp', true);
            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Executa migrações no banco (Master ou Tenant)
     */
    public function migrate(Request $request)
    {
        $target = $request->input('target'); // 'master' ou UUID do tenant

        try {
            if ($target === 'master') {
                Artisan::call('migrate', ['--force' => true]);
                $output = Artisan::output();
            } else {
                $tenant = Tenant::findOrFail($target);
                // Para tenants, como usamos dump, a "migração" é a re-inicialização ou o dump de patches
                $result = $this->databaseService->createAndInitialize($tenant, $tenant->password);
                if (!$result['success']) throw new \Exception($result['message']);
                $output = $result['message'];
            }

            return response()->json(['success' => true, 'output' => $output]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Executa uma Query SQL manual no alvo selecionado
     */
    public function execute(Request $request)
    {
        // ... (mantido para compatibilidade se necessário)
        $request->validate([
            'target' => ['required', 'string'],
            'query' => ['required', 'string'],
        ]);

        $target = $request->input('target');
        $query = $request->input('query');

        try {
            $connection = $this->getConnection($target);
            $isSelect = stripos(trim($query), 'SELECT') === 0 || stripos(trim($query), 'SHOW') === 0 || stripos(trim($query), 'DESCRIBE') === 0;

            if ($isSelect) {
                $result = DB::connection($connection)->select($query);
                return response()->json(['success' => true, 'type' => 'select', 'data' => $result]);
            } else {
                $affected = DB::connection($connection)->affectingStatement($query);
                return response()->json(['success' => true, 'type' => 'statement', 'affected' => $affected]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna a lista simples de tabelas do alvo
     */
    public function tables($target)
    {
        try {
            $connection = $this->getConnection($target);
            $tables = DB::connection($connection)->select('SHOW TABLES');
            $list = array_map(fn($t) => array_values((array)$t)[0], $tables);
            return response()->json(['success' => true, 'data' => $list]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna os dados de uma tabela específica
     */
    public function tableData(Request $request, $target, $table)
    {
        try {
            $connection = $this->getConnection($target);
            $data = DB::connection($connection)->table($table)->orderByDesc(DB::raw('1'))->limit(100)->get();
            $columns = DB::connection($connection)->select("SHOW COLUMNS FROM `{$table}`");
            
            return response()->json([
                'success' => true, 
                'columns' => $columns,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza um campo de uma linha específica
     */
    public function updateRecord(Request $request)
    {
        $payload = $request->validate([
            'target' => ['required', 'string'],
            'table' => ['required', 'string'],
            'pk_column' => ['required', 'string'],
            'pk_value' => ['required', 'string'],
            'column' => ['required', 'string'],
            'value' => ['nullable'],
        ]);

        try {
            $connection = $this->getConnection($payload['target']);
            DB::connection($connection)->table($payload['table'])
                ->where($payload['pk_column'], $payload['pk_value'])
                ->update([$payload['column'] => $payload['value']]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function getConnection($target)
    {
        if ($target === 'master') return config('database.default');
        
        $tenant = Tenant::findOrFail($target);
        $this->setupTemporaryConnection($tenant);
        return 'tenant_temp';
    }

    private function getDbStats($connection, $detailed = false)
    {
        try {
            $tables = DB::connection($connection)->select('SHOW TABLE STATUS');
            $count = count($tables);
            $size = array_sum(array_column($tables, 'Data_length')) + array_sum(array_column($tables, 'Index_length'));
            
            $stats = [
                'table_count' => $count,
                'size_mb' => round($size / 1024 / 1024, 2),
                'version' => DB::connection($connection)->select('SELECT VERSION() as version')[0]->version,
            ];

            if ($detailed) {
                $stats['tables'] = array_map(function($table) {
                    return [
                        'name' => $table->Name,
                        'rows' => $table->Rows,
                        'size' => round(($table->Data_length + $table->Index_length) / 1024, 2) . ' KB'
                    ];
                }, $tables);
            }

            return $stats;
        } catch (\Exception $e) {
            return ['error' => 'Falha ao conectar: ' . $e->getMessage()];
        }
    }

    private function setupTemporaryConnection(Tenant $tenant)
    {
        config(['database.connections.tenant_temp' => [
            'driver' => 'mysql',
            'host' => $tenant->url_banco,
            'port' => $tenant->porta ?? 3306,
            'database' => $tenant->database,
            'username' => $tenant->username,
            'password' => $tenant->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]]);
        DB::purge('tenant_temp');
    }
}
