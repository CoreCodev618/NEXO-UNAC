<?php

use App\Filters\CsrfTokenHeader;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Security;

/**
 * @internal
 */
final class CsrfTokenHeaderTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        Services::resetSingle('security');
        unset($_SESSION[config(Security::class)->tokenName]);

        parent::tearDown();
    }

    public function testAgregaElTokenRotadoEnPost(): void
    {
        $security = config(Security::class);

        $_SESSION[$security->tokenName] = 'token-rotado';
        Services::resetSingle('security');

        $response = new Response(new App());
        $this->filter()->after($this->request('POST'), $response);

        $this->assertSame('token-rotado', $response->getHeaderLine('X-CSRF-TOKEN'));
    }

    public function testNoAgregaHeaderEnGet(): void
    {
        $response = new Response(new App());
        $this->filter()->after($this->request('GET'), $response);

        $this->assertSame('', $response->getHeaderLine('X-CSRF-TOKEN'));
    }

    private function filter(): CsrfTokenHeader
    {
        return new CsrfTokenHeader();
    }

    private function request(string $method): IncomingRequest
    {
        $request = new IncomingRequest(new App(), new URI('http://localhost/login'), null, new UserAgent());
        $request->setMethod($method);

        return $request;
    }
}
