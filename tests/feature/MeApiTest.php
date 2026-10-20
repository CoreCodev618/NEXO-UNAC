<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserModel;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

require_once __DIR__ . '/../_support/Helpers/user_helper.php';

final class MeApiTest extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = null;
    protected $refresh   = true;
    protected $seed      = 'App\Database\Seeds\FacultySeeder';

    public function testRequiereSesion(): void
    {
        // actingAs de tests anteriores deja sesión; forzar estado anónimo.
        auth('session')->logout();

        $this->get('api/v1/me')->assertStatus(401);
    }

    public function testDevuelveDatosDelUsuarioAutenticado(): void
    {
        $user = testUserWithEmail(['name' => 'Ana Torres', 'faculty_id' => 1]);
        $user->addGroup('alumno');

        $result = $this->actingAs($user)->get('api/v1/me');

        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);

        $this->assertTrue($body['success']);
        $this->assertSame($user->id, $body['data']['id']);
        $this->assertSame('Ana Torres', $body['data']['name']);
        $this->assertSame(1, $body['data']['faculty_id']);
        $this->assertContains('alumno', $body['data']['groups']);
        $this->assertNotEmpty($body['data']['email']);
    }

    public function testNoExponePasswordNiSecretos(): void
    {
        $user = fake(UserModel::class, ['name' => 'Beto']);
        $user->addGroup('docente');

        $result = $this->actingAs($user)->get('api/v1/me');

        $result->assertStatus(200);

        $raw = (string) $result->getBody();

        $this->assertStringNotContainsString('password', $raw);
        $this->assertStringNotContainsString('secret', $raw);
        $this->assertStringNotContainsString('$2y$', $raw);

        $body = json_decode($result->getJSON(), true);

        $this->assertSame(
            ['id', 'username', 'name', 'email', 'faculty_id', 'groups'],
            array_keys($body['data']),
        );
    }

    public function testGruposReflejanRolDelUsuario(): void
    {
        $user = fake(UserModel::class, ['name' => 'Carla']);
        $user->addGroup('admin');

        $result = $this->actingAs($user)->get('api/v1/me');

        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);

        $this->assertSame(['admin'], $body['data']['groups']);
    }

    public function testFormatoDeRespuestaEsElContratado(): void
    {
        $user = fake(UserModel::class, ['name' => 'Diego']);

        $result = $this->actingAs($user)->get('api/v1/me');

        $result->assertStatus(200);
        $result->assertJSONPath('success', true);
        $result->assertJSONPath('message', null);
        $result->assertJSONPath('errors', null);
    }

    public function testRutaChatExisteYExigeSesion(): void
    {
        // Stub de contrato (F4d): la ruta existe y está protegida.
        // El controller lo implementa Int.3; hasta entonces da 404 con sesión.
        // Aquí CSRF bloquea el POST sin token (la protección global manda).
        $this->expectException(SecurityException::class);

        $this->post('api/v1/chat', ['message' => 'hola']);
    }
}
