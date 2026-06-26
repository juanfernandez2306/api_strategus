<?php

namespace App\Strategus\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Strategus\Services\ExportExcelService;
use Slim\Psr7\Stream;

class ExportExcelController
{
    private ExportExcelService $excelService;

    // PHP-DI inyectará automáticamente el Servicio aquí
    public function __construct(ExportExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        // 1. Capturar parámetros desde el cuerpo del POST (Form Data)
        $parsedBody = $request->getParsedBody();
        
        $fechaInicioParam = $parsedBody['fecha_inicio'] ?? null;
        $fechaFinParam = $parsedBody['fecha_fin'] ?? null;

        // Validar que ambos campos vengan en el Form Data
        if (!$fechaInicioParam || !$fechaFinParam) {
            $response->getBody()->write(json_encode([
                'statusCode' => 400,
                'error' => [
                    'type' => 'BAD_REQUEST',
                    'description' => 'Los campos de formulario fecha_inicio y fecha_fin son requeridos en el cuerpo del POST.'
                ]
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // 2. Delegar la generación del Excel al archivo de Servicio
            $stream = $this->excelService->generateMonitoreosExcel($fechaInicioParam, $fechaFinParam);

            // Nombre dinámico para el archivo descargable
            $filename = "monitoreos_{$fechaInicioParam}_al_{$fechaFinParam}.xlsx";
            
            // 3. Responder con el flujo binario nativo directo de Slim
            return $response
                ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Cache-Control', 'max-age=0')
                ->withBody(new Stream($stream));

        } catch (\Exception $e) {
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