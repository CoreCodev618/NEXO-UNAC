<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Libraries\WorkspaceAccess;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Etiquetas de las páginas.
 *
 * GET    /api/v1/tags?workspace_id=1
 * POST   /api/v1/tags             {page_id, name, color?}
 * DELETE /api/v1/tags/{tagId}     {page_id}
 *
 * Solo editor/owner puede modificar etiquetas. La etiqueta es global (name
 * UNIQUE), pero la relación page_tags sí está acotada a la página.
 */
final class TagController extends BaseApiController
{
    private const COLORS = ['wine', 'gold', 'sage'];

    public function index(): ResponseInterface
    {
        $workspaceId = $this->positiveInt($this->request->getGet('workspace_id'));

        if ($workspaceId === null) {
            return $this->error('workspace_id es obligatorio.', 422);
        }

        if ($guard = $this->guardWorkspace($workspaceId)) {
            return $guard;
        }

        $rows = db_connect()
            ->table('tags t')
            ->select('t.id, t.name, t.color, COUNT(DISTINCT pt.page_id) AS pages')
            ->join('page_tags pt', 'pt.tag_id = t.id', 'INNER')
            ->join('pages p', 'p.id = pt.page_id', 'INNER')
            ->where('p.workspace_id', $workspaceId)
            ->where('p.deleted_at', null)
            ->groupBy('t.id, t.name, t.color')
            ->orderBy('t.name', 'ASC')
            ->get()
            ->getResultArray();

        return $this->ok(array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'name'  => (string) $row['name'],
            'color' => (string) ($row['color'] ?? 'sage'),
            'pages' => (int) $row['pages'],
        ], $rows));
    }

    public function attach(): ResponseInterface
    {
        $data   = $this->payload();
        $pageId = $this->positiveInt($data['page_id'] ?? null);
        $name   = trim((string) ($data['name'] ?? ''));

        if ($pageId === null) {
            return $this->error('page_id es obligatorio.', 422, ['page_id' => 'Requerido.']);
        }

        if ($name === '') {
            return $this->error('Escribe el nombre de la etiqueta.', 422, ['name' => 'Requerido.']);
        }

        if (mb_strlen($name) > 80) {
            return $this->error('La etiqueta no puede superar 80 caracteres.', 422);
        }

        $access      = new WorkspaceAccess();
        $workspaceId = $access->pageWorkspaceId($pageId);

        if ($workspaceId === null) {
            return $this->error('La página no existe.', 404);
        }

        if (! $access->canView($workspaceId, (int) auth()->id())) {
            return $this->error('No tienes acceso a esta página.', 403);
        }

        if (! $access->canEdit($workspaceId, (int) auth()->id())) {
            return $this->error('Tu rol en este espacio es de solo lectura.', 403);
        }

        $color = strtolower(trim((string) ($data['color'] ?? 'sage')));
        $color = in_array($color, self::COLORS, true) ? $color : 'sage';

        $db  = db_connect();
        $tag = $db->table('tags')->where('name', $name)->get()->getRowArray();

        if ($tag === null) {
            $db->table('tags')->insert([
                'name'       => $name,
                'color'      => $color,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $tagId = (int) $db->insertID();
        } else {
            $tagId = (int) $tag['id'];
        }

        if ($tagId <= 0) {
            return $this->error('No se pudo crear la etiqueta.', 500);
        }

        $linked = $db->table('page_tags')
            ->where('page_id', $pageId)
            ->where('tag_id', $tagId)
            ->countAllResults();

        if ($linked === 0) {
            $db->table('page_tags')->insert([
                'page_id' => $pageId,
                'tag_id'  => $tagId,
            ]);
        }

        return $this->ok([
            'id'    => $tagId,
            'name'  => $name,
            'color' => $tag !== null ? (string) ($tag['color'] ?? $color) : $color,
        ], 201);
    }

    public function detach(string $id): ResponseInterface
    {
        $tagId  = (int) $id;
        $data   = $this->payload();
        $pageId = $this->positiveInt($data['page_id'] ?? null);

        if ($tagId <= 0 || $pageId === null) {
            return $this->error('Indica una etiqueta y page_id válidos.', 422);
        }

        $access      = new WorkspaceAccess();
        $workspaceId = $access->pageWorkspaceId($pageId);

        if ($workspaceId === null) {
            return $this->error('La página no existe.', 404);
        }

        if (! $access->canEdit($workspaceId, (int) auth()->id())) {
            return $this->error('Tu rol en este espacio es de solo lectura.', 403);
        }

        db_connect()
            ->table('page_tags')
            ->where('page_id', $pageId)
            ->where('tag_id', $tagId)
            ->delete();

        return $this->ok(['page_id' => $pageId, 'tag_id' => $tagId]);
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
