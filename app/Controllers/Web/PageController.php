<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;
use CodeIgniter\Exceptions\PageNotFoundException;

class PageController extends BaseWebController
{
    public function show(string $id): string
    {
        $page = FrontendDemo::page((int) $id);

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
        $page        = FrontendDemo::newPage($workspaceId);

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
