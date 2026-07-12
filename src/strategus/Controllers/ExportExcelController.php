<?php

namespace App\Strategus\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Strategus\Services\ExportExcelService;
use App\Strategus\Validators\ExportExcelValidator;
use Slim\Psr7\Stream;

class ExportExcelController
{
    private ExportExcelService $excelService;
    private ExportExcelValidator $validator;

    public function __construct(ExportExcelService $excelService, ExportExcelValidator $validator)
    {
        $this->excelService = $excelService;
        $this->validator = $validator;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $parsedBody = $request->getParsedBody() ?? [];
        
        // 1. Validar el rango de fechas y deltas
        $errores = $this->validator->validate($parsedBody);

        if (!empty($errores)) {
            $primerError = reset($errores);
            
            $response->getBody()->write(json_encode([
                'statusCode' => 400,
                'error' => [
                    'type' => 'VALIDATION_ERROR',
                    'description' => $primerError
                ]
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $fechaInicioParam = $parsedBody['fecha_inicio'];
        $fechaFinParam = $parsedBody['fecha_fin'];

        try {
            // 2. Intentar generar el Excel
            $stream = $this->excelService->generateMonitoreosExcel($fechaInicioParam, $fechaFinParam);
            $filename = "monitoreos_{$fechaInicioParam}_al_{$fechaFinParam}.xlsx";
            
            return $response
                ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Cache-Control', 'max-age=0')
                ->withBody(new Stream($stream));

        } catch (\Exception $e) {
        // 3. CAPTURA OPTIMIZADA: Cambiar obligatoriamente a HTTP status 400
            if ($e->getMessage() === 'NO_ROWS_FOUND') {
                $response->getBody()->write(json_encode([
                    'statusCode' => 400,
                    'error' => [
                        'type' => 'NO_ROWS_FOUND',
                        'description' => 'No se encontraron registros en el rango de fechas seleccionado.'
                    ]
                ], JSON_UNESCAPED_UNICODE));
                
                // CORREGIDO: Ahora retorna un código de error 400 real al navegador
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Errores críticos de la biblioteca o base de datos (Status 500)
            $response->getBody()->write(json_encode([
                'statusCode' => 500,
                'error' => [
                    'type' => 'EXCEL_ERROR', 
                    'description' => $e->getMessage()
                ]
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}