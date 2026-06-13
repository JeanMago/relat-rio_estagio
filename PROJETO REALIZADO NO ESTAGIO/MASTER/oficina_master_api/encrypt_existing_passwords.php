<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

echo "Iniciando criptografia das senhas existentes...\n";

// Precisamos desativar o cast temporariamente ou usar DB direto para ler o texto puro
$tenants = DB::table('tenants')->get();

$count = 0;
foreach ($tenants as $tenant) {
    // Verifica se a senha já parece estar criptografada (geralmente começa com o prefixo do payload do Crypt)
    // No Laravel, o Crypt gera um JSON base64. Uma forma simples de checar é tentar descriptografar.
    
    try {
        Crypt::decryptString($tenant->password);
        // Se não deu erro, já está criptografada
        continue;
    } catch (\Exception $e) {
        // Se deu erro, é texto puro. Vamos criptografar.
        DB::table('tenants')
            ->where('uuid', $tenant->uuid)
            ->update([
                'password' => Crypt::encryptString($tenant->password)
            ]);
        $count++;
    }
}

echo "Finalizado. {$count} senhas foram criptografadas.\n";
