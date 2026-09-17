<?php

declare(strict_types=1);

namespace App\Strategus\Actions;

use App\Strategus\Repositories\StrategusMonitoringRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

class FindByUuidMonitoringAction
{
    private StrategusMonitoringRepositoryInterface $repository;

    public function __construct(StrategusMonitoringRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        // 1. Extraer el UUID de los parámetros de la ruta (/api/monitoring/{uuid})
        $uuid = $args['uuid'] ?? '';

        // 2. Consultar el repositorio (convierte string UUID -> BINARY(16) -> consulta PDO -> string UUID)
        $record = $this->repository->findByUuid($uuid);

        // 3. Manejo de registro no encontrado
        if (empty($record)) {
            throw new HttpNotFoundException($request, "Monitoreo con UUID '{$uuid}' no encontrado.");
        }

        // 4. Estructurar respuesta exitosa
        $payload = json_encode([
            'status'     => 'success',
            'statusCode' => 200,
            'data'       => $record,
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
