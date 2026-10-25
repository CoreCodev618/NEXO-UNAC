<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Libraries\GroqService;
use App\Libraries\RagService;
use App\Libraries\WorkspaceAccess;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Tutor NEXO: RAG sobre Qdrant + Groq, con memoria por sesión.
 *
 * Respuesta:
 * {session_id, reply, sources[], model, page_id, degraded}
 * donde cada source es {doc_id, title, page_id, url, source, location, topic,
 * score, snippet, chunk_key}.
 */
final class AIChatController extends BaseApiController
{
    public function send(): ResponseInterface
    {
        $payload = $this->payload();
        $message = trim((string) ($payload['message'] ?? ''));

        if ($message === '') {
            return $this->error('Escribe una pregunta para el tutor.', 422);
        }

        if (mb_strlen($message) > 1200) {
            return $this->error('La pregunta es demasiado larga (máximo 1200 caracteres).', 422);
        }

        $userId      = (int) auth()->id();
        $access      = new WorkspaceAccess();
        $pageId      = $this->positiveInt($payload['page_id'] ?? null);
        $sessionId   = $this->positiveInt($payload['session_id'] ?? null);
        $workspaceIds = $access->workspaceIds($userId);

        if ($workspaceIds === []) {
            return $this->error('No perteneces a ningún espacio con material.', 403);
        }

        // La autorización ocurre ANTES de crear o tocar una sesión. Si la
        // página no existe o no es del usuario, no se escribe nada en BD.
        if ($pageId !== null && ! $access->canViewPage($pageId, $userId)) {
            return $this->error('No tienes acceso al contexto de esta página.', 403);
        }

        try {
            $session = $this->resolveSession($sessionId, $userId, $pageId, $message);
            $pageId  = isset($session['page_id']) && $session['page_id'] !== null
                ? (int) $session['page_id']
                : $pageId;
        } catch (RuntimeException $error) {
            return $this->error($error->getMessage(), 404);
        } catch (\Throwable $error) {
            log_message('error', 'No se pudo crear la sesión de chat: {error}', ['error' => $error->getMessage()]);

            return $this->error('No se pudo iniciar la conversación. Revisa la base de datos.', 500);
        }

        if ($pageId !== null && ! $access->canViewPage($pageId, $userId)) {
            return $this->error('No tienes acceso al contexto de esta página.', 403);
        }

        $pageContext = $pageId !== null ? $this->pageContext($pageId, $userId) : null;
        $workspaceId = $pageId !== null ? $access->pageWorkspaceId($pageId) : null;

        $rag        = new RagService();
        $hits       = [];
        $ragError   = null;
        $localHits  = false;

        if ($rag->isConfigured()) {
            try {
                $hits = $rag->retrieve($message, $workspaceId !== null
                    ? ['workspace_id' => $workspaceId, 'page_id' => $pageId]
                    : ['workspace_ids' => $workspaceIds]);
            } catch (\Throwable $error) {
                $ragError = $error->getMessage();
                log_message('warning', 'RAG vectorial falló: {error}', ['error' => $ragError]);
            }
        } else {
            $ragError = 'Qdrant/Gemini no configurados en este entorno.';
        }

        // Fallback local: la demo sigue respondiendo con evidencia de MariaDB
        // y citas verificables aunque no haya claves ni Qdrant.
        if ($hits === []) {
            $hits      = $rag->localRetrieve($message, $workspaceIds);
            $localHits = $hits !== [];
        }

        // Última barrera: descarta cualquier cita fuera de los espacios del
        // usuario, incluso si el motor vectorial devolviera un payload sucio.
        $hits = array_values(array_filter($hits, static function (array $hit) use ($workspaceIds, $pageId): bool {
            $hitWorkspace = (int) ($hit['workspace_id'] ?? 0);
            $hitPage      = (int) ($hit['page_id'] ?? 0);

            if ($hitWorkspace <= 0 || ! in_array($hitWorkspace, $workspaceIds, true)) {
                return false;
            }

            return $pageId === null || $hitPage === $pageId;
        }));

        $citations = $rag->citations($hits, $workspaceIds);
        $context   = $rag->buildContext($hits, $pageContext);
        $model     = null;
        $degraded  = $ragError !== null || $localHits;

        if ($context === '') {
            $reply = $ragError !== null
                ? 'No pude consultar el material en este momento. La página actual sí está disponible, pero no encontré material indexado para tu pregunta. Intenta de nuevo o sube el archivo de la clase.'
                : 'No encontré material indexado para responder eso. Sube el PDF, DOCX o PPTX de la clase en «Material para el tutor» y vuelve a preguntar.';
        } else {
            try {
                $answer = (new GroqService())->chat(
                    $this->prompt($message, $context, $ragError !== null, (int) $session['id'], $localHits),
                );
                $reply  = $answer['content'];
                $model  = $answer['model'];
            } catch (\Throwable $error) {
                // Sin GROQ_API_KEY (o con cuota agotada) la demo no se cae:
                // responde con extractos y citas del material recuperado.
                log_message('warning', 'Groq no respondió; se usa respuesta local: {error}', [
                    'error' => $error->getMessage(),
                ]);

                $reply    = $this->fallbackReply($message, $hits, $pageContext);
                $degraded = true;
            }
        }

        $this->saveExchange((int) $session['id'], $message, $reply);

        return $this->ok([
            'session_id' => (int) $session['id'],
            'reply'      => $reply,
            'sources'    => $citations,
            'model'      => $model,
            'page_id'    => $pageId,
            'degraded'   => $degraded,
            'retrieval'  => $localHits ? 'local' : ($hits !== [] ? 'vector' : 'none'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSession(?int $sessionId, int $userId, ?int $pageId, string $message): array
    {
        $db = \Config\Database::connect();

        if ($sessionId !== null) {
            $session = $db->table('chat_sessions')
                ->where('id', $sessionId)
                ->where('user_id', $userId)
                ->get()
                ->getRowArray();

            if ($session === null) {
                throw new RuntimeException('La conversación no existe o no te pertenece.');
            }

            $sessionPage = isset($session['page_id']) && $session['page_id'] !== null
                ? (int) $session['page_id']
                : null;

            if ($pageId !== null && $sessionPage !== null && $pageId !== $sessionPage) {
                throw new RuntimeException('La conversación pertenece a otra página.');
            }

            return $session;
        }

        $db->table('chat_sessions')->insert([
            'user_id'    => $userId,
            'page_id'    => $pageId,
            'title'      => mb_substr($message, 0, 120),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $db->insertID();

        if ($id <= 0) {
            throw new RuntimeException('No se pudo crear la conversación.');
        }

        return [
            'id'      => $id,
            'user_id' => $userId,
            'page_id' => $pageId,
        ];
    }

    /**
     * Contexto de la página actual. Solo MariaDB; no hay datos de demo que
     * puedan saltarse la autorización.
     *
     * @return array{title: string, text: string}
     */
    private function pageContext(int $pageId, int $userId): array
    {
        $db   = \Config\Database::connect();
        $page = $db->table('pages')
            ->where('id', $pageId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($page === null) {
            throw new RuntimeException('La página no existe.');
        }

        $member = $db->table('workspace_members')
            ->where('workspace_id', (int) $page['workspace_id'])
            ->where('user_id', $userId)
            ->countAllResults();

        if ($member === 0) {
            throw new RuntimeException('No tienes acceso al contexto de esta página.');
        }

        $blocks = $db->table('blocks')
            ->where('page_id', $pageId)
            ->orderBy('order_index', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'title' => (string) ($page['title'] ?? 'Página'),
            'text'  => $this->blocksText($blocks),
        ];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function blocksText(array $blocks): string
    {
        $parts = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $content = $block['content'] ?? [];
            if (is_string($content)) {
                $decoded = json_decode($content, true);
                $content = is_array($decoded) ? $decoded : [];
            }

            if (! is_array($content)) {
                continue;
            }

            $chunk = [];

            foreach (['title', 'text'] as $field) {
                if (isset($content[$field]) && is_scalar($content[$field])) {
                    $chunk[] = trim((string) $content[$field]);
                }
            }

            if (isset($content['code']) && is_scalar($content['code'])) {
                $chunk[] = 'Código: ' . trim((string) $content['code']);
            }

            if (isset($content['items']) && is_array($content['items'])) {
                foreach ($content['items'] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $text = trim((string) ($item['text'] ?? ''));
                    if ($text !== '') {
                        $chunk[] = '- ' . $text . (! empty($item['checked']) ? ' (hecho)' : '');
                    }
                }
            }

            if (isset($content['headers']) && is_array($content['headers'])) {
                $chunk[] = implode(' | ', array_map('strval', $content['headers']));
            }

            if (isset($content['rows']) && is_array($content['rows'])) {
                foreach ($content['rows'] as $row) {
                    if (is_array($row)) {
                        $chunk[] = implode(' | ', array_map('strval', $row));
                    }
                }
            }

            if (isset($content['cite']) && is_scalar($content['cite'])) {
                $chunk[] = '— ' . trim((string) $content['cite']);
            }

            $chunk = array_values(array_filter(array_map('trim', $chunk), static fn (string $line): bool => $line !== ''));
            if ($chunk !== []) {
                $parts[] = implode("\n", $chunk);
            }
        }

        return mb_substr(trim(implode("\n\n", $parts)), 0, 4000);
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function prompt(string $message, string $context, bool $externalFailed, int $sessionId, bool $localEvidence = false): array
    {
        $system = <<<'PROMPT'
Eres el Tutor NEXO de la Universidad Nacional del Callao. Ayudas a estudiantes de FIIS a estudiar con el material de sus cursos.

Reglas obligatorias:
1. Responde en español, claro y breve (máximo 180 palabras).
2. Usa únicamente el CONTEXTO. No uses conocimiento externo para afirmar hechos del curso.
3. Cuando uses un fragmento del material, cita su número con corchetes ASCII: [1], [2], etc. Nunca uses 【】.
4. Escribe texto plano: no uses Markdown (nada de **, ##, tablas ni listas con asteriscos). Separa las ideas con saltos de línea.
5. Si el contexto no alcanza, responde exactamente: "Con la información disponible no puedo confirmar eso." y sugiere subir el material que falta.
6. No inventes fuentes, fechas, eventos, enlaces ni nombres de archivos.
7. La PÁGINA ACTUAL es el apunte que el estudiante está editando; puedes resumirla o explicarla sin citarla como fuente externa.
PROMPT;

        if ($localEvidence) {
            $system .= "\n8. El material recuperado proviene del índice local de la demo; puedes citarlo con [1], [2], etc.";
        } elseif ($externalFailed) {
            $system .= "\n8. El retrieval vectorial falló en esta petición: no cites fragmentos externos.";
        }

        $messages = [
            ['role' => 'system', 'content' => $system],
        ];

        foreach ($this->history($sessionId) as $row) {
            $messages[] = $row;
        }

        $messages[] = [
            'role'    => 'user',
            'content' => "CONTEXTO:\n" . $context . "\n\nPREGUNTA:\n" . $message,
        ];

        return $messages;
    }

    /**
     * Respuesta determinista cuando Groq no está disponible. No inventa: solo
     * presenta los extractos recuperados y la página actual.
     *
     * @param list<array<string, mixed>> $hits
     * @param array{title: string, text: string}|null $pageContext
     */
    private function fallbackReply(string $message, array $hits, ?array $pageContext): string
    {
        $lines = [];
        $seen  = [];
        $count = 0;

        foreach ($hits as $hit) {
            $docId = (int) ($hit['doc_id'] ?? 0);
            $key   = $docId > 0 ? 'doc:' . $docId : 'src:' . ($hit['source'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $count++;

            if ($count > 3) {
                break;
            }

            $title = trim((string) ($hit['title'] ?? 'Material del curso'));
            $text  = trim((string) ($hit['text'] ?? ''));
            $text  = preg_replace('/\s+/', ' ', $text) ?? $text;

            if ($text !== '') {
                $lines[] = '[' . $count . '] ' . $title . "\n" . mb_substr($text, 0, 420);
            }
        }

        $pageText = trim((string) ($pageContext['text'] ?? ''));

        if ($lines === [] && $pageText === '') {
            return 'Con la información disponible no puedo confirmar eso. Sube el material de la clase y vuelve a preguntar.';
        }

        $reply = "El tutor generativo no está configurado en este entorno, así que respondo con el material recuperado.\n\n";

        if ($lines !== []) {
            $reply .= implode("\n\n", $lines);
            $reply .= "\n\nPuedes abrir las fuentes citadas para leer el contexto completo.";
        } else {
            $reply .= 'No encontré fragmentos externos para «' . mb_substr($message, 0, 160) . '». ';
            $reply .= 'La página actual contiene material relacionado; revisa el contexto o sube el documento que falta.';
        }

        return $reply;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function history(int $sessionId): array
    {
        if ($sessionId <= 0) {
            return [];
        }

        $rows = \Config\Database::connect()
            ->table('chat_messages')
            ->select('role, content')
            ->where('session_id', $sessionId)
            ->orderBy('id', 'DESC')
            ->limit(6)
            ->get()
            ->getResultArray();

        $rows = array_reverse($rows);
        $out  = [];

        foreach ($rows as $row) {
            $role = (string) ($row['role'] ?? '');
            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $content = trim((string) ($row['content'] ?? ''));
            if ($content !== '') {
                $out[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
            }
        }

        return $out;
    }

    private function saveExchange(int $sessionId, string $userMessage, string $reply): void
    {
        try {
            $db  = \Config\Database::connect();
            $now = date('Y-m-d H:i:s');

            $db->transStart();
            $db->table('chat_messages')->insert([
                'session_id' => $sessionId,
                'role'       => 'user',
                'content'    => $userMessage,
                'created_at' => $now,
            ]);
            $db->table('chat_messages')->insert([
                'session_id' => $sessionId,
                'role'       => 'assistant',
                'content'    => $reply,
                'created_at' => $now,
            ]);
            $db->transComplete();
        } catch (\Throwable $error) {
            log_message('error', 'No se guardó el intercambio de chat: {error}', ['error' => $error->getMessage()]);
        }
    }

    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $number = (int) $value;

            return $number > 0 ? $number : null;
        }

        return null;
    }
}
