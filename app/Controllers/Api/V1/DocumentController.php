<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Libraries\RagService;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Documentos del RAG.
 *
 * GET  /api/v1/documents       lista el material indexado
 * POST /api/v1/documents       multipart: file, title, topic, class_date, curso_id, page_id
 *
 * El archivo se guarda en writable/uploads/rag y se indexa en el momento con
 * `python/ingest.py --doc-id`. Si el ingest falla, el documento queda en
 * MariaDB con `error_message` para poder reintentar/depurar.
 */
final class DocumentController extends BaseApiController
{
    private const ALLOWED = [
        'pdf'  => 'PDF',
        'docx' => 'DOCX',
        'pptx' => 'PPTX',
        'txt'  => 'TXT',
        'md'   => 'MD',
        'jpg'  => 'JPG',
        'jpeg' => 'JPG',
        'png'  => 'PNG',
        'webp' => 'WEBP',
    ];

    public function index(): ResponseInterface
    {
        $pageId = $this->positiveInt($this->request->getGet('page_id'));

        if ($pageId !== null) {
            try {
                $this->assertPageAccess($pageId, (int) auth()->id());
            } catch (\RuntimeException $error) {
                return $this->error($error->getMessage(), 403);
            }
        }

        $builder = \Config\Database::connect()
            ->table('documents')
            ->select('id, page_id, title, file_path, source_key, mime_type, size_bytes, curso_id, class_date, topic, indexed_v2, indexed_at, error_message, created_at')
            ->orderBy('id', 'DESC')
            ->limit(50);

        if ($pageId !== null) {
            $builder->where('page_id', $pageId);
        }

        $rows = $builder->get()->getResultArray();

        return $this->ok(array_map(fn (array $row): array => $this->formatDocument($row), $rows));
    }

    public function create(): ResponseInterface
    {
        $user = auth()->user();
        $file = $this->request->getFile('file');

        if ($file === null) {
            return $this->error('Adjunta un archivo para indexar.', 422);
        }

        try {
            if (! $file->isValid()) {
                return $this->error('El archivo no llegó completo. Intenta de nuevo.', 422);
            }
        } catch (Throwable $error) {
            return $this->error('No se pudo leer el archivo: ' . $error->getMessage(), 422);
        }

        $maxMb = max(1, min(100, (int) env('RAG_MAX_UPLOAD_MB', 20)));
        $size  = (int) $file->getSize();

        if ($size <= 0) {
            return $this->error('El archivo está vacío.', 422);
        }

        if ($size > $maxMb * 1024 * 1024) {
            return $this->error('El archivo supera el límite de ' . $maxMb . ' MB.', 422);
        }

        $original = $file->getClientName();
        $ext      = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));

        if ($ext === '') {
            $ext = strtolower($file->getClientExtension());
        }

        if (! isset(self::ALLOWED[$ext])) {
            return $this->error('Formato no permitido. Usa PDF, DOCX, PPTX, TXT, MD, JPG, PNG o WEBP.', 422);
        }

        $pageId = $this->positiveInt($this->request->getPost('page_id'));

        if ($pageId !== null) {
            try {
                $page = \Config\Database::connect()
                    ->table('pages')
                    ->where('id', $pageId)
                    ->get()
                    ->getRowArray();

                if ($page === null) {
                    // En la demo de A todavía puede no existir la página de B:
                    // se indexa igual, sin asociación, en vez de romper el FK.
                    $pageId = null;
                } else {
                    $this->assertPageAccess($pageId, (int) $user->id);
                }
            } catch (\RuntimeException $error) {
                return $this->error($error->getMessage(), 403);
            }
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

        $stored = $file->getRandomName();

        try {
            $file->move($directory, $stored);
        } catch (Throwable $error) {
            log_message('error', 'No se pudo mover el documento RAG: {error}', ['error' => $error->getMessage()]);

            return $this->error('No se pudo guardar el archivo en el servidor.', 500);
        }

        $relative = 'writable/uploads/rag/' . $stored;
        $db       = \Config\Database::connect();

        $db->table('documents')->insert([
            'page_id'      => $pageId,
            'title'        => $title,
            'file_path'    => $relative,
            'source_key'   => $relative,
            'mime_type'    => $file->getClientMimeType(),
            'size_bytes'   => $size,
            'curso_id'     => $cursoId,
            'class_date'   => $date !== '' ? $date : null,
            'topic'        => $topic !== '' ? $topic : null,
            'created_by'   => (int) $user->id,
            'indexed_v2'   => false,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $docId = (int) $db->insertID();

        if ($docId <= 0) {
            return $this->error('No se pudo registrar el documento en la base de datos.', 500);
        }

        try {
            $result = (new RagService())->ingest($docId, $relative, [
                'curso_id'   => $cursoId,
                'class_date' => $date,
                'topic'      => $topic,
            ]);

            $db->table('documents')->where('id', $docId)->update([
                'indexed_v2'    => true,
                'indexed_at'    => date('Y-m-d H:i:s'),
                'error_message' => null,
            ]);

            $row = $this->find($docId);

            return $this->ok(
                ($row !== null ? $this->formatDocument($row) : ['id' => $docId]) + ['chunks' => $result['chunks']],
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
        return \Config\Database::connect()
            ->table('documents')
            ->select('id, page_id, title, file_path, source_key, mime_type, size_bytes, curso_id, class_date, topic, indexed_v2, indexed_at, error_message, created_at')
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

        return [
            'id'          => (int) ($row['id'] ?? 0),
            'page_id'     => isset($row['page_id']) && $row['page_id'] !== null ? (int) $row['page_id'] : null,
            'title'       => (string) ($row['title'] ?? 'Documento'),
            'file_name'   => $filePath !== '' ? basename($filePath) : null,
            'mime_type'   => $row['mime_type'] ?? null,
            'size_bytes'  => isset($row['size_bytes']) ? (int) $row['size_bytes'] : null,
            'size_label'  => nexo_bytes(isset($row['size_bytes']) ? (int) $row['size_bytes'] : null),
            'curso_id'    => isset($row['curso_id']) ? (int) $row['curso_id'] : null,
            'class_date'  => $row['class_date'] ?? null,
            'topic'       => $row['topic'] ?? null,
            'indexed'     => $indexed,
            'status'      => $indexed ? 'indexed' : ($error !== '' ? 'error' : 'pending'),
            'indexed_at'  => $row['indexed_at'] ?? null,
            'error'       => $error !== '' ? $error : null,
            'created_at'  => $row['created_at'] ?? null,
        ];
    }

    private function assertPageAccess(int $pageId, int $userId): void
    {
        $db   = \Config\Database::connect();
        $page = $db->table('pages')
            ->where('id', $pageId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($page === null) {
            throw new \RuntimeException('La página no existe.');
        }

        $member = $db->table('workspace_members')
            ->where('workspace_id', (int) $page['workspace_id'])
            ->where('user_id', $userId)
            ->countAllResults();

        if ($member === 0) {
            throw new \RuntimeException('No tienes acceso a esta página.');
        }
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
