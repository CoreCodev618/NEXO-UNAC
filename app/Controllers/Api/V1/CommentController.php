<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Libraries\NexoRepository;
use App\Libraries\WorkspaceAccess;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Comentarios por página.
 *
 * Cualquier miembro del espacio (incluido viewer) puede leer y comentar.
 * No se puede comentar en una página de otro espacio, aunque se conozca el id.
 */
final class CommentController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $pageId = $this->positiveInt($this->request->getGet('page_id'));

        if ($pageId === null) {
            return $this->error('page_id es obligatorio.', 422);
        }

        if (! (new WorkspaceAccess())->canViewPage($pageId, (int) auth()->id())) {
            return $this->error('No tienes acceso a esta página.', 403);
        }

        return $this->ok((new NexoRepository())->comments($pageId));
    }

    public function create(): ResponseInterface
    {
        $data   = $this->payload();
        $pageId = $this->positiveInt($data['page_id'] ?? null);

        if ($pageId === null) {
            return $this->error('page_id es obligatorio.', 422, ['page_id' => 'Requerido.']);
        }

        if (! (new WorkspaceAccess())->canViewPage($pageId, (int) auth()->id())) {
            return $this->error('No tienes acceso a esta página.', 403);
        }

        $content = trim((string) ($data['content'] ?? ''));

        if ($content === '') {
            return $this->error('Escribe un comentario.', 422, ['content' => 'Requerido.']);
        }

        if (mb_strlen($content) > 2000) {
            return $this->error('El comentario no puede superar 2000 caracteres.', 422);
        }

        $db = db_connect();
        $db->table('comments')->insert([
            'page_id'    => $pageId,
            'user_id'    => (int) auth()->id(),
            'content'    => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $db->insertID();

        if ($id <= 0) {
            return $this->error('No se pudo guardar el comentario.', 500);
        }

        foreach ((new NexoRepository())->comments($pageId) as $comment) {
            if ((int) $comment['id'] === $id) {
                return $this->ok($comment, 201);
            }
        }

        return $this->ok(['id' => $id, 'page_id' => $pageId, 'content' => $content], 201);
    }

    public function delete(string $id): ResponseInterface
    {
        $commentId = (int) $id;
        $comment   = db_connect()
            ->table('comments')
            ->select('id, page_id, user_id')
            ->where('id', $commentId)
            ->get()
            ->getRowArray();

        if ($comment === null) {
            return $this->error('Comentario no encontrado.', 404);
        }

        $pageId = (int) $comment['page_id'];
        $userId = (int) auth()->id();
        $access = new WorkspaceAccess();

        if (! $access->canViewPage($pageId, $userId)) {
            return $this->error('No tienes acceso a esta página.', 403);
        }

        $workspaceId = $access->pageWorkspaceId($pageId);
        $canManage   = $workspaceId !== null && $access->canManage($workspaceId, $userId);

        if ((int) ($comment['user_id'] ?? 0) !== $userId && ! $canManage) {
            return $this->error('Solo el autor o el propietario del espacio puede eliminar este comentario.', 403);
        }

        db_connect()->table('comments')->where('id', $commentId)->delete();

        return $this->ok(['id' => $commentId, 'page_id' => $pageId]);
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
