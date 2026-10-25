<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Libraries\NexoRepository;
use CodeIgniter\HTTP\ResponseInterface;

final class WorkspaceController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->ok(
            (new NexoRepository())->workspacesForUser((int) auth()->id())
        );
    }

    public function show(string $id): ResponseInterface
    {
        $repo = new NexoRepository();
        $workspace = $repo->workspace((int) $id, (int) auth()->id());

        if ($workspace === null) {
            return $this->error('Espacio no encontrado.', 404);
        }

        $workspace['pages'] = $repo->pages((int) $id);

        return $this->ok($workspace);
    }

    public function tasks(string $id): ResponseInterface
    {
        if ($guard = $this->guardWorkspace((int) $id)) {
            return $guard;
        }

        return $this->ok(
            (new NexoRepository())->tasks((int) $id)
        );
    }
}
