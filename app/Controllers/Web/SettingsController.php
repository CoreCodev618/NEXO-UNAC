<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;
use CodeIgniter\Exceptions\PageNotFoundException;

class SettingsController extends BaseWebController
{
    public function index(): string
    {
        $tab = (string) ($this->request->getGet('tab') ?? 'profile');
        $tab = in_array($tab, ['profile', 'workspace', 'about'], true) ? $tab : 'profile';

        $workspace = $this->isPreview()
            ? FrontendDemo::workspace(1)
            : ($this->repository()->workspacesForUser((int) auth()->id())[0] ?? null);

        if ($workspace === null) {
            throw PageNotFoundException::forPageNotFound('No tienes espacios disponibles.');
        }

        return $this->render('settings/index', [
            'pageTitle'   => 'Ajustes',
            'topbarTitle' => 'Ajustes',
            'activeNav'   => 'settings',
            'tab'         => $tab,
            'workspace'   => $workspace,
            'baseUrl'     => nexo_url($this->prefix(), 'settings'),
        ]);
    }
}
