<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Libraries\NexoRepository;
use App\Libraries\WorkspaceAccess;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Adjuntos de una página.
 *
 * POST /api/v1/attachments   multipart: file, page_id
 * GET  /api/v1/attachments/12
 *
 * Solo editor/owner sube archivos; cualquier miembro descarga. El nombre en
 * disco es aleatorio y la extensión se reemplaza por una de la lista blanca.
 */
final class AttachmentController extends BaseApiController
{
    private const ALLOWED = [
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'zip'  => 'application/zip',
        'txt'  => 'text/plain',
        'md'   => 'text/markdown',
    ];

    public function create(): ResponseInterface
    {
        $userId = (int) auth()->id();
        $pageId = $this->positiveInt($this->request->getPost('page_id'));

        if ($pageId === null) {
            return $this->error('page_id es obligatorio.', 422, ['page_id' => 'Requerido.']);
        }

        $access      = new WorkspaceAccess();
        $workspaceId = $access->pageWorkspaceId($pageId);

        if ($workspaceId === null) {
            return $this->error('La página no existe.', 404);
        }

        if (! $access->canView($workspaceId, $userId)) {
            return $this->error('No tienes acceso a esta página.', 403);
        }

        if (! $access->canEdit($workspaceId, $userId)) {
            return $this->error('Tu rol en este espacio es de solo lectura.', 403);
        }

        $file = $this->request->getFile('file');

        if ($file === null) {
            return $this->error('Adjunta un archivo.', 422);
        }

        try {
            if (! $file->isValid()) {
                return $this->error('El archivo no llegó completo. Intenta de nuevo.', 422);
            }
        } catch (Throwable) {
            return $this->error('No se pudo leer el archivo.', 422);
        }

        $maxMb = max(1, min(50, (int) env('NEXO_ATTACHMENT_MAX_MB', 10)));
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
            $ext = strtolower((string) $file->getClientExtension());
        }

        if (! isset(self::ALLOWED[$ext])) {
            return $this->error('Formato no permitido. Usa PDF, DOCX, PPTX, JPG, PNG, ZIP o TXT.', 422);
        }

        try {
            $mime = (string) $file->getMimeType();
        } catch (Throwable) {
            $mime = (string) $file->getClientMimeType();
        }

        if (! $this->mimeLooksSafe($mime, $ext)) {
            return $this->error('El contenido del archivo no coincide con su extensión.', 422);
        }

        $folder = WRITEPATH . 'uploads/attachments/' . date('Y/m');

        if (! is_dir($folder) && ! mkdir($folder, 0o775, true) && ! is_dir($folder)) {
            return $this->error('No se pudo crear la carpeta de adjuntos.', 500);
        }

        $stored = pathinfo($file->getRandomName(), PATHINFO_FILENAME) . '.' . $ext;
        $name   = basename($original);
        $name   = $name !== '' ? mb_substr($name, 0, 255) : ('adjunto.' . $ext);

        try {
            $file->move($folder, $stored);
        } catch (Throwable $error) {
            log_message('error', 'No se pudo mover el adjunto: {error}', ['error' => $error->getMessage()]);

            return $this->error('No se pudo guardar el archivo en el servidor.', 500);
        }

        $relative = 'attachments/' . date('Y/m') . '/' . $stored;

        $db = db_connect();
        $db->table('attachments')->insert([
            'page_id'    => $pageId,
            'user_id'    => $userId,
            'file_path'  => $relative,
            'file_name'  => $name,
            'file_type'  => $mime !== '' ? $mime : self::ALLOWED[$ext],
            'file_size'  => $size,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $db->insertID();

        if ($id <= 0) {
            @unlink($folder . '/' . $stored);

            return $this->error('No se pudo registrar el adjunto.', 500);
        }

        foreach ((new NexoRepository())->attachments($pageId) as $attachment) {
            if ((int) $attachment['id'] === $id) {
                return $this->ok($attachment, 201);
            }
        }

        return $this->ok(['id' => $id, 'page_id' => $pageId, 'file_name' => $name], 201);
    }

    public function file(string $id): ResponseInterface
    {
        $attachmentId = (int) $id;
        $file = db_connect()
            ->table('attachments')
            ->select('id, page_id, file_path, file_name, file_type')
            ->where('id', $attachmentId)
            ->get()
            ->getRowArray();

        if ($file === null) {
            return $this->error('Archivo no encontrado.', 404);
        }

        $pageId = (int) $file['page_id'];

        if (! (new WorkspaceAccess())->canViewPage($pageId, (int) auth()->id())) {
            return $this->error('No tienes acceso a este archivo.', 403);
        }

        $relative = ltrim((string) ($file['file_path'] ?? ''), '/');
        $absolute = realpath(WRITEPATH . 'uploads/' . $relative);
        $uploads  = realpath(WRITEPATH . 'uploads');

        if ($absolute === false || $uploads === false || ! str_starts_with($absolute, $uploads . DIRECTORY_SEPARATOR)) {
            return $this->error('El archivo ya no está disponible.', 404);
        }

        $name = trim((string) ($file['file_name'] ?? ''));
        if ($name === '') {
            $name = basename($absolute);
        }

        $content = file_get_contents($absolute);

        if ($content === false) {
            return $this->error('No se pudo leer el archivo.', 500);
        }

        $response = new \CodeIgniter\HTTP\DownloadResponse($name, true);
        $response->setBinary($content);

        return $response;
    }

    private function mimeLooksSafe(string $mime, string $ext): bool
    {
        $mime = strtolower(trim(explode(';', $mime, 2)[0]));

        if ($mime === '' || $mime === 'application/octet-stream') {
            return true;
        }

        if (in_array($ext, ['docx', 'pptx', 'zip'], true)) {
            return in_array($mime, ['application/zip', 'application/octet-stream', self::ALLOWED[$ext]], true);
        }

        return $mime === self::ALLOWED[$ext]
            || ($ext === 'md' && in_array($mime, ['text/plain', 'text/markdown'], true));
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
