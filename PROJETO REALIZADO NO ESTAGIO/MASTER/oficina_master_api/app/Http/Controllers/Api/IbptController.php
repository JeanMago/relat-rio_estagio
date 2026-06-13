<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IbptVersao;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IbptController extends Controller
{
    private const IBPT_ITEM_COLUMNS = 13;
    private const MYSQL_SAFE_PLACEHOLDERS = 60000;
    private const DESCRICAO_MAX_LENGTH = 255;
    private const CHAVE_MAX_LENGTH = 80;
    private const FONTE_MAX_LENGTH = 60;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'ativa' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = IbptVersao::query()->withCount('itens');

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('versao', 'like', "%{$search}%")
                    ->orWhere('fonte', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('ativa', $filters)) {
            $query->where('ativa', (bool) $filters['ativa']);
        }

        $rows = $query
            ->orderByDesc('ativa')
            ->orderByDesc('publicada_em')
            ->orderByDesc('id')
            ->paginate((int) ($filters['limit'] ?? 30));

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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'arquivo' => ['required', 'file', 'max:102400'],
            'ativa' => ['nullable', 'boolean'],
            'clear_existing' => ['nullable', 'boolean'],
            'chunk' => ['nullable', 'integer', 'min:100', 'max:10000'],
        ]);

        $ativa = (bool) ($validated['ativa'] ?? true);
        $clearExisting = (bool) ($validated['clear_existing'] ?? true);
        $chunk = $this->normalizarChunkImportacao((int) ($validated['chunk'] ?? 1000));

        $arquivo = $request->file('arquivo');
        $metadadosArquivo = $this->extrairMetadadosArquivoIbpt($arquivo->getRealPath());
        $versao = $metadadosArquivo['versao'];
        $fonte = $metadadosArquivo['fonte'];
        $hashArquivo = sha1_file($arquivo->getRealPath());
        $ext = strtolower($arquivo->getClientOriginalExtension() ?: 'csv');
        $nomeArquivo = 'ibpt_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $versao) . '_' . now()->format('Ymd_His') . '.' . $ext;
        $arquivoPath = $arquivo->storeAs('ibpt/' . date('Y/m'), $nomeArquivo, 'local');

        if (IbptVersao::query()->where('versao', $versao)->exists()) {
            throw ValidationException::withMessages([
                'arquivo' => ["A versao IBPT {$versao} ja esta cadastrada."],
            ]);
        }

        $importados = 0;
        $versaoModel = null;

        DB::connection('master')->transaction(function () use (
            $validated,
            $metadadosArquivo,
            $versao,
            $fonte,
            $ativa,
            $clearExisting,
            $hashArquivo,
            $arquivoPath,
            $chunk,
            &$importados,
            &$versaoModel,
            $arquivo
        ) {
            if ($ativa) {
                IbptVersao::query()->where('ativa', true)->update(['ativa' => false]);
            }

            $versaoModel = IbptVersao::query()->create([
                'versao' => $versao,
                'vigencia_inicio' => $this->parseDateOrNull($metadadosArquivo['vigencia_inicio'] ?? null),
                'vigencia_fim' => $this->parseDateOrNull($metadadosArquivo['vigencia_fim'] ?? null),
                'fonte' => $fonte,
                'arquivo_path' => $arquivoPath,
                'hash_arquivo' => $hashArquivo,
                'ativa' => $ativa,
                'publicada_em' => now(),
                'meta' => [
                    'arquivo_original' => $arquivoPath,
                    'nome_cliente_arquivo' => $arquivo?->getClientOriginalName(),
                    'metadados_arquivo' => $metadadosArquivo,
                ],
            ]);

            if ($clearExisting) {
                DB::connection('master')
                    ->table('ibpt_itens')
                    ->where('ibpt_versao_id', $versaoModel->id)
                    ->delete();
            }

            $importados = $this->importarArquivoIbpt(
                Storage::disk('local')->path($arquivoPath),
                $versaoModel->id,
                $fonte,
                $chunk
            );

            $metaAtual = $versaoModel->meta ?? [];
            $metaAtual['total_itens'] = $importados;
            $metaAtual['importado_em'] = now()->toIso8601String();
            $versaoModel->meta = $metaAtual;
            $versaoModel->save();
        });

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $versaoModel?->fresh()->loadCount('itens'),
            'message' => "Arquivo IBPT importado com sucesso. {$importados} item(ns) processado(s).",
            'errors' => null,
            'meta' => [
                'itens_importados' => $importados,
            ],
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $versao = IbptVersao::query()->findOrFail($id);

        $payload = $request->validate([
            'versao' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('master.ibpt_versoes', 'versao')->ignore($versao->id)],
            'vigencia_inicio' => ['nullable', 'date'],
            'vigencia_fim' => ['nullable', 'date', 'after_or_equal:vigencia_inicio'],
            'fonte' => ['nullable', 'string', 'max:60'],
            'ativa' => ['nullable', 'boolean'],
        ]);

        DB::connection('master')->transaction(function () use ($payload, $versao) {
            $ativa = array_key_exists('ativa', $payload) ? (bool) $payload['ativa'] : $versao->ativa;

            if ($ativa) {
                IbptVersao::query()
                    ->where('id', '!=', $versao->id)
                    ->where('ativa', true)
                    ->update(['ativa' => false]);
            }

            if (array_key_exists('versao', $payload)) {
                $versao->versao = trim((string) $payload['versao']);
            }

            if (array_key_exists('vigencia_inicio', $payload)) {
                $versao->vigencia_inicio = $this->parseDateOrNull($payload['vigencia_inicio']);
            }

            if (array_key_exists('vigencia_fim', $payload)) {
                $versao->vigencia_fim = $this->parseDateOrNull($payload['vigencia_fim']);
            }

            if (array_key_exists('fonte', $payload)) {
                $versao->fonte = trim((string) ($payload['fonte'] ?? '')) ?: 'IBPT';
            }

            if (array_key_exists('ativa', $payload)) {
                $versao->ativa = $ativa;
            }

            $versao->save();
        });

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $versao->fresh()->loadCount('itens'),
            'message' => 'Versao IBPT atualizada com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $versao = IbptVersao::query()->findOrFail($id);
        $arquivoPath = $versao->arquivo_path;
        $versao->delete();

        if ($arquivoPath) {
            Storage::disk('local')->delete($arquivoPath);
        }

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => null,
            'message' => 'Versao IBPT removida com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }

    private function importarArquivoIbpt(string $arquivoFullPath, int $versaoId, string $fontePadrao, int $chunk = 1000): int
    {
        $chunk = $this->normalizarChunkImportacao($chunk);
        $handle = fopen($arquivoFullPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Nao foi possivel abrir o arquivo IBPT.');
        }

        $total = 0;
        $batch = [];
        $cabecalho = null;
        $delimiter = null;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = $this->normalizeLine($line);
                if ($line === '') {
                    continue;
                }

                if ($cabecalho === null) {
                    $delimiter = $this->detectarDelimitador($line);
                    $cabecalho = $this->normalizarCabecalho(str_getcsv($line, $delimiter));
                    continue;
                }

                $colunas = str_getcsv($line, $delimiter);
                if (empty(array_filter($colunas, static fn ($value) => trim((string) $value) !== ''))) {
                    continue;
                }

                $row = $this->rowComCabecalho($cabecalho, $colunas);
                $ncm = Str::of((string) $this->getCampo($row, ['ncm', 'codigo', 'codigo_ncm']))
                    ->replaceMatches('/\D+/', '')
                    ->substr(0, 8)
                    ->value();

                if ($ncm === '') {
                    continue;
                }

                $uf = strtoupper((string) $this->getCampo($row, ['uf', 'estado', 'uf_estado', 'estado_uf', 'sigla_estado', 'sigla']));
                $uf = $uf !== '' ? substr($uf, 0, 2) : null;

                $exTipi = strtoupper((string) $this->getCampo($row, ['ex_tipi', 'extipi', 'ex']));
                $exTipi = $exTipi !== '' ? substr($exTipi, 0, 3) : null;

                $batch[] = [
                    'ibpt_versao_id' => $versaoId,
                    'uf' => $uf,
                    'ncm' => $ncm,
                    'ex_tipi' => $exTipi,
                    'descricao' => $this->limitString(
                        $this->nullIfEmpty($this->getCampo($row, ['descricao', 'descricao_ncm', 'descricao_produto'])),
                        self::DESCRICAO_MAX_LENGTH
                    ),
                    'aliquota_federal_nacional' => $this->toDecimal($this->getCampo($row, [
                        'aliquota_federal_nacional',
                        'nacional_federal',
                        'federal_nacional',
                    ])),
                    'aliquota_federal_importado' => $this->toDecimal($this->getCampo($row, [
                        'aliquota_federal_importado',
                        'importados_federal',
                        'federal_importado',
                    ])),
                    'aliquota_estadual' => $this->toDecimal($this->getCampo($row, ['aliquota_estadual', 'estadual'])),
                    'aliquota_municipal' => $this->toDecimal($this->getCampo($row, ['aliquota_municipal', 'municipal'])),
                    'chave' => $this->limitString(
                        $this->nullIfEmpty($this->getCampo($row, ['chave'])),
                        self::CHAVE_MAX_LENGTH
                    ),
                    'fonte' => $this->limitString(
                        $this->nullIfEmpty($this->getCampo($row, ['fonte'])) ?: $fontePadrao,
                        self::FONTE_MAX_LENGTH
                    ),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= $chunk) {
                    $this->upsertLote($batch);
                    $total += count($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $this->upsertLote($batch);
                $total += count($batch);
            }
        } finally {
            fclose($handle);
        }

        return $total;
    }

    private function extrairMetadadosArquivoIbpt(string $arquivoFullPath): array
    {
        $handle = fopen($arquivoFullPath, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'arquivo' => ['Nao foi possivel ler o arquivo IBPT enviado.'],
            ]);
        }

        $cabecalho = null;
        $delimiter = null;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = $this->normalizeLine($line);
                if ($line === '') {
                    continue;
                }

                if ($cabecalho === null) {
                    $delimiter = $this->detectarDelimitador($line);
                    $cabecalho = $this->normalizarCabecalho(str_getcsv($line, $delimiter));
                    continue;
                }

                $colunas = str_getcsv($line, $delimiter);
                if (empty(array_filter($colunas, static fn ($value) => trim((string) $value) !== ''))) {
                    continue;
                }

                $row = $this->rowComCabecalho($cabecalho, $colunas);
                $versao = $this->limitString($this->getCampo($row, ['versao']), 30);
                $fonte = $this->limitString(
                    $this->getCampo($row, ['fonte']) ?: 'IBPT',
                    self::FONTE_MAX_LENGTH
                );

                if (!$versao) {
                    throw ValidationException::withMessages([
                        'arquivo' => ['Nao foi possivel identificar a versao no arquivo IBPT.'],
                    ]);
                }

                return [
                    'versao' => $versao,
                    'vigencia_inicio' => $this->normalizarDataArquivo(
                        $this->getCampo($row, ['vigencia_inicio', 'vigenciainicio', 'inicio_vigencia'])
                    ),
                    'vigencia_fim' => $this->normalizarDataArquivo(
                        $this->getCampo($row, ['vigencia_fim', 'vigenciafim', 'fim_vigencia'])
                    ),
                    'fonte' => $fonte ?: 'IBPT',
                ];
            }
        } finally {
            fclose($handle);
        }

        throw ValidationException::withMessages([
            'arquivo' => ['O arquivo IBPT nao possui linhas de dados validas.'],
        ]);
    }

    private function upsertLote(array $batch): void
    {
        foreach (array_chunk($batch, $this->maxRowsPerUpsert()) as $chunk) {
            DB::connection('master')->table('ibpt_itens')->upsert(
                $chunk,
                ['ibpt_versao_id', 'ncm', 'ex_tipi', 'uf'],
                [
                    'descricao',
                    'aliquota_federal_nacional',
                    'aliquota_federal_importado',
                    'aliquota_estadual',
                    'aliquota_municipal',
                    'chave',
                    'fonte',
                    'updated_at',
                ]
            );
        }
    }

    private function normalizarChunkImportacao(int $chunk): int
    {
        return max(100, min($chunk, $this->maxRowsPerUpsert()));
    }

    private function maxRowsPerUpsert(): int
    {
        $maxRows = (int) floor(self::MYSQL_SAFE_PLACEHOLDERS / self::IBPT_ITEM_COLUMNS);

        return max(100, min($maxRows, 250));
    }

    private function detectarDelimitador(string $line): string
    {
        $candidates = [';', ',', "\t", '|'];
        $best = ';';
        $bestCount = 0;

        foreach ($candidates as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function normalizarCabecalho(array $headers): array
    {
        return array_map(function ($header) {
            $key = strtolower(trim((string) $header));
            $key = Str::ascii($key);
            $key = preg_replace('/[^a-z0-9]+/', '_', $key);
            return trim((string) $key, '_');
        }, $headers);
    }

    private function rowComCabecalho(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $idx => $header) {
            if ($header === '') {
                continue;
            }

            $row[$header] = isset($values[$idx]) ? trim((string) $values[$idx]) : null;
        }

        return $row;
    }

    private function getCampo(array $row, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $key = strtolower(trim($alias));
            $key = Str::ascii($key);
            $key = preg_replace('/[^a-z0-9]+/', '_', $key);
            $key = trim((string) $key, '_');

            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function toDecimal(?string $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $value = str_replace('%', '', trim($value));
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 4);
    }

    private function normalizeLine(string $line): string
    {
        $line = trim($line);
        if ($line === '') {
            return '';
        }

        if (!mb_detect_encoding($line, 'UTF-8', true)) {
            $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1,Windows-1252,UTF-8');
        }

        return trim($line);
    }

    private function parseDateOrNull(mixed $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function limitString(?string $value, int $maxLength): ?string
    {
        $value = $this->nullIfEmpty($value);
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function normalizarDataArquivo(?string $value): ?string
    {
        $value = $this->nullIfEmpty($value);
        if ($value === null) {
            return null;
        }

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1) {
                return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
