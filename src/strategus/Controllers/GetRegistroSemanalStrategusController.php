<?php

namespace App\Strategus\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Strategus\Repository\StrategusRepository;
use Exception;

class GetRegistroSemanalStrategusController
{
    private StrategusRepository $repository;

    // PHP-DI inyectará automáticamente el repositorio configurado
    public function __construct(StrategusRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        try {
            // 1. Consultar las métricas de rendimiento semanales directamente al Repositorio
            $datosGrafico = $this->repository->obtenerDatosGraficoSemanal();

            // 2. Retornar la respuesta exitosa con los datos estructurados para el gráfico
            return $this->jsonResponse($response, [
                'statusCode' => 200,
                'message' => 'Métricas semanales de monitoreo generadas con éxito.',
                'data' => $datosGrafico
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