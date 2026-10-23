<?php

declare(strict_types=1);

namespace App\Controllers\Web;

class PreviewController extends BaseWebController
{
    public function login(): string
    {
        return view('auth/login', [
            'config'     => config('Auth'),
            'prefix'     => '/preview',
            'isPreview'  => true,
            'pageTitle'  => 'Entrar',
        ]);
    }

    public function register(): string
    {
        return view('auth/register', [
            'config'     => config('Auth'),
            'prefix'     => '/preview',
            'isPreview'  => true,
            'pageTitle'  => 'Crear cuenta',
        ]);
    }

    public function uiKit(): string
    {
        return $this->render('dev/ui_kit', [
            'pageTitle'   => 'UI kit',
            'topbarTitle' => 'UI kit',
            'activeNav'   => 'ui-kit',
        ]);
    }

    public function events(): string
    {
        return $this->render('preview/events', [
            'pageTitle'   => 'Radar',
            'topbarTitle' => 'Radar',
            'activeNav'   => 'events',
        ]);
    }
}
