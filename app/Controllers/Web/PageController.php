<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;
use CodeIgniter\Exceptions\PageNotFoundException;

class PageController extends BaseWebController
{
    public function show(string $id): string
    {
        $pageId = (int) $id;

        if ($this->isPreview()) {
            $page = FrontendDemo::page($pageId);
        } else {
            $page = $this->repository()->page($pageId, (int) auth()->id());
        }

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound('No encontramos esa página.');
        }

        $this->setWorkspace($page['workspace']);
        $this->setPage($page);

        return $this->render('pages/show', [
            'pageTitle'   => $page['title'],
            'topbarTitle' => $page['title'],
            'activeNav'   => 'workspaces',
            'page'        => $page,
            'isNew'       => false,
        ]);
    }

    public function new(): string
    {
        $workspaceId = (int) ($this->request->getGet('workspace') ?? 1);

        if ($this->isPreview()) {
            $page = FrontendDemo::newPage($workspaceId);
        } else {
            $userId = (int) auth()->id();
            $workspace = $this->repository()->workspace($workspaceId, $userId);

            if ($workspace === null) {
                $first = $this->repository()->workspacesForUser($userId)[0] ?? null;
                if ($first === null) {
                    throw PageNotFoundException::forPageNotFound('No tienes espacios disponibles.');
                }
                $workspaceId = (int) $first['id'];
                $workspace   = $first;
            }

            if (empty($workspace['can_edit'])) {
                throw PageNotFoundException::forPageNotFound('No tienes permiso para crear páginas en este espacio.');
            }

            $page = $this->repository()->newPage($workspaceId, $userId);
        }

        $this->setWorkspace($page['workspace']);
        $this->setPage($page);

        return $this->render('pages/show', [
            'pageTitle'   => 'Nueva página',
            'topbarTitle' => 'Nueva página',
            'activeNav'   => 'workspaces',
            'page'        => $page,
            'isNew'       => true,
        ]);
    }
}
