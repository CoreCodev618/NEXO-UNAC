<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;

class SettingsController extends BaseWebController
{
    public function index(): string
    {
        $tab = (string) ($this->request->getGet('tab') ?? 'profile');
        $tab = in_array($tab, ['profile', 'workspace', 'about'], true) ? $tab : 'profile';

        $workspace = FrontendDemo::workspace(1);

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
