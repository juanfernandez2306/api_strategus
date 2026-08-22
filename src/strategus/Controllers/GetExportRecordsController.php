<?php

namespace App\Strategus\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Strategus\Repository\StrategusRepository;
use App\Strategus\Validators\ExportExcelValidator;
use Exception;

class GetExportRecordsController
{
    private StrategusRepository $repository;
    private ExportExcelValidator $validator;

    public function __construct(
        StrategusRepository $repository,
        ExportExcelValidator $validator
    ) {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        try {
            // 1. Obtener los datos del body (POST)
            $queryParams = $request->getQueryParams();

            // 2. Ejecutar la validación con Rakit mediante ExportExcelValidator
            $errors = $this->validator->validate($queryParams);

            if (!empty($errors)) {
                $payload = json_encode([
                    'statusCode' => 400,
                    'error' => [
                        'type' => 'VALIDATION_ERROR',
                        'description' => 'Los datos enviados no superaron las reglas de validación.',
                        'details' => $errors
                    ]
                ], JSON_UNESCAPED_UNICODE);

                $response->getBody()->write($payload);
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(400);
            }

            // 3. Preparar las fechas con la hora correspondiente para la consulta SQL
            $fechaInicio = $queryParams['fecha_inicio'] . ' 00:00:00';
            $fechaFin = $queryParams['fecha_fin'] . ' 23:59:59';

            // 4. Consultar el repositorio
            $records = $this->repository->getExportRecords($fechaInicio, $fechaFin);

            $payload = json_encode([
                'statusCode' => 200,
                'data' => $records
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);

            $response->getBody()->write($payload);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (Exception $e) {
            $payload = json_encode([
                'statusCode' => 500,
                'error' => [
                    'type' => 'EXPORT_RECORDS_ERROR',
                    'description' => $e->getMessage()
                ]
            ], JSON_UNESCAPED_UNICODE);

            $response->getBody()->write($payload);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}
