<?php

use App\Models\Empresa;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\Plano;
use Illuminate\Support\Facades\DB;

// 1. Garantir que o Plano Master existe
$plano = Plano::firstOrCreate(
    ['id' => 1],
    [
        'nome' => 'Plano Master',
        'status' => 'ativo',
        'limite_usuarios' => 10,
        'limite_disco' => 5120,
        'valor' => 199.90,
        'modulos' => json_encode(['financeiro', 'estoque', 'oficina']),
    ]
);

// 2. Limpeza PROFUNDA (Force Delete) para evitar erros de duplicidade com SoftDeletes
$emailsParaLimpar = ['admin@alpha.test', 'admin@beta.test', 'contato@oficinaalpha.test', 'contato@oficinabeta.test'];

echo "Limpando usuarios com forceDelete...\n";
TenantUser::withTrashed()->whereIn('email', $emailsParaLimpar)->forceDelete();

echo "Limpando empresas e tenants relacionados...\n";
$empresasParaLimpar = ['Oficina Alpha', 'Oficina Beta'];
$empUuids = Empresa::withTrashed()->whereIn('nome', $empresasParaLimpar)->pluck('uuid');

if ($empUuids->isNotEmpty()) {
    Tenant::withTrashed()->whereIn('empresa_uuid', $empUuids)->forceDelete();
    Empresa::withTrashed()->whereIn('uuid', $empUuids)->forceDelete();
}

echo "Limpeza concluída.\n";

// 3. Dados das empresas
$empresas = [
    [
        'empresa' => [
            'nome' => 'Oficina Alpha',
            'apelido' => 'Alpha',
            'status' => 1,
            'email' => 'contato@oficinaalpha.test',
        ],
        'tenant' => [
            'name' => 'Oficina Alpha Tenant',
            'url_banco' => '127.0.0.1',
            'database' => 'oficina_alpha',
            'username' => 'root',
            'password' => 'Omago2004@',
            'status' => 'ativo',
        ],
        'usuario' => [
            'name' => 'Admin Alpha',
            'email' => 'admin@alpha.test',
            'password' => '123456',
        ]
    ],
    [
        'empresa' => [
            'nome' => 'Oficina Beta',
            'apelido' => 'Beta',
            'status' => 1,
            'email' => 'contato@oficinabeta.test',
        ],
        'tenant' => [
            'name' => 'Oficina Beta Tenant',
            'url_banco' => '127.0.0.1',
            'database' => 'oficina_beta',
            'username' => 'root',
            'password' => 'Omago2004@',
            'status' => 'ativo',
        ],
        'usuario' => [
            'name' => 'Admin Beta',
            'email' => 'admin@beta.test',
            'password' => '123456',
        ]
    ]
];

// 4. Executar criação via Controller para testar fluxo real
$controller = app(\App\Http\Controllers\Api\EmpresaController::class);

foreach ($empresas as $data) {
    echo "Criando {$data['empresa']['nome']}...\n";
    try {
        $request = new \Illuminate\Http\Request();
        $request->merge(array_merge($data, ['create_database' => true]));
        
        $response = $controller->store($request);
        $resData = $response->getData();

        if ($resData->status) {
            echo " - SUCESSO: Empresa, Tenant, Usuário, Licença e Banco criados.\n";
        } else {
            echo " - ERRO: " . json_encode($resData->errors) . "\n";
        }
    } catch (\Exception $e) {
        echo " - EXCEÇÃO: " . $e->getMessage() . "\n";
    }
}
