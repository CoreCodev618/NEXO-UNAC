<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;

class DashboardController extends BaseWebController
{
    public function index(): string
    {
        $dashboard = $this->isPreview()
            ? FrontendDemo::dashboard()
            : $this->repository()->dashboard((int) auth()->id());

        return $this->render('dashboard/index', [
            'pageTitle'   => 'Mi mesa',
            'topbarTitle' => 'Mi mesa',
            'dashboard'   => $dashboard,
            'activeNav'   => 'dashboard',
        ]);
    }
}
