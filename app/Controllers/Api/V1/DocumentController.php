<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Libraries\RagService;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Documentos del RAG.
 *
 * GET  /api/v1/documents?page_id=12
 * GET  /api/v1/documents?workspace_id=3
 * POST /api/v1/documents       multipart: file, title, topic, class_date, curso_id, page_id
 *
 * Reglas del piloto:
 * - Nunca se lista el corpus global: se exige página o espacio y se comprueba
 *   pertenencia. El rol viewer puede consultar/descargar; solo editor/owner
 *   puede subir.
 * - El archivo se guarda con una extensión blanca (nunca la del cliente tal
 *   cual) y se indexa en Qdrant cuando el entorno RAG está configurado.
 * - Si el entorno RAG no está configurado, el documento queda guardado como
 *   `pending` con un mensaje claro. Así la demo no se rompe por falta de claves.
 */
final class DocumentController extends BaseApiController
{
    private const ALLOWED = [
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'  => 'text/plain',
        'md'   => 'text/markdown',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    private const SELECT = 'id, page_id, workspace_id, title, file_path, source_key, mime_type, size_bytes, curso_id, class_date, topic, indexed_v2, indexed_at, error_message, created_at';

    public function index(): ResponseInterface
    {
        $userId      = (int) auth()->id();
        $pageId      = $this->positiveInt($this->request->getGet('page_id'));
        $workspaceId = $this->positiveInt($this->request->getGet('workspace_id'));

        if ($pageId === null && $workspaceId === null) {
            return $this->error('Indica page_id o workspace_id para listar documentos.', 422);
        }

        $builder = db_connect()
            ->table('documents')
            ->select(self::SELECT)
            ->orderBy('id', 'DESC')
            ->limit(50);

        if ($pageId !== null) {
            if (! $this->access()->canViewPage($pageId, $userId)) {
                return $this->error('No tienes acceso a esta página.', 403);
            }

            // Si además llega workspace_id, debe coincidir con la página.
            $pageWorkspace = $this->access()->pageWorkspaceId($pageId);

            if ($workspaceId !== null && $pageWorkspace !== (int) $workspaceId) {
                return $this->error('La página no pertenece al espacio indicado.', 422);
            }

            $builder->where('page_id', $pageId);
        } elseif ($workspaceId !== null) {
            if ($guard = $this->guardWorkspace($workspaceId)) {
                return $guard;
            }

            $builder->where('workspace_id', $workspaceId);
        }

        $rows = $builder->get()->getResultArray();

        return $this->ok(array_map(fn (array $row): array => $this->formatDocument($row), $rows));
    }

    public function create(): ResponseInterface
    {
        $user   = auth()->user();
        $userId = (int) $user->id;
        $file   = $this->request->getFile('file');

        if ($file === null) {
            return $this->error('Adjunta un archivo para indexar.', 422);
        }

        try {
            if (! $file->isValid()) {
                return $this->error('El archivo no llegó completo. Intenta de nuevo.', 422);
            }
        } catch (Throwable $error) {
            return $this->error('No se pudo leer el archivo.', 422);
        }

        $maxMb = max(1, min(100, (int) env('RAG_MAX_UPLOAD_MB', 20)));
        $size  = (int) $file->getSize();

        if ($size <= 0) {
            return $this->error('El archivo está vacío.', 422);
        }

        if ($size > $maxMb * 1024 * 1024) {
            return $this->error('El archivo supera el límite de ' . $maxMb . ' MB.', 422);
        }

        // La página es obligatoria: sin ella no hay espacio al que acotar.
        $pageId = $this->positiveInt($this->request->getPost('page_id'));

        if ($pageId === null) {
            return $this->error('page_id es obligatorio para subir material.', 422, [
                'page_id' => 'Requerido.',
            ]);
        }

        if (! $this->access()->canViewPage($pageId, $userId)) {
            return $this->error('No tienes acceso a esta página.', 403);
        }

        $workspaceId = $this->access()->pageWorkspaceId($pageId);

        if ($workspaceId === null || ! $this->access()->canEdit($workspaceId, $userId)) {
            return $this->error('Tu rol en este espacio es de solo lectura.', 403);
        }

        $original = $file->getClientName();
        $ext      = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));

        if ($ext === '') {
            $ext = strtolower((string) $file->getClientExtension());
        }

        if (! isset(self::ALLOWED[$ext])) {
            return $this->error('Formato no permitido. Usa PDF, DOCX, PPTX, TXT, MD, JPG, PNG o WEBP.', 422);
        }

        // Comprobamos el MIME real cuando el runtime puede leerlo. Algunos
        // formatos de Office se reportan como application/zip; la extensión
        // blanca sigue siendo la defensa principal.
        try {
            $mime = (string) $file->getMimeType();
        } catch (Throwable) {
            $mime = (string) $file->getClientMimeType();
        }

        if (! $this->mimeLooksSafe($mime, $ext)) {
            return $this->error('El contenido del archivo no coincide con su extensión.', 422);
        }

        $cursoId = max(1, (int) ($this->request->getPost('curso_id') ?? 1));
        $topic   = mb_substr(trim((string) ($this->request->getPost('topic') ?? '')), 0, 255);
        $title   = mb_substr(trim((string) ($this->request->getPost('title') ?? '')), 0, 255);
        $date    = trim((string) ($this->request->getPost('class_date') ?? ''));

        if ($title === '') {
            $title = mb_substr((string) pathinfo($original, PATHINFO_FILENAME), 0, 255);
        }

        if ($title === '') {
            $title = 'Documento ' . date('Y-m-d H:i');
        }

        if ($date !== '' && ! $this->validDate($date)) {
            return $this->error('La fecha de clase debe tener el formato YYYY-MM-DD.', 422);
        }

        $directory = WRITEPATH . 'uploads/rag';

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            return $this->error('No se pudo crear la carpeta de documentos.', 500);
        }

        // getRandomName() conserva la extensión del cliente; la reemplazamos
        // por la extensión blanca ya validada.
        $stored = pathinfo($file->getRandomName(), PATHINFO_FILENAME) . '.' . $ext;

        try {
            $file->move($directory, $stored);
        } catch (Throwable $error) {
            log_message('error', 'No se pudo mover el documento RAG: {error}', ['error' => $error->getMessage()]);

            return $this->error('No se pudo guardar el archivo en el servidor.', 500);
        }

        $relative = 'writable/uploads/rag/' . $stored;
        $db       = db_connect();

        $db->table('documents')->insert([
            'page_id'      => $pageId,
            'workspace_id' => $workspaceId,
            'title'        => $title,
            'file_path'    => $relative,
            'source_key'   => $relative,
            'mime_type'    => $mime !== '' ? $mime : self::ALLOWED[$ext],
            'size_bytes'   => $size,
            'curso_id'     => $cursoId,
            'class_date'   => $date !== '' ? $date : null,
            'topic'        => $topic !== '' ? $topic : null,
            'created_by'   => $userId,
            'indexed_v2'   => false,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $docId = (int) $db->insertID();

        if ($docId <= 0) {
            return $this->error('No se pudo registrar el documento en la base de datos.', 500);
        }

        $rag = new RagService();

        if (! $rag->isConfigured()) {
            $message = 'Archivo guardado. La indexación vectorial está pendiente: configura Qdrant y la clave de Gemini en el entorno.';
            $db->table('documents')->where('id', $docId)->update(['error_message' => $message]);

            $row = $this->find($docId);

            return $this->ok(
                ($row !== null ? $this->formatDocument($row) : ['id' => $docId]) + ['chunks' => 0, 'warning' => $message],
                201,
            );
        }

        try {
            $result = $rag->ingest($docId, $relative, [
                'curso_id'    => $cursoId,
                'class_date'  => $date,
                'topic'       => $topic,
                'page_id'     => $pageId,
                'workspace_id'=> $workspaceId,
            ]);

            $db->table('documents')->where('id', $docId)->update([
                'indexed_v2'    => true,
                'indexed_at'    => date('Y-m-d H:i:s'),
                'error_message' => null,
            ]);

            $row = $this->find($docId);

            return $this->ok(
                ($row !== null ? $this->formatDocument($row) : ['id' => $docId]) + ['chunks' => $result['chunks'] ?? 0],
                201,
            );
        } catch (Throwable $error) {
            $message = mb_substr($error->getMessage(), 0, 1000);

            $db->table('documents')->where('id', $docId)->update([
                'indexed_v2'    => false,
                'indexed_at'    => null,
                'error_message' => $message,
            ]);

            log_message('warning', 'Ingest RAG falló para el documento {id}: {error}', [
                'id'    => $docId,
                'error' => $message,
            ]);

            $row = $this->find($docId);

            return $this->error(
                'El archivo se guardó, pero no se pudo indexar: ' . $message,
                422,
                ['document' => $row !== null ? $this->formatDocument($row) : ['id' => $docId]],
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find(int $docId): ?array
    {
        return db_connect()
            ->table('documents')
            ->select(self::SELECT)
            ->where('id', $docId)
            ->get()
            ->getRowArray();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function formatDocument(array $row): array
    {
        $filePath = (string) ($row['file_path'] ?? '');
        $indexed  = (bool) ($row['indexed_v2'] ?? false);
        $error    = trim((string) ($row['error_message'] ?? ''));
        $pending  = ! $indexed && $error !== '';

        return [
            'id'           => (int) ($row['id'] ?? 0),
            'page_id'      => isset($row['page_id']) && $row['page_id'] !== null ? (int) $row['page_id'] : null,
            'workspace_id' => isset($row['workspace_id']) && $row['workspace_id'] !== null ? (int) $row['workspace_id'] : null,
            'title'        => (string) ($row['title'] ?? 'Documento'),
            'file_name'    => $filePath !== '' ? basename($filePath) : null,
            'mime_type'    => $row['mime_type'] ?? null,
            'size_bytes'   => isset($row['size_bytes']) ? (int) $row['size_bytes'] : null,
            'size_label'   => nexo_bytes(isset($row['size_bytes']) ? (int) $row['size_bytes'] : null),
            'curso_id'     => isset($row['curso_id']) ? (int) $row['curso_id'] : null,
            'class_date'   => $row['class_date'] ?? null,
            'topic'        => $row['topic'] ?? null,
            'indexed'      => $indexed,
            'status'       => $indexed ? 'indexed' : ($pending ? 'pending' : 'error'),
            'indexed_at'   => $row['indexed_at'] ?? null,
            'error'        => $error !== '' ? $error : null,
            'created_at'   => $row['created_at'] ?? null,
        ];
    }

    private function mimeLooksSafe(string $mime, string $ext): bool
    {
        $mime = strtolower(trim(explode(';', $mime, 2)[0]));

        if ($mime === '' || $mime === 'application/octet-stream') {
            return true;
        }

        if (in_array($ext, ['docx', 'pptx'], true)) {
            return in_array($mime, ['application/zip', 'application/octet-stream', self::ALLOWED[$ext]], true);
        }

        return $mime === self::ALLOWED[$ext]
            || ($ext === 'md' && in_array($mime, ['text/plain', 'text/markdown'], true));
    }

    private function validDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
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
