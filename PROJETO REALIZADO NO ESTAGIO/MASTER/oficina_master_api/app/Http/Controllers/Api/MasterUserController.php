<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MasterUserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'boolean'],
            'perfil' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = MasterUser::query()->orderBy('nome');

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('nome', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telefone', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('status', $filters)) {
            $query->where('status', (bool) $filters['status']);
        }

        if (!empty($filters['perfil'])) {
            $query->where('perfil', $filters['perfil']);
        }

        $rows = $query->paginate((int) ($filters['limit'] ?? 30));

        return $this->successList($rows);
    }

    public function store(Request $request)
    {
        $payload = $this->validatePayload($request);
        $payload['uuid'] = $payload['uuid'] ?? (string) Str::uuid();

        $user = MasterUser::query()->create($payload);

        return $this->successItem($user->fresh(), 'Usuario master criado com sucesso.', 201);
    }

    public function show(string $id)
    {
        return $this->successItem($this->findUser($id));
    }

    public function update(Request $request, string $id)
    {
        $user = $this->findUser($id);
        $payload = $this->validatePayload($request, true, $user);
        $user->update($payload);

        return $this->successItem($user->fresh(), 'Usuario master atualizado com sucesso.');
    }

    public function destroy(Request $request, string $id)
    {
        $user = $this->findUser($id);

        if ((string) $request->user()?->uuid === (string) $user->uuid) {
            return response()->json([
                'status' => false,
                'code' => 'SELF_DELETE_FORBIDDEN',
                'data' => null,
                'message' => 'Nao e permitido remover o proprio usuario logado.',
                'errors' => null,
                'meta' => null,
            ], 422);
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => null,
            'message' => 'Usuario master removido com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }

    private function findUser(string $id): MasterUser
    {
        return MasterUser::query()
            ->where('uuid', $id)
            ->orWhere('id', $id)
            ->firstOrFail();
    }

    private function validatePayload(Request $request, bool $partial = false, ?MasterUser $user = null): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $emailRule = Rule::unique('master_users', 'email');

        if ($user) {
            $emailRule = $emailRule->ignore($user->id);
        }

        $rules = [
            'uuid' => ['nullable', 'uuid'],
            'nome' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255', $emailRule],
            'telefone' => ['nullable', 'string', 'max:30'],
            'password' => [$partial ? 'nullable' : 'required', 'string', 'min:6', 'max:255'],
            'perfil' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'boolean'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'observacoes' => ['nullable', 'string'],
            'email_verified_at' => ['nullable', 'date'],
        ];

        $payload = $request->validate($rules);

        if ($partial && array_key_exists('password', $payload) && !$payload['password']) {
            unset($payload['password']);
        }

        return $payload;
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

    private function successItem(?MasterUser $user, ?string $message = null, int $status = 200)
    {
        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $user,
            'message' => $message,
            'errors' => null,
            'meta' => null,
        ], $status);
    }
}
