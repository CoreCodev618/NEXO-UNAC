<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\PageModel;

/**
 * Única fuente de verdad para autorización por espacio.
 *
 * Roles (de menor a mayor): viewer < editor < owner.
 * - viewer: puede leer páginas, descargar archivos y comentar.
 * - editor: además crea/edita páginas, tareas y adjuntos.
 * - owner: además administra miembros y el espacio.
 *
 * La pertenencia se guarda en `workspace_members`. No se usa el grupo global
 * de Shield para saltarse la pertenencia: un admin sin membresía no ve el
 * espacio. Así una cuenta globalmente privilegiada no filtra contenido ajeno.
 */
final class WorkspaceAccess
{
    public const ROLE_VIEWER = 'viewer';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_OWNER = 'owner';

    private const RANK = [
        self::ROLE_VIEWER => 1,
        self::ROLE_EDITOR => 2,
        self::ROLE_OWNER  => 3,
    ];

    /** @var array<string, string|null> */
    private array $roles = [];

    /** @var array<int, int|null> */
    private array $pageWorkspaces = [];

    public function role(int $workspaceId, int $userId): ?string
    {
        if ($workspaceId <= 0 || $userId <= 0) {
            return null;
        }

        $key = $workspaceId . ':' . $userId;

        if (array_key_exists($key, $this->roles)) {
            return $this->roles[$key];
        }

        $row = db_connect()
            ->table('workspace_members')
            ->select('role')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        $role = isset($row['role']) && is_string($row['role']) && $row['role'] !== ''
            ? $row['role']
            : null;

        return $this->roles[$key] = $role;
    }

    public function canView(int $workspaceId, int $userId): bool
    {
        return $this->role($workspaceId, $userId) !== null;
    }

    public function canEdit(int $workspaceId, int $userId): bool
    {
        return $this->atLeast($workspaceId, $userId, self::ROLE_EDITOR);
    }

    public function canManage(int $workspaceId, int $userId): bool
    {
        return $this->atLeast($workspaceId, $userId, self::ROLE_OWNER);
    }

    public function atLeast(int $workspaceId, int $userId, string $minimum): bool
    {
        $role = $this->role($workspaceId, $userId);

        return $role !== null
            && (self::RANK[$role] ?? 0) >= (self::RANK[$minimum] ?? PHP_INT_MAX);
    }

    /**
     * Espacios visibles para el usuario. Se usa para acotar RAG y listados.
     *
     * @return list<int>
     */
    public function workspaceIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = db_connect()
            ->table('workspace_members')
            ->select('workspace_id')
            ->where('user_id', $userId)
            ->orderBy('workspace_id', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['workspace_id'],
            $rows
        )));
    }

    public function page(int $pageId): ?array
    {
        if ($pageId <= 0) {
            return null;
        }

        $page = model(PageModel::class)->find($pageId);

        return $page !== null ? $page : null;
    }

    public function pageWorkspaceId(int $pageId): ?int
    {
        if ($pageId <= 0) {
            return null;
        }

        if (array_key_exists($pageId, $this->pageWorkspaces)) {
            return $this->pageWorkspaces[$pageId];
        }

        $page = $this->page($pageId);

        return $this->pageWorkspaces[$pageId] = $page !== null
            ? (int) $page['workspace_id']
            : null;
    }

    public function pageRole(int $pageId, int $userId): ?string
    {
        $workspaceId = $this->pageWorkspaceId($pageId);

        return $workspaceId !== null ? $this->role($workspaceId, $userId) : null;
    }

    public function canViewPage(int $pageId, int $userId): bool
    {
        return $this->pageRole($pageId, $userId) !== null;
    }

    public function canEditPage(int $pageId, int $userId): bool
    {
        $workspaceId = $this->pageWorkspaceId($pageId);

        return $workspaceId !== null && $this->canEdit($workspaceId, $userId);
    }
}
