<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = MasterUser::query()
            ->where('email', $data['email'])
            ->first();

        if (!$user || !Hash::check((string) $data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais invalidas.'],
            ]);
        }

        if (!$user->status) {
            return response()->json([
                'status' => false,
                'message' => 'Usuario desativado.',
            ], 403);
        }

        $token = $user->createToken(
            $data['device_name'] ?? 'master-web',
            ['*'],
            now()->addHours(12)
        );

        $user->forceFill([
            'ultimo_login_at' => now(),
        ])->save();

        return response()->json([
            'status' => true,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->serializeUser($user->fresh()),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'status' => true,
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    private function serializeUser(?MasterUser $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->nome,
            'nome' => $user->nome,
            'email' => $user->email,
            'telefone' => $user->telefone,
            'perfil' => $user->perfil,
            'role' => $user->perfil,
            'status' => $user->status,
            'avatar_url' => $user->avatar_url,
            'ultimo_login_at' => $user->ultimo_login_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
