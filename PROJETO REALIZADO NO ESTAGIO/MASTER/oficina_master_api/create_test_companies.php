<?php

use App\Models\Empresa;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\Plano;
use App\Services\Tenants\TenantDatabaseService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

// Garantir que existe um plano
$plano = Plano::first();
if (!$plano) {
    echo "Criando Plano Master inicial...\n";
    $plano = Plano::create([
        'nome' => 'Plano Master',
        'status' => 'ativo',
        'limite_usuarios' => 10,
        'limite_disco' => 5120,
        'valor' => 199.90,
        'modulos' => json_encode(['financeiro', 'estoque', 'oficina']),
    ]);
}

// Mock de dados para a Oficina Alpha
$data1 = [
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
];

// Mock de dados para a Oficina Beta
$data2 = [
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
];

function criarEmpresaTeste($data) {
    echo "Iniciando criacao da empresa: " . $data['empresa']['nome'] . "\n";
    
    // Limpar dados anteriores se existirem para permitir re-teste
    $oldEmpresa = Empresa::where('nome', $data['empresa']['nome'])->first();
    if ($oldEmpresa) {
        echo " - Removendo registro anterior...\n";
        $oldEmpresa->delete(); 
    }

    try {
        // Usamos o proprio controller ou a logica dele para testar a integração completa
        $request = new \Illuminate\Http\Request();
        $request->merge(array_merge($data, ['create_database' => true]));
        
        $controller = app(\App\Http\Controllers\Api\EmpresaController::class);
        $response = $controller->store($request);
        
        $resData = $response->getData();
        if ($resData->status) {
             echo "Sucesso: Empresa {$data['empresa']['nome']} criada com licenca e banco!\n\n";
        } else {
             echo "Erro ao criar {$data['empresa']['nome']}: " . json_encode($resData->errors) . "\n\n";
        }
    } catch (\Exception $e) {
        echo "Erro ao criar {$data['empresa']['nome']}: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n\n";
    }
}

criarEmpresaTeste($data1);
criarEmpresaTeste($data2);
