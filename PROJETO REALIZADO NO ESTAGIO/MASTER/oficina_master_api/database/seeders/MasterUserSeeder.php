<?php

namespace Database\Seeders;

use App\Models\MasterUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MasterUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name = (string) env('MASTER_USER_NAME', 'Master Admin');
        $email = (string) env('MASTER_USER_EMAIL', 'master@fkoficina.local');
        $password = (string) env('MASTER_USER_PASSWORD', '123456');
        $phone = (string) env('MASTER_USER_PHONE', '');
        $profile = (string) env('MASTER_USER_PROFILE', 'superadmin');

        MasterUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'uuid' => (string) Str::uuid(),
                'nome' => $name,
                'telefone' => $phone ?: null,
                'password' => Hash::make($password),
                'perfil' => $profile,
                'status' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
