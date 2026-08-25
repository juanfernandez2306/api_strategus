<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Http\Message\ResponseInterface as Response;

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

        $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $response->getBody()->write($encodedPayload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
