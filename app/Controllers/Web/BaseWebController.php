<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Libraries\FrontendDemo;

/**
 * Base de los controladores web del frontend.
 *
 * Mantiene una sola fuente de verdad para el shell (usuario, espacios,
 * navegación, datos de demo) y permite reemplazar FrontendDemo por servicios
 * de API sin tocar cada vista.
 */
abstract class BaseWebController extends BaseController
{
    protected ?array $currentWorkspace = null;

    protected ?array $currentPage = null;

    protected function isPreview(): bool
    {
        return str_starts_with(trim($this->request->getPath(), '/'), 'preview');
    }

    protected function prefix(): string
    {
        return $this->isPreview() ? '/preview' : '';
    }

    protected function currentUser(): array
    {
        if ($this->isPreview()) {
            return FrontendDemo::user();
        }

        $user = auth()->user();

        if ($user === null) {
            if (ENVIRONMENT !== 'production') {
                return FrontendDemo::user();
            }

            throw new \RuntimeException('No hay un usuario autenticado en una ruta web protegida.');
        }

        $groups = $user->getGroups();
        $role   = in_array('admin', $groups, true)
            ? 'admin'
            : (in_array('docente', $groups, true) ? 'docente' : 'alumno');

        $name = trim((string) ($user->name ?? $user->username ?? 'Usuario'));

        return [
            'id'         => (int) $user->id,
            'name'       => $name !== '' ? $name : 'Usuario',
            'username'   => (string) ($user->username ?? ''),
            'email'      => (string) ($user->email ?? ''),
            'faculty'    => 'FIIS',
            'role'       => $role,
            'role_label' => match ($role) {
                'admin'  => 'Administrador',
                'docente' => 'Docente',
                default  => 'Estudiante',
            },
            'groups'     => $groups,
            'initials'   => nexo_initials($name),
            'tone'       => match ($role) {
                'admin'  => 'wine',
                'docente' => 'gold',
                default  => 'sage',
            },
            'is_demo'    => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function commonData(): array
    {
        $workspaces = [];

        foreach (FrontendDemo::workspaces() as $workspace) {
            $hydrated = FrontendDemo::workspace((int) $workspace['id']);

            if ($hydrated !== null) {
                $workspaces[] = $hydrated;
            }
        }

        $sidebarWorkspace = $this->currentWorkspace ?? $workspaces[0] ?? null;
        $sidebarPages     = $sidebarWorkspace !== null
            ? FrontendDemo::pages((int) $sidebarWorkspace['id'])
            : [];

        $tasks = FrontendDemo::tasks();
        $taskCounts = [];

        foreach ($tasks as $task) {
            if ($task['status'] === 'done') {
                continue;
            }

            $workspaceId = (int) $task['workspace_id'];
            $taskCounts[$workspaceId] = ($taskCounts[$workspaceId] ?? 0) + 1;
        }

        $prefix      = $this->prefix();
        $currentPath = '/' . trim($this->request->getPath(), '/');

        $topbarTitle = $this->currentPage['title']
            ?? $this->currentWorkspace['name']
            ?? 'Mi mesa';

        $eventsAvailable = $this->isPreview() || class_exists('App\\Controllers\\Web\\EventViewController');

        return [
            'user'              => $this->currentUser(),
            'workspaces'        => $workspaces,
            'currentWorkspace'  => $this->currentWorkspace,
            'currentPage'       => $this->currentPage,
            'sidebarWorkspace'  => $sidebarWorkspace,
            'sidebarPages'      => $sidebarPages,
            'taskCounts'        => $taskCounts,
            'members'           => FrontendDemo::members(),
            'prefix'            => $prefix,
            'isPreview'         => $this->isPreview(),
            'currentPath'       => $currentPath,
            'topbarTitle'       => $topbarTitle,
            'palette'           => FrontendDemo::palette($prefix, (int) ($sidebarWorkspace['id'] ?? 1)),
            'eventsUrl'         => $eventsAvailable ? nexo_url($prefix, 'events') : null,
            'pageTitle'         => 'NEXO UNAC',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        $data = array_merge($this->commonData(), $data);
        $data['content'] = view($view, $data);

        return view('layouts/app', $data);
    }

    /**
     * @param array<string, mixed> $workspace
     */
    protected function setWorkspace(array $workspace): void
    {
        $this->currentWorkspace = $workspace;
    }

    /**
     * @param array<string, mixed> $page
     */
    protected function setPage(array $page): void
    {
        $this->currentPage = $page;
    }
}
