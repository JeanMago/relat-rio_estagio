<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModeloImpressao;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModeloImpressaoController extends Controller
{
    public function index(Request $request)
    {
        $query = ModeloImpressao::query();

        if ($request->filled('contexto')) {
            $query->where('contexto', $request->input('contexto'));
        }

        if ($request->has('ativo')) {
            $query->where('ativo', $request->boolean('ativo'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%")
                    ->orWhere('nome', 'like', "%{$search}%")
                    ->orWhere('contexto', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderBy('ordem')->orderBy('nome')->paginate((int) $request->input('limit', 50));

        return response()->json([
            'status' => true,
            'dados' => $rows->items(),
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ]);
    }

    public function ativos(Request $request)
    {
        $query = ModeloImpressao::query()->where('ativo', true);

        if ($request->filled('contexto')) {
            $query->where('contexto', $request->input('contexto'));
        }

        $rows = $query->orderBy('ordem')->orderBy('nome')->get();

        return response()->json([
            'status' => true,
            'dados' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatePayload($request);
        $modelo = ModeloImpressao::create($payload);

        return response()->json([
            'status' => true,
            'dados' => $modelo,
        ], 201);
    }

    public function show(string $id)
    {
        $modelo = ModeloImpressao::findOrFail($id);

        return response()->json([
            'status' => true,
            'dados' => $modelo,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $modelo = ModeloImpressao::findOrFail($id);
        $payload = $this->validatePayload($request, $modelo->id);
        $modelo->update($payload);

        return response()->json([
            'status' => true,
            'dados' => $modelo->fresh(),
        ]);
    }

    public function destroy(string $id)
    {
        $modelo = ModeloImpressao::findOrFail($id);
        $modelo->delete();

        return response()->json([
            'status' => true,
            'message' => 'Modelo removido com sucesso.',
        ]);
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = Rule::unique('modelos_impressao_catalogo', 'slug');
        if ($ignoreId) {
            $slugRule = $slugRule->ignore($ignoreId);
        }

        return $request->validate([
            'slug' => ['required', 'string', 'max:120', $slugRule],
            'nome' => ['required', 'string', 'max:160'],
            'contexto' => ['required', 'string', 'max:120'],
            'engine' => ['nullable', 'string', 'max:60'],
            'formato_documento' => ['nullable', 'string', 'max:60'],
            'impressora_tipo_default' => ['nullable', 'string', 'max:60'],
            'descricao' => ['nullable', 'string'],
            'imagem_exemplo_url' => ['nullable', 'string', 'max:2048'],
            'payload_exemplo' => ['nullable', 'array'],
            'campos_configuraveis' => ['nullable', 'array'],
            'layout_bloqueado' => ['nullable', 'boolean'],
            'sistema' => ['nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
