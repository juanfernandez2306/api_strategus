<?php

namespace App\Home\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class IndexController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $baseUrl = $_ENV['APP_URL'];

        $data = [
            'success' => true,
            'name'    => 'API REST - Servicio de Geolocalización y Gestión Palma Digital',
            'organization' => 'Agropecuaria Guaikinima',
            'version' => '1.0.0',
            'status'  => 'online',
            'description' => 'Servicio espacial y alfanumérico para el monitoreo agronómico y control de plagas.',

            'support' => [
                'email' => 'fincaguaikinima@gmail.com'
            ]
        ];

        return $this->jsonResponse($response, $data, 200);
    }

    /**
     * Función auxiliar para estructurar respuestas JSON limpias
     */
    private function jsonResponse(Response $response, array $data, int $statusCode = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
