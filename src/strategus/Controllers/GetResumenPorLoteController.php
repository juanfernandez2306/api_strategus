<?php

namespace App\Strategus\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Strategus\Repository\StrategusRepository;
use Exception;

class GetResumenPorLoteController
{
    private StrategusRepository $repository;

    // PHP-DI inyectará automáticamente el repositorio configurado
    public function __construct(StrategusRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        // 1. Capturar el parámetro desde el cuerpo del POST (Form Data)
        $parsedBody = $request->getParsedBody();
        $fechaInput = '2026-06-27'; // Espera formato 'YYYY-MM-DD' de Day.js

        // Validar que se haya enviado la fecha requerida
        if (!$fechaInput) {
            return $this->jsonResponse($response, [
                'statusCode' => 400,
                'error' => [
                    'type' => 'BAD_REQUEST',
                    'description' => 'El campo de formulario "fecha_input" es requerido en el cuerpo del POST.'
                ]
            ], 400);
        }

        try {
            // 2. Consultar las métricas de rendimiento desligadas directamente al Repositorio
            $resumen = $this->repository->getResumenPorLote($fechaInput);

            // 3. Retornar la respuesta exitosa con los datos estructurados por Lote
            return $this->jsonResponse($response, [
                'statusCode' => 200,
                'message' => 'Resumen analítico por lotes generado con éxito.',
                'fecha_consultada' => $fechaInput,
                'data' => $resumen
            ], 200);

        } catch (Exception $e) {
            // Control de fallos del servidor o base de datos
            return $this->jsonResponse($response, [
                'statusCode' => 500,
                'error' => [
                    'type' => 'SERVER_ERROR',
                    'description' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Helper para estandarizar las respuestas JSON de la API
     */
    private function jsonResponse(Response $response, array $data, int $status): Response
    {
        $payload = json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}