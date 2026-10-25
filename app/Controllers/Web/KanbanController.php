<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;
use CodeIgniter\Exceptions\PageNotFoundException;

class KanbanController extends BaseWebController
{
    public function index(string $workspaceId): string
    {
        $id = (int) $workspaceId;

        if ($this->isPreview()) {
            $workspace = FrontendDemo::workspace($id);
            $tasks     = FrontendDemo::tasks($id);
        } else {
            $workspace = $this->repository()->workspace($id, (int) auth()->id());
            $tasks     = $workspace !== null ? $this->repository()->tasks($id) : [];
        }

        if ($workspace === null) {
            throw PageNotFoundException::forPageNotFound('No encontramos ese espacio.');
        }

        $this->setWorkspace($workspace);

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
