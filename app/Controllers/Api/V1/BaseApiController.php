<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Base de la API v1: formato de respuesta uniforme
 * {success, data, message, errors} para todos los endpoints.
 */
abstract class BaseApiController extends Controller
{
    use ResponseTrait;

    /**
     * Respuesta exitosa. $meta solo se usa en listados paginados
     * ({"page":1,"perPage":20,"total":57}).
     */
    protected function ok(mixed $data, int $status = 200, ?array $meta = null): ResponseInterface
    {
        $body = [
            'success' => true,
            'data'    => $data,
            'message' => null,
            'errors'  => null,
        ];

        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        return $this->respond($body, $status);
    }

    protected function error(string $message, int $status, ?array $errors = null): ResponseInterface
    {
        return $this->respond([
            'success' => false,
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * Body JSON o form. JSON malformado aborta con 400.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        if (! str_contains($this->request->getHeaderLine('Content-Type'), 'application/json')) {
            return $this->request->getPost();
        }

        $raw = trim((string) $this->request->getBody());

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestException('JSON malformado.');
        }

        return $decoded;
    }
}
