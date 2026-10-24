<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;
use CodeIgniter\Exceptions\PageNotFoundException;

class KanbanController extends BaseWebController
{
    public function index(string $workspaceId): string
    {
        $workspace = FrontendDemo::workspace((int) $workspaceId);

        if ($workspace === null) {
            throw PageNotFoundException::forPageNotFound('No encontramos ese espacio.');
        }

        $this->setWorkspace($workspace);

        $tasks = FrontendDemo::tasks((int) $workspace['id']);

        return $this->render('kanban/index', [
            'pageTitle'   => 'Tablero · ' . $workspace['name'],
            'topbarTitle' => 'Tablero',
            'activeNav'   => 'workspaces',
            'workspace'   => $workspace,
            'tasks'       => $tasks,
            'columns'     => [
                'todo'  => 'Por hacer',
                'doing' => 'En curso',
                'done'  => 'Hecho',
            ],
        ]);
    }
}
