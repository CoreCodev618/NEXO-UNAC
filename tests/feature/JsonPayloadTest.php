<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * jsonpayload: en /api/v1 un body JSON malformado responde 400 con el
 * envelope del contrato, no con el formato de errores del framework.
 */
final class JsonPayloadTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = null;

    /** @var list<string> */
    private array $csrfGlobal;

    protected function setUp(): void
    {
        parent::setUp();

        $filters           = config('Filters');
        $this->csrfGlobal  = $filters->globals['before'];
        $filters->globals['before'] = [];
        Factories::injectMock('config', 'Filters', $filters);

        $this->withRoutes([
            ['POST', 'api/v1/_json', static fn () => 'ok', ['filter' => 'jsonpayload']],
            ['POST', 'api/v1/_json-auth', static fn () => 'ok', ['filter' => ['apiauth', 'jsonpayload']]],
        ]);
    }

    protected function tearDown(): void
    {
        config('Filters')->globals['before'] = $this->csrfGlobal;

        parent::tearDown();
    }

    public function testJsonMalformadoResponde400ConEnvelope(): void
    {
        $result = $this->withBody('{"title":')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/_json');

        $result->assertStatus(400);
        $result->assertJSONPath('success', false);
        $result->assertJSONPath('data', null);
        $result->assertJSONPath('message', 'JSON malformado.');
        $result->assertJSONPath('errors', null);
    }

    public function testJsonValidoPasaAlControlador(): void
    {
        $result = $this->withBody('{"title":"Nota"}')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/_json');

        $result->assertStatus(200);
        $this->assertSame('ok', (string) $result->response()->getBody());
    }

    public function testBodySinContentTypeJsonNoSeToca(): void
    {
        $this->withBodyFormat('form')
            ->post('api/v1/_json', ['title' => 'Nota'])
            ->assertStatus(200);
    }

    public function testLaSesionSeExigeAntesQueElJson(): void
    {
        auth('session')->logout();

        $result = $this->withBody('{"title":')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('api/v1/_json-auth');

        $result->assertStatus(401);
        $result->assertJSONPath('success', false);
    }
}
