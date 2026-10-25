<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Web
$routes->get('/', 'Home::index');

// Tech Radar: exige sesión; su controlador y vista pertenecen a la fase de
// eventos del backend. Mientras no exista, no publicamos una ruta rota.
if (class_exists('App\\Controllers\\Web\\EventViewController')) {
    $routes->get('events', 'Web\\EventViewController::index', ['filter' => 'session']);
}

// Auth (Shield)
service('auth')->routes($routes);

// Aplicación web autenticada
$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Web\DashboardController::index');
    $routes->get('workspaces', 'Web\WorkspaceController::index');
    $routes->get('workspaces/(:num)', 'Web\WorkspaceController::show/$1');
    $routes->get('workspaces/(:num)/kanban', 'Web\KanbanController::index/$1');
    $routes->get('workspaces/(:num)/calendar', 'Web\CalendarController::index/$1');
    $routes->get('pages/nueva', 'Web\PageController::new');
    $routes->get('pages/(:num)', 'Web\PageController::show/$1');
    $routes->get('settings', 'Web\SettingsController::index');
});

/*
 * Rutas de previsualización.
 *
 * Existen solo en desarrollo/pruebas para revisar la interfaz sin depender de
 * MariaDB ni de Shield. En producción no se registran y, además, pueden
 * desactivarse con NEXO_PREVIEW=false (útil en staging).
 */
$previewEnabled = filter_var(env('NEXO_PREVIEW', ENVIRONMENT === 'development'), FILTER_VALIDATE_BOOLEAN);

if (ENVIRONMENT !== 'production' && $previewEnabled) {
    $routes->get('preview', 'Web\DashboardController::index');
    $routes->get('preview/dashboard', 'Web\DashboardController::index');
    $routes->get('preview/workspaces', 'Web\WorkspaceController::index');
    $routes->get('preview/workspaces/(:num)', 'Web\WorkspaceController::show/$1');
    $routes->get('preview/workspaces/(:num)/kanban', 'Web\KanbanController::index/$1');
    $routes->get('preview/workspaces/(:num)/calendar', 'Web\CalendarController::index/$1');
    $routes->get('preview/pages/nueva', 'Web\PageController::new');
    $routes->get('preview/pages/(:num)', 'Web\PageController::show/$1');
    $routes->get('preview/settings', 'Web\SettingsController::index');
    $routes->get('preview/login', 'Web\PreviewController::login');
    $routes->get('preview/register', 'Web\PreviewController::register');
    $routes->get('preview/ui-kit', 'Web\PreviewController::uiKit');
    $routes->get('preview/events', 'Web\PreviewController::events');
}

// API v1

// Ingest de eventos: llega de fuera del navegador, valida X-API-KEY y va sin CSRF.
// El controlador pertenece a la fase de eventos del backend; no se publica la
// ruta mientras no exista para no devolver un 500 "Controller not found".
if (class_exists(\App\Controllers\Api\V1\EventIngestController::class)) {
    $routes->post('api/v1/events/ingest', 'Api\V1\EventIngestController::store', [
        'filter' => ['apikey', 'jsonpayload'],
    ]);
}

$routes->group('api/v1', [
    'namespace' => 'App\Controllers\Api\V1',
    'filter'    => ['apiauth', 'jsonpayload'],
], static function (RouteCollection $routes): void {
    $routes->get('me', 'MeController::show');

    // Recursos del backend (B). Se publican cuando el controlador existe.
    if (class_exists(\App\Controllers\Api\V1\WorkspaceController::class)) {
        $routes->get('workspaces', 'WorkspaceController::index');
        $routes->get('workspaces/(:num)', 'WorkspaceController::show/$1');
        $routes->get('workspaces/(:num)/tasks', 'WorkspaceController::tasks/$1');
    }

    if (class_exists(\App\Controllers\Api\V1\PageController::class)) {
        $routes->post('pages', 'PageController::create');
        $routes->get('pages/(:num)', 'PageController::show/$1');
        $routes->put('pages/(:num)', 'PageController::update/$1');
    }

    if (class_exists(\App\Controllers\Api\V1\TaskController::class)) {
        $routes->post('tasks', 'TaskController::create');
        $routes->put('tasks/(:num)/move', 'TaskController::move/$1');
        $routes->put('tasks/(:num)', 'TaskController::update/$1');
        $routes->delete('tasks/(:num)', 'TaskController::delete/$1');
    }

    if (class_exists(\App\Controllers\Api\V1\CommentController::class)) {
        $routes->get('comments', 'CommentController::index');
        $routes->post('comments', 'CommentController::create');
        $routes->delete('comments/(:num)', 'CommentController::delete/$1');
    }

    if (class_exists(\App\Controllers\Api\V1\TagController::class)) {
        $routes->get('tags', 'TagController::index');
        $routes->post('tags', 'TagController::attach');
        $routes->delete('tags/(:num)', 'TagController::detach/$1');
    }

    if (class_exists(\App\Controllers\Api\V1\AttachmentController::class)) {
        $routes->post('attachments', 'AttachmentController::create');
        $routes->get('attachments/(:num)', 'AttachmentController::file/$1');
    }

    // Contrato reservado del chat y documentos (A). Se publican cuando el
    // controlador de la fase IA exista; mientras tanto la ruta no se registra.
    if (class_exists(\App\Controllers\Api\V1\AIChatController::class)) {
        $routes->post('chat', 'AIChatController::send');
    }

    if (class_exists(\App\Controllers\Api\V1\DocumentController::class)) {
        $routes->get('documents', 'DocumentController::index');
        $routes->post('documents', 'DocumentController::create');
    }
});
