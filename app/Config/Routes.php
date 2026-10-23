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
 * MariaDB ni de Shield. En producción no se registran.
 */
if (ENVIRONMENT !== 'production') {
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
$routes->post('api/v1/events/ingest', 'Api\V1\EventIngestController::store', [
    'filter' => ['apikey', 'jsonpayload'],
]);

$routes->group('api/v1', [
    'namespace' => 'App\Controllers\Api\V1',
    'filter'    => ['apiauth', 'jsonpayload'],
], static function (RouteCollection $routes): void {
    $routes->get('me', 'MeController::show');

    // Recursos (workspaces, pages, blocks, tasks, events) se agregan en cada fase.

    // Contrato reservado del chat: responde 404 hasta que su controlador exista.
    $routes->post('chat', 'AIChatController::send');
});
