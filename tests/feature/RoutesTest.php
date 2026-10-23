<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Shield\Filters\SessionAuth;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Cableado de app/Config/Routes.php: rutas web, ingest por API key
 * y que la reorganización de rutas no rompe Home ni Shield.
 */
final class RoutesTest extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = null;
    protected $refresh   = true;

    private const INGEST = 'api/v1/events/ingest';

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['EVENT_INGEST_API_KEY'] = 'clave-real-de-prueba';
        auth('session')->logout();
    }

    protected function tearDown(): void
    {
        unset($_ENV['EVENT_INGEST_API_KEY']);

        parent::tearDown();
    }

    public function testEventosNoSePublicaSinControlador(): void
    {
        // El módulo Radar pertenece al backend; la ruta solo existe cuando su
        // controlador está instalado. Sin él, la URL debe ser un 404 honesto.
        $this->get('events')->assertStatus(404);
    }

    /**
     * El alias 'session' que usa /events viene de Shield (su Registrar se
     * fusiona al config por auto-descubrimiento); se declara explícito en
     * app/Config/Filters.php para no depender de ese descubrimiento.
     */
    public function testAliasSessionEstaRegistrado(): void
    {
        $this->assertSame(SessionAuth::class, config('Filters')->aliases['session']);
    }

    public function testIngestSinApiKeyResponde401(): void
    {
        $this->withBodyFormat('json')
            ->post(self::INGEST, [])
            ->assertStatus(401);
    }

    public function testIngestConApiKeyIncorrectaResponde401(): void
    {
        $this->withHeaders(['X-API-KEY' => 'clave-equivocada'])
            ->withBodyFormat('json')
            ->post(self::INGEST, [])
            ->assertStatus(401);
    }

    public function testEnIngestLaApiKeySeExigeAntesQueElJson(): void
    {
        $this->withBody('{"title":')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(self::INGEST)
            ->assertStatus(401);
    }

    public function testRaizRedirigeAlLoginSinSesion(): void
    {
        $this->get('/')->assertRedirectTo('login');
    }

    public function testLoginDeShieldResponde200(): void
    {
        $this->get('login')->assertStatus(200);
    }
}
