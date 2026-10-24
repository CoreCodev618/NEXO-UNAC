<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserModel;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class FiltersTest extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = null;
    protected $refresh   = true;

    private const API_KEY   = 'clave-secreta-de-prueba';
    private const DUMMY_API = 'api/v1/_dummy';

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['EVENT_INGEST_API_KEY'] = self::API_KEY;

        $this->withRoutes([
            ['GET', self::DUMMY_API, static fn () => 'ok', ['filter' => 'apiauth']],
            ['GET', 'api/v1/_ingest-dummy', static fn () => 'ok', ['filter' => 'apikey']],
            ['POST', 'api/v1/events/ingest', static fn () => 'ok', ['filter' => 'apikey']],
            ['POST', 'api/v1/_csrf-dummy', static fn () => 'ok'],
        ]);
    }

    protected function tearDown(): void
    {
        unset($_ENV['EVENT_INGEST_API_KEY']);

        parent::tearDown();
    }

    public function testApiSinSesionResponde401Json(): void
    {
        $result = $this->get(self::DUMMY_API);

        $result->assertStatus(401);

        $body = json_decode($result->getJSON(), true);

        $this->assertFalse($body['success']);
        $this->assertNull($body['data']);
        $this->assertIsString($body['message']);
        $this->assertNull($body['errors']);
    }

    public function testApiConSesionResponde200(): void
    {
        $user = fake(UserModel::class, ['name' => 'Ana']);

        $this->actingAs($user)
            ->get(self::DUMMY_API)
            ->assertStatus(200);
    }

    public function testIngestSinApiKeyResponde401(): void
    {
        $this->get('api/v1/_ingest-dummy')->assertStatus(401);
    }

    public function testIngestConApiKeyIncorrectaResponde401(): void
    {
        $this->withHeaders(['X-API-KEY' => 'clave-mala'])
            ->get('api/v1/_ingest-dummy')
            ->assertStatus(401);
    }

    public function testIngestConApiKeyCorrectaResponde200(): void
    {
        $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->get('api/v1/_ingest-dummy')
            ->assertStatus(200);
    }

    public function testIngestConKeyVaciaEnEnvResponde401(): void
    {
        $_ENV['EVENT_INGEST_API_KEY'] = '';

        $this->withHeaders(['X-API-KEY' => ''])
            ->get('api/v1/_ingest-dummy')
            ->assertStatus(401);
    }

    public function testPostSinTokenCsrfResponde403(): void
    {
        // En entorno testing Security::$redirect=false, así que el filtro
        // CSRF re-lanza la excepción con código HTTP 403.
        $this->expectException(SecurityException::class);
        $this->expectExceptionCode(403);

        $this->post('api/v1/_csrf-dummy');
    }

    public function testRutaIngestEstaExentaDeCsrf(): void
    {
        // POST sin token CSRF a la ruta de ingest: si el except no funcionara,
        // el filtro csrf la bloquearía antes que apikey.
        $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->post('api/v1/events/ingest', [])
            ->assertStatus(200);
    }
}
