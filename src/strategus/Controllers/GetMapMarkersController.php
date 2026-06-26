<?php

namespace App\Strategus\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Strategus\Repository\StrategusRepository;
use Exception;

class GetMapMarkersController
{
    private StrategusRepository $repository;

    public function __construct(StrategusRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        try {
            $markers = $this->repository->getMapMarkers();

            $payload = json_encode([
                'statusCode' => 200,
                'data' => $markers
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $response->getBody()->write($payload);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (Exception $e) {
            $payload = json_encode([
                'statusCode' => 500,
                'error' => ['type' => 'MAP_RANGE_ERROR', 'description' => $e->getMessage()]
            ], JSON_UNESCAPED_UNICODE);

            $response->getBody()->write($payload);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}