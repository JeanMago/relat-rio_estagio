<?php

namespace App\Services\Tenants;

use App\Models\Tenant;
use App\Models\TenantUser;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantDatabaseService
{
    /**
     * Tenta criar o banco de dados e as tabelas para o tenant.
     */
    public function createAndInitialize(Tenant $tenant, string $plainPassword, ?array $adminUserPayload = null): array
    {
        try {
            // 1. Criar o banco de dados (usando a conexão padrão/master)
            $this->createDatabase($tenant);

            // 2. Configurar a conexão temporária para o novo banco
            $this->setupTenantConnection($tenant, $plainPassword);

            // 3. Importar o dump base usando a conexão do tenant
            $this->importBaseDump();

            // 4. Se houver payload de usuário admin, atualizar no banco do tenant
            if ($adminUserPayload) {
                $this->updateAdminUserInTenant($adminUserPayload);
            }

            return [
                'success' => true,
                'message' => "Banco de dados '{$tenant->database}' criado e inicializado com sucesso."
            ];
        } catch (Exception $e) {
            Log::error("Erro ao inicializar banco do tenant: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Testa a conexão com o banco do tenant.
     */
    public function testConnection(array $config): array
    {
        $connectionName = 'tenant_test';

        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => $config['url_banco'],
            'port' => $config['porta'] ?? 3306,
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        try {
            DB::purge($connectionName);
            DB::connection($connectionName)->getPdo();
            return [
                'success' => true,
                'message' => 'Conexão realizada com sucesso!'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Falha na conexão: ' . $e->getMessage()
            ];
        }
    }

    public function exportDatabase(Tenant $tenant, ?string $password = null): string
    {
        $dbPassword = $password ?: $tenant->password;
        $fileName = "dump_{$tenant->database}_" . date('Ymd_His') . ".sql";
        $tempPath = storage_path("app/public/temp_dumps/{$fileName}");

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        // Comando mysqldump
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
            escapeshellarg($tenant->url_banco),
            escapeshellarg($tenant->porta ?: '3306'),
            escapeshellarg($tenant->username),
            escapeshellarg($dbPassword),
            escapeshellarg($tenant->database),
            escapeshellarg($tempPath)
        );

        $output = [];
        $resultCode = 0;
        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            throw new Exception("Falha ao gerar dump do banco. Código: {$resultCode}");
        }

        return $tempPath;
    }

    private function createDatabase(Tenant $tenant): void
    {
        // Garantimos que usamos a conexão padrão (master)
        $query = "CREATE DATABASE IF NOT EXISTS `{$tenant->database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        DB::connection()->statement($query);
    }

    private function setupTenantConnection(Tenant $tenant, string $password): void
    {
        Config::set('database.connections.tenant_setup', [
            'driver' => 'mysql',
            'host' => $tenant->url_banco,
            'port' => $tenant->porta ?? 3306,
            'database' => $tenant->database,
            'username' => $tenant->username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        DB::purge('tenant_setup');
    }

    private function importBaseDump(): void
    {
        $dumpPath = database_path('DUMPS/oficina.dump');

        if (!file_exists($dumpPath)) {
            throw new Exception("Dump base não encontrado em: {$dumpPath}");
        }

        $sql = file_get_contents($dumpPath);

        // Usar explicitamente a conexão tenant_setup
        DB::connection('tenant_setup')->unprepared($sql);
    }

    private function updateAdminUserInTenant(array $payload): void
    {
        // Usar explicitamente a conexão tenant_setup
        $conn = DB::connection('tenant_setup');
        
        $user = $conn->table('users')->where('email', $payload['email'])->first();
        if (!$user) {
            $user = $conn->table('users')->first();
        }

        $data = [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'status' => 1, // Usando inteiro 1 em vez de 'ativo'
            'role' => 'admin',
            'updated_at' => now(),
        ];

        if (!empty($payload['password'])) {
            $data['password'] = bcrypt($payload['password']);
        }

        if ($user) {
            // Tenta usar uuid, depois id, depois email como chave de busca
            $key = 'uuid';
            $val = $user->uuid ?? null;

            if (!$val && isset($user->id)) {
                $key = 'id';
                $val = $user->id;
            }

            if (!$val) {
                $key = 'email';
                $val = $user->email;
            }

            $conn->table('users')
                ->where($key, $val)
                ->update($data);
        } else {
            $data['uuid'] = (string) \Illuminate\Support\Str::uuid();
            $data['created_at'] = now();
            $conn->table('users')->insert($data);
        }
    }
}
