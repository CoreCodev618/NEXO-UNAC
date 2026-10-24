<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;
use CodeIgniter\Exceptions\PageNotFoundException;

class WorkspaceController extends BaseWebController
{
    public function index(): string
    {
        $workspaces = [];

        foreach (FrontendDemo::workspaces() as $workspace) {
            $hydrated = FrontendDemo::workspace((int) $workspace['id']);

            if ($hydrated !== null) {
                $workspaces[] = $hydrated;
            }
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
        $workspace = FrontendDemo::workspace((int) $id);

        if ($workspace === null) {
            throw PageNotFoundException::forPageNotFound('No encontramos ese espacio.');
        }

        $this->setWorkspace($workspace);

        $pages      = FrontendDemo::pages((int) $workspace['id']);
        $flatPages  = $this->flatten($pages);
        $tasks      = FrontendDemo::tasks((int) $workspace['id']);
        $openTasks  = array_values(array_filter($tasks, static fn (array $task): bool => $task['status'] !== 'done'));

        return $this->render('workspaces/show', [
            'pageTitle'    => $workspace['name'],
            'topbarTitle'  => $workspace['name'],
            'activeNav'    => 'workspaces',
            'workspace'    => $workspace,
            'pages'        => $pages,
            'flatPages'    => $flatPages,
            'tasks'        => $openTasks,
            'task_counts'  => [
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
