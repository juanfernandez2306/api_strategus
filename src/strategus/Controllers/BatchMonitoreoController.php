<?php

namespace App\Strategus\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Strategus\Repository\StrategusRepository;
use App\Strategus\Validators\StrategusValidator;
use Exception;

class BatchMonitoreoController
{
    private StrategusRepository $repository;
    private StrategusValidator $validator;

    public function __construct(StrategusRepository $repository, StrategusValidator $validator)
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

        $usuarioId = (int) $request->getAttribute('usuario_id');

        // Listas de control para clasificar el destino en IndexedDB
        $completados = [];          // Tienen fecha_revision -> Se borrarán del móvil
        $guardadosSinRevision = [];  // Tienen fecha_revision = null -> Cambian a sincronizacion=true

        // Obtenemos la conexión PDO compartida desde el repositorio
        $db = $this->repository->getConnection();

        try {
            // INICIALIZACIÓN DE LA TRANSACCIÓN ATÓMICA DE MYSQL
            $db->beginTransaction();

            foreach ($body as $item) {
                $uuid = $item['uuid'] ?? null;

                // 1. Validación de datos individual
                $validation = $this->validator->validate($item);
                if ($validation->fails()) {
                    // Si un solo registro falla la validación, abortamos TODO el lote lanzando una excepción
                    $erroresValidacion = json_encode($validation->errors()->firstOfAll());
                    throw new Exception("Error de validación en el elemento con UUID [{$uuid}]: {$erroresValidacion}");
                }

                if ($this->repository->esDuplicadoEspacial($item)) {
                    // Es un duplicado de un punto más antiguo. Lo obviamos en MySQL central,
                    // pero lo metemos en 'completados' para que el Frontend lo borre del teléfono.
                    $completados[] = $uuid;
                    continue; 
                }

                // 2. Ejecución del Upsert en la base de datos central
                // (Garantiza inserción nueva o actualización si ya existe el UUID)
                $queryExitoso = $this->repository->upsert($item, $usuarioId);

                if (!$queryExitoso) {
                    throw new Exception("El repositorio retornó falso al procesar el UUID [{$uuid}].");
                }

                // 3. Si todo marcha bien para este registro, lo clasificamos por su estado de revisión
                if (!empty($item['fecha_revision'])) {
                    $completados[] = $uuid;
                } else {
                    $guardadosSinRevision[] = $uuid;
                }
            }

            // SI EL BUCLE COMPLETÓ EL 100% DE LOS REGISTROS SIN EXCEPCIONES, CONFIRMAMOS EN MYSQL
            $db->commit();

            return $this->jsonResponse($response, [
                'statusCode' => 200,
                'message' => 'Sincronización masiva procesada de manera atómica exitosamente.',
                'summary' => [
                    'total_enviados' => count($body),
                    'completados_para_borrar' => count($completados),
                    'guardados_sin_revision' => count($guardadosSinRevision)
                ],
                'data' => [
                    'completados' => $completados,
                    'guardados_sin_revision' => $guardadosSinRevision
                ]
            ], 200);

        } catch (Exception $e) {
            // SI CUALQUIER COSA FALLA, REVERTIMOS ABSOLUTAMENTE TODOS LOS CAMBIOS DE ESTA LLAMADA
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            return $this->jsonResponse($response, [
                'statusCode' => 422,
                'error' => [
                    'type' => 'TRANSACTION_ABORTED',
                    'description' => 'La sincronización masiva falló de forma unificada. No se guardó ningún registro en el servidor.',
                    'message' => $e->getMessage()
                ]
            ], 422);
        }
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