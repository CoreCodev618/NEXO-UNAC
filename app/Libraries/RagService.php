<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/**
 * Puente PHP -> scripts Python del RAG.
 *
 * - `retrieve()` llama `python/retrieve.py --json` y normaliza los hits.
 * - `ingest()` llama `python/ingest.py --doc-id` de forma síncrona.
 * - `citations()` convierte `doc_id` de Qdrant en títulos/URLs de MariaDB.
 *
 * MySQL sigue siendo la fuente de verdad del documento; Qdrant solo aporta
 * el fragmento y el score. Si un `doc_id` no existe en `documents` (material
 * ingestado antes de la demo), la cita usa el `source` del payload.
 */
final class RagService
{
    private PythonRunner $runner;

    private int $topK;

    private float $minScore;

    private int $ingestTimeout;

    public function __construct(?PythonRunner $runner = null)
    {
        $this->runner        = $runner ?? new PythonRunner();
        $this->topK          = max(1, min(10, (int) env('RAG_TOP_K', 5)));
        $this->minScore      = (float) env('RAG_MIN_SCORE', 0);
        $this->ingestTimeout = max(30, min(600, (int) env('RAG_TIMEOUT', 180)));
    }

    /**
     * @param array{curso_id?: int, class_date?: string, topic?: string, doc_id?: int, page_id?: int, workspace_id?: int, workspace_ids?: list<int>} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function retrieve(string $query, array $filters = [], ?int $topK = null): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new RuntimeException('La consulta del RAG no puede estar vacía.');
        }

        $workspaceId  = (int) ($filters['workspace_id'] ?? 0);
        $workspaceIds = array_values(array_filter(
            array_map('intval', (array) ($filters['workspace_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        ));
        $pageId = (int) ($filters['page_id'] ?? 0);

        // Defensa en profundidad: nunca se consulta el corpus global. El chat
        // siempre debe acotar a una página o a los espacios del usuario.
        if ($workspaceId <= 0 && $workspaceIds === [] && $pageId <= 0) {
            throw new RuntimeException('La consulta RAG requiere un espacio o una página.');
        }

        $limit = max(1, min(10, $topK ?? $this->topK));
        $args  = [
            'python/retrieve.py',
            '--json',
            '--top',
            (string) $limit,
        ];

        if (($filters['curso_id'] ?? 0) > 0) {
            $args[] = '--curso-id';
            $args[] = (string) (int) $filters['curso_id'];
        }

        if (($filters['doc_id'] ?? 0) > 0) {
            $args[] = '--doc-id';
            $args[] = (string) (int) $filters['doc_id'];
        }

        if ($pageId > 0) {
            $args[] = '--page-id';
            $args[] = (string) $pageId;
        }

        if ($workspaceId > 0) {
            $args[] = '--workspace-id';
            $args[] = (string) $workspaceId;
        } elseif ($workspaceIds !== []) {
            $args[] = '--workspace-ids';
            $args[] = implode(',', $workspaceIds);
        }

        $date = trim((string) ($filters['class_date'] ?? ''));
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $args[] = '--class-date';
            $args[] = $date;
        }

        $topic = trim((string) ($filters['topic'] ?? ''));
        if ($topic !== '') {
            $args[] = '--topic';
            $args[] = $topic;
        }

        if ($this->minScore > 0) {
            $args[] = '--min-score';
            $args[] = (string) $this->minScore;
        }

        // `--` evita que una consulta que empiece con "-" se lea como opción.
        $args[] = '--';
        $args[] = $query;

        $result = $this->runner->run($args, $this->retrieveTimeout());

        if (! $result['ok']) {
            throw new RuntimeException($this->failureMessage($result, 'No se pudo consultar el material.'));
        }

        $decoded = json_decode($result['stdout'], true);

        if (! is_array($decoded)) {
            throw new RuntimeException('El retrieval devolvió una respuesta que no es JSON.');
        }

        $hits = [];
        foreach ($decoded as $hit) {
            if (! is_array($hit)) {
                continue;
            }

            $normalized = $this->normalizeHit($hit);
            if ($normalized !== null) {
                $hits[] = $normalized;
            }
        }

        return $hits;
    }

    /**
     * Ingesta un archivo ya guardado. El `doc_id` debe ser el `documents.id`.
     *
     * @param array{curso_id?: int, class_date?: string, topic?: string, page_id?: int, workspace_id?: int} $meta
     *
     * @return array{chunks: int, output: string}
     */
    public function ingest(int $docId, string $path, array $meta = []): array
    {
        if ($docId <= 0) {
            throw new RuntimeException('El documento no tiene un id válido para indexar.');
        }

        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('El documento no tiene una ruta para indexar.');
        }

        $cursoId = max(1, (int) ($meta['curso_id'] ?? 1));
        $args    = [
            'python/ingest.py',
            $path,
            '--doc-id',
            (string) $docId,
            '--curso-id',
            (string) $cursoId,
        ];

        $pageId = (int) ($meta['page_id'] ?? 0);
        if ($pageId > 0) {
            $args[] = '--page-id';
            $args[] = (string) $pageId;
        }

        $workspaceId = (int) ($meta['workspace_id'] ?? 0);
        if ($workspaceId > 0) {
            $args[] = '--workspace-id';
            $args[] = (string) $workspaceId;
        }

        $date = trim((string) ($meta['class_date'] ?? ''));
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $args[] = '--class-date';
            $args[] = $date;
        }

        $topic = trim((string) ($meta['topic'] ?? ''));
        if ($topic !== '') {
            $args[] = '--topic';
            $args[] = $topic;
        }

        $result = $this->runner->run($args, $this->ingestTimeout);

        if (! $result['ok']) {
            throw new RuntimeException($this->failureMessage($result, 'No se pudo indexar el documento.'));
        }

        $chunks = 0;
        if (preg_match('/Total:\s+(\d+)\s+chunks/i', $result['stdout'], $match) === 1) {
            $chunks = (int) $match[1];
        }

        return [
            'chunks' => $chunks,
            'output' => $result['stdout'],
        ];
    }

    /**
     * ¿Está configurado el camino vectorial real? La demo puede funcionar sin
     * él gracias a `localRetrieve()` y al fallback del chat.
     */
    public function isConfigured(): bool
    {
        if (env('RAG_INGEST_ENABLED', 'true') === 'false') {
            return false;
        }

        if (! $this->runner->isAvailable()) {
            return false;
        }

        $qdrant = trim((string) env('QDRANT_URL', ''));
        $gemini = trim((string) env('GEMINI_API_KEY', ''));

        if ($qdrant === '' || $gemini === '') {
            return false;
        }

        return true;
    }

    /**
     * Retrieval léxico local sobre `document_chunks`.
     *
     * Es el fallback de la demo cuando Qdrant/Gemini no están configurados.
     * No sustituye al retrieval semántico, pero permite probar el flujo
     * completo (pregunta -> evidencia -> cita) sin claves ni servicios.
     *
     * @param list<int> $workspaceIds
     *
     * @return list<array<string, mixed>>
     */
    public function localRetrieve(string $query, array $workspaceIds, ?int $topK = null): array
    {
        $query = trim($query);
        $ids   = array_values(array_unique(array_filter(
            array_map('intval', $workspaceIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($query === '' || $ids === []) {
            return [];
        }

        $tokens = $this->queryTokens($query);
        if ($tokens === []) {
            return [];
        }

        try {
            $sql = 'SELECT dc.content, dc.chunk_index, d.id AS doc_id, d.title,
                           d.file_path, d.page_id, d.workspace_id, d.topic, d.class_date
                    FROM document_chunks dc
                    INNER JOIN documents d ON d.id = dc.document_id
                    WHERE d.workspace_id IN (' . implode(',', $ids) . ')
                      AND dc.content IS NOT NULL
                      AND dc.content != ""
                    LIMIT 800';
            $rows = \Config\Database::connect()->query($sql)->getResultArray();
        } catch (\Throwable $error) {
            log_message('warning', 'Fallback RAG local no disponible: {error}', [
                'error' => $error->getMessage(),
            ]);

            return [];
        }

        $scored = [];
        $queryLower = mb_strtolower($query, 'UTF-8');

        foreach ($rows as $row) {
            $text  = (string) ($row['content'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $haystack = mb_strtolower($title . "\n" . $text, 'UTF-8');
            $score = 0.0;

            foreach ($tokens as $token => $weight) {
                $occurrences = mb_substr_count($haystack, $token, 'UTF-8');
                if ($occurrences > 0) {
                    $score += ($weight * 2) + min(3, $occurrences);
                }
            }

            if ($queryLower !== '' && mb_strpos($haystack, $queryLower, 0, 'UTF-8') !== false) {
                $score += 5.0;
            }

            if ($score <= 0) {
                continue;
            }

            $source = (string) ($row['file_path'] ?? '');
            $scored[] = [
                'score'        => round($score / max(1, count($tokens)), 6),
                'doc_id'       => (int) ($row['doc_id'] ?? 0),
                'workspace_id' => (int) ($row['workspace_id'] ?? 0),
                'page_id'      => (int) ($row['page_id'] ?? 0),
                'title'        => $title !== '' ? $title : 'Material del curso',
                'source'       => $source,
                'location'     => 'Fragmento ' . ((int) ($row['chunk_index'] ?? 0) + 1),
                'topic'        => (string) ($row['topic'] ?? ''),
                'chunk_key'    => (string) ($row['doc_id'] ?? '') . '-' . (string) ($row['chunk_index'] ?? ''),
                'text'         => $text,
                'snippet'      => mb_substr(preg_replace('/\s+/', ' ', $text) ?? $text, 0, 240),
                'local'        => true,
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, min(10, $topK ?? $this->topK)));
    }

    /**
     * Convierte hits de Qdrant en fuentes para el chat.
     *
     * @param list<array<string, mixed>> $hits
     * @param list<int>                  $allowedWorkspaceIds
     *
     * @return list<array<string, mixed>>
     */
    public function citations(array $hits, array $allowedWorkspaceIds = []): array
    {
        if ($hits === []) {
            return [];
        }

        $hits = $this->uniqueHits($hits);
        $rows = $this->documentsById($hits);
        $allowed = array_values(array_unique(array_filter(
            array_map('intval', $allowedWorkspaceIds),
            static fn (int $id): bool => $id > 0
        )));
        $out  = [];

        foreach ($hits as $hit) {
            $docId = (int) ($hit['doc_id'] ?? 0);
            $row   = $rows[$docId] ?? null;

            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = (string) ($hit['title'] ?? 'Material del curso');
            }

            $pageId      = (int) ($row['page_id'] ?? $hit['page_id'] ?? 0);
            $workspaceId = (int) ($row['workspace_id'] ?? $hit['workspace_id'] ?? 0);

            // Defensa final: una cita de un espacio no permitido se descarta.
            if ($allowed !== [] && ($workspaceId <= 0 || ! in_array($workspaceId, $allowed, true))) {
                continue;
            }

            $source = (string) ($row['file_path'] ?? $hit['source'] ?? '');

            $out[] = [
                'doc_id'       => $docId,
                'workspace_id' => $workspaceId > 0 ? $workspaceId : null,
                'title'        => $title,
                'page_id'      => $pageId > 0 ? $pageId : null,
                'url'          => $pageId > 0 ? $this->pageUrl($pageId) : null,
                'source'       => $source !== '' ? basename($source) : null,
                'location'     => $hit['location'] ?? null,
                'topic'        => $hit['topic'] ?? null,
                'score'        => $hit['score'] ?? null,
                'snippet'      => $hit['snippet'] ?? null,
                'chunk_key'    => $hit['chunk_key'] ?? null,
            ];
        }

        return array_slice($out, 0, 5);
    }

    /**
     * Contexto acotado para el prompt: página actual + fragmentos RAG.
     *
     * @param list<array<string, mixed>> $hits
     * @param array{title?: string, text?: string}|null $pageContext
     */
    public function buildContext(array $hits, ?array $pageContext = null, int $maxChars = 6000): string
    {
        $parts = [];

        $pageTitle = trim((string) ($pageContext['title'] ?? ''));
        $pageText  = trim((string) ($pageContext['text'] ?? ''));

        if ($pageText !== '') {
            $parts[] = "PÁGINA ACTUAL" . ($pageTitle !== '' ? ': ' . $pageTitle : '') . "\n"
                . mb_substr($pageText, 0, 2200);
        }

        foreach (array_values($this->uniqueHits($hits)) as $index => $hit) {
            $number = $index + 1;
            $title  = trim((string) ($hit['title'] ?? 'Material'));
            $where  = trim((string) ($hit['location'] ?? ''));
            $text   = trim((string) ($hit['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $header = '[' . $number . '] ' . $title . ($where !== '' ? ' · ' . $where : '');
            $parts[] = $header . "\n" . mb_substr($text, 0, 1200);
        }

        $context = implode("\n\n", $parts);

        return mb_substr($context, 0, $maxChars);
    }

    /**
     * Un fragmento por documento/fuente. El primero conserva el mejor score
     * porque `retrieve.py` ya devuelve los hits ordenados.
     *
     * @param list<array<string, mixed>> $hits
     *
     * @return list<array<string, mixed>>
     */
    private function uniqueHits(array $hits): array
    {
        $seen = [];
        $out  = [];

        foreach ($hits as $hit) {
            $docId = (int) ($hit['doc_id'] ?? 0);
            $key   = $docId > 0
                ? 'doc:' . $docId
                : 'src:' . ($hit['source'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[]      = $hit;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $hit
     *
     * @return array<string, mixed>|null
     */
    private function normalizeHit(array $hit): ?array
    {
        $payload = is_array($hit['payload'] ?? null) ? $hit['payload'] : [];
        $text    = trim((string) ($payload['text'] ?? ''));

        if ($text === '') {
            return null;
        }

        $source = trim((string) ($payload['source'] ?? $payload['file_path'] ?? ''));
        $title  = $source !== '' ? pathinfo(basename($source), PATHINFO_FILENAME) : 'Material del curso';

        return [
            'score'        => round((float) ($hit['score'] ?? 0.0), 6),
            'doc_id'       => (int) ($payload['doc_id'] ?? 0),
            'workspace_id' => (int) ($payload['workspace_id'] ?? 0),
            'page_id'      => (int) ($payload['page_id'] ?? 0),
            'title'        => $title,
            'source'       => $source,
            'location'     => trim((string) ($payload['location'] ?? '')),
            'topic'        => trim((string) ($payload['topic'] ?? '')),
            'chunk_key'    => trim((string) ($payload['chunk_key'] ?? '')),
            'text'         => $text,
            'snippet'      => mb_substr(preg_replace('/\s+/', ' ', $text) ?? $text, 0, 240),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function queryTokens(string $query): array
    {
        $stopwords = [
            'a', 'al', 'algo', 'como', 'con', 'de', 'del', 'el', 'en', 'es', 'esa',
            'ese', 'esta', 'este', 'for', 'la', 'las', 'lo', 'los', 'más', 'me',
            'mi', 'no', 'o', 'para', 'pero', 'por', 'que', 'qué', 'se', 'sin',
            'sobre', 'su', 'sus', 'un', 'una', 'uno', 'y', 'ya', 'the', 'and',
            'what', 'how', 'where', 'when', 'why', 'is', 'are', 'to', 'of', 'in',
        ];

        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query, 'UTF-8')) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || mb_strlen($part, 'UTF-8') < 3 || in_array($part, $stopwords, true)) {
                continue;
            }
            $tokens[$part] = ($tokens[$part] ?? 0) + 1;
        }

        return $tokens;
    }

    /**
     * @param list<array<string, mixed>> $hits
     *
     * @return array<int, array<string, mixed>>
     */
    private function documentsById(array $hits): array
    {
        $ids = [];

        foreach ($hits as $hit) {
            $id = (int) ($hit['doc_id'] ?? 0);
            // La columna documents.id es INT UNSIGNED; los doc_id de material
            // histórico de Qdrant son hashes de 60 bits y no aplican.
            if ($id > 0 && $id <= 4_294_967_295) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        try {
            $rows = \Config\Database::connect()
                ->table('documents')
                ->select('id, title, file_path, page_id, workspace_id, curso_id, class_date, topic')
                ->whereIn('id', $ids)
                ->get()
                ->getResultArray();
        } catch (\Throwable $error) {
            log_message('warning', 'No se pudieron leer documents para citas: {error}', [
                'error' => $error->getMessage(),
            ]);

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = $row;
        }

        return $map;
    }

    private function retrieveTimeout(): int
    {
        return max(20, min(120, (int) env('RAG_TIMEOUT', 180)));
    }

    /**
     * @param array{ok: bool, exit: int, stdout: string, stderr: string, timed_out: bool} $result
     */
    private function failureMessage(array $result, string $prefix): string
    {
        if ($result['timed_out']) {
            return $prefix . ' El proceso tardó demasiado. Revisa Qdrant/Gemini o intenta con un archivo más pequeño.';
        }

        $lines = preg_split('/\R/', $result['stderr']) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));

        $last = $lines === [] ? '' : (string) end($lines);
        $last = preg_replace('/^ERROR:\s*/i', '', $last) ?? $last;

        return $prefix . ($last !== '' ? ' ' . mb_substr($last, 0, 300) : ' Revisa los logs de writable/logs.');
    }

    private function pageUrl(int $pageId): string
    {
        $base = rtrim((string) config('App')->baseURL, '/');

        return $base . '/pages/' . $pageId;
    }
}
