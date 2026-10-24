<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;
use CodeIgniter\Exceptions\PageNotFoundException;

class WorkspaceController extends BaseWebController
{
    public function index(): string
    {
        if ($this->isPreview()) {
            $workspaces = [];
            foreach (FrontendDemo::workspaces() as $workspace) {
                $hydrated = FrontendDemo::workspace((int) $workspace['id']);
                if ($hydrated !== null) {
                    $workspaces[] = $hydrated;
                }
            }
        } else {
            $workspaces = $this->repository()->workspacesForUser((int) auth()->id());
        }

        return $this->render('workspaces/index', [
            'pageTitle'   => 'Espacios',
            'topbarTitle' => 'Espacios',
            'activeNav'   => 'workspaces',
            'workspaces'  => $workspaces,
        ]);
    }

    public function show(string $id): string
    {
        $workspaceId = (int) $id;

        if ($this->isPreview()) {
            $workspace = FrontendDemo::workspace($workspaceId);
            $pages     = $workspace !== null ? FrontendDemo::pages($workspaceId) : [];
            $tasks     = FrontendDemo::tasks($workspaceId);
        } else {
            $workspace = $this->repository()->workspace($workspaceId, (int) auth()->id());
            $pages     = $workspace !== null ? $this->repository()->pages($workspaceId) : [];
            $tasks     = $workspace !== null ? $this->repository()->tasks($workspaceId) : [];
        }

        if ($workspace === null) {
            throw PageNotFoundException::forPageNotFound('No encontramos ese espacio.');
        }

        $this->setWorkspace($workspace);

        $flatPages = $this->flatten($pages);
        $openTasks = array_values(array_filter($tasks, static fn (array $task): bool => $task['status'] !== 'done'));

        return $this->render('workspaces/show', [
            'pageTitle'   => $workspace['name'],
            'topbarTitle' => $workspace['name'],
            'activeNav'   => 'workspaces',
            'workspace'   => $workspace,
            'pages'       => $pages,
            'flatPages'   => $flatPages,
            'tasks'       => $openTasks,
            'task_counts' => [
                'todo'  => count(array_filter($tasks, static fn (array $task): bool => $task['status'] === 'todo')),
                'doing' => count(array_filter($tasks, static fn (array $task): bool => $task['status'] === 'doing')),
                'done'  => count(array_filter($tasks, static fn (array $task): bool => $task['status'] === 'done')),
            ],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $pages
     *
     * @return list<array<string, mixed>>
     */
    private function flatten(array $pages, int $depth = 0): array
    {
        $flat = [];

        foreach ($pages as $page) {
            $children = $page['children'] ?? [];
            unset($page['children']);
            $page['depth'] = $depth;
            $page['has_children'] = $children !== [];
            $flat[] = $page;

            if ($children !== []) {
                $flat = [...$flat, ...$this->flatten($children, $depth + 1)];
            }
        }

        return $flat;
    }
}
