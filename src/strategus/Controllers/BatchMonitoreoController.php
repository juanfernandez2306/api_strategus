<?php

namespace App\Strategus\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Strategus\Repository\StrategusRepository;
use App\Strategus\Validators\MonitoreoValidator;

class BatchMonitoreoController
{
    private StrategusRepository $repository;
    private MonitoreoValidator $validator;

    public function __construct(StrategusRepository $repository, MonitoreoValidator $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (!is_array($body) || empty($body)) {
            return $this->jsonResponse($response, [
                'statusCode' => 400,
                'error' => [
                    'type' => 'BAD_REQUEST',
                    'description' => 'El cuerpo de la solicitud debe ser un arreglo no vacío de monitoreos.'
                ]
            ], 400);
        }

        // DELEGADO: Confiamos ciegamente en el AuthMiddleware de las Rutas.
        // Si la petición llegó hasta aquí, el usuario_id existe obligatoriamente.
        $usuarioId = (int) $request->getAttribute('usuario_id');

        $sincronizados = [];
        $errores = [];

        foreach ($body as $index => $item) {
            $uuid = $item['uuid'] ?? "index_$index";

            // Validar el elemento actual
            $validation = $this->validator->validate($item);

            if ($validation->fails()) {
                $errores[] = [
                    'uuid' => $uuid,
                    'error' => 'Errores de validación en campos',
                    'details' => $validation->errors()->firstOfAll()
                ];
                continue;
            }

            try {
                // Ejecutar el Upsert individual (Inserta nuevo o actualiza fecha_revision)
                $resultado = $this->repository->upsert($item, $usuarioId);

                if ($resultado) {
                    $sincronizados[] = $uuid;
                } else {
                    $errores[] = [
                        'uuid' => $uuid,
                        'error' => 'No se pudo procesar el registro en el servidor.'
                    ];
                }
            } catch (\Exception $e) {
                $errores[] = [
                    'uuid' => $uuid,
                    'error' => 'Fallo interno en el repositorio',
                    'message' => $e->getMessage()
                ];
            }
        }

        // Determinar código de respuesta según los resultados del lote
        $httpStatus = 200;
        if (empty($sincronizados)) {
            $httpStatus = 422; 
        } elseif (!empty($errores)) {
            $httpStatus = 207; // Multi-Status (Algunos pasaron, otros fallaron)
        }

        return $this->jsonResponse($response, [
            'statusCode' => $httpStatus,
            'message' => 'Procesamiento de sincronización masiva finalizado.',
            'summary' => [
                'total_enviados' => count($body),
                'exitosos' => count($sincronizados),
                'fallidos' => count($errores)
            ],
            'data' => [
                'sincronizados' => $sincronizados, // Array de UUIDs para limpiar IndexedDB
                'errores' => $errores
            ]
        ], $httpStatus);
    }

    private function jsonResponse(Response $response, array $data, int $status): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}