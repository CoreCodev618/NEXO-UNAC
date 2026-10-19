<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Web
$routes->get('/', 'Home::index');

// Tech Radar: exige sesión; su vista llega en la fase de eventos.
$routes->get('events', 'Web\EventViewController::index', ['filter' => 'session']);

// Auth (Shield)
service('auth')->routes($routes);

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
