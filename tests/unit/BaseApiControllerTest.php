<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\V1\BaseApiController;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Services;

/**
 * @internal
 */
final class BaseApiControllerTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setPost([]);
    }

    protected function tearDown(): void
    {
        $this->setPost([]);
        parent::tearDown();
    }

    /**
     * Los tests comparten el servicio superglobals: hay que escribir ahí,
     * no solo en $_POST, para que el request lea los datos de esta prueba.
     */
    private function setPost(array $post): void
    {
        service('superglobals')->setPostArray($post);
    }

    private function controller(?string $body, string $contentType = 'application/json'): BaseApiController
    {
        $config    = new App();
        $uri       = Services::uri();
        $userAgent = new UserAgent();
        $request   = new IncomingRequest($config, $uri, $body ?? 'php://input', $userAgent);

        if ($contentType !== '') {
            $request->setHeader('Content-Type', $contentType);
        }

        $controller = new class extends BaseApiController {
            public function payloadPublic(): array
            {
                return $this->payload();
            }

            public function errorPublic(string $message, int $status, ?array $errors = null): mixed
            {
                return $this->error($message, $status, $errors);
            }

            public function okPublic(mixed $data, int $status = 200, ?array $meta = null): mixed
            {
                return $this->ok($data, $status, $meta);
            }
        };

        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    public function testPayloadDecodificaJsonValido(): void
    {
        $controller = $this->controller('{"title":"Nota","order":3}');

        $this->assertSame(['title' => 'Nota', 'order' => 3], $controller->payloadPublic());
    }

    public function testPayloadJsonVacioDevuelveArrayVacio(): void
    {
        $controller = $this->controller('');

        $this->assertSame([], $controller->payloadPublic());
    }

    public function testPayloadJsonMalformadoLanza400(): void
    {
        $controller = $this->controller('{"title":');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionCode(400);

        $controller->payloadPublic();
    }

    public function testPayloadSinContentTypeJsonUsaPost(): void
    {
        $this->setPost(['a' => '1', 'b' => '2']);

        $controller = $this->controller(null, 'application/x-www-form-urlencoded');

        $this->assertSame(['a' => '1', 'b' => '2'], $controller->payloadPublic());
    }

    public function testOkRespondeFormaEstandar(): void
    {
        $response = $this->controller('')->okPublic(['id' => 1]);

        $body = json_decode($response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame(['id' => 1], $body['data']);
        $this->assertNull($body['message']);
        $this->assertNull($body['errors']);
        $this->assertArrayNotHasKey('meta', $body);
    }

    public function testOkIncluyeMetaCuandoSePasa(): void
    {
        $response = $this->controller('')
            ->okPublic([[ 'id' => 1 ]], 200, ['page' => 1, 'perPage' => 20, 'total' => 57]);

        $body = json_decode($response->getBody(), true);

        $this->assertSame(['page' => 1, 'perPage' => 20, 'total' => 57], $body['meta']);
    }

    public function testErrorRespondeFormaEstandar(): void
    {
        $response = $this->controller('')
            ->errorPublic('Validación fallida', 422, ['title' => 'El campo title es obligatorio.']);

        $body = json_decode($response->getBody(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertNull($body['data']);
        $this->assertSame('Validación fallida', $body['message']);
        $this->assertSame(['title' => 'El campo title es obligatorio.'], $body['errors']);
    }

    public function testErrorSinErrorsDetailaNull(): void
    {
        $response = $this->controller('')->errorPublic('No encontrado', 404);

        $body = json_decode($response->getBody(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertNull($body['errors']);
    }
}
