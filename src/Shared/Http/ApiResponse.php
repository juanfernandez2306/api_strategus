<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

class ApiResponse
{
    public static function json(
        Response $response,
        int $statusCode,
        string $message,
        mixed $data = null,
        ?array $errors = null
    ): Response {
        $isSuccess = $statusCode >= 200 && $statusCode < 300;

        $payload = [
            'status'     => $isSuccess ? 'success' : 'error',
            'statusCode' => $statusCode,
            'message'    => $message,
            'data'       => $data,
            'errors'     => $errors
        ];

        try {
            $encodedPayload = json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $e) {
            $statusCode = HttpStatus::INTERNAL_SERVER_ERROR;
            $encodedPayload = json_encode([
                'status'     => 'error',
                'statusCode' => $statusCode,
                'message'    => 'Error crítico al serializar la respuesta JSON.',
                'data'       => null,
                'errors'     => null
            ]);
        }

        $response->getBody()->write($encodedPayload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
