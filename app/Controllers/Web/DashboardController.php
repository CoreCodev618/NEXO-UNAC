<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Libraries\FrontendDemo;

class DashboardController extends BaseWebController
{
    public function index(): string
    {
        $dashboard = FrontendDemo::dashboard();

        return $this->render('dashboard/index', [
            'pageTitle'   => 'Mi mesa',
            'topbarTitle' => 'Mi mesa',
            'dashboard'   => $dashboard,
            'activeNav'   => 'dashboard',
        ]);
    }
}
