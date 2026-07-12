<?php

namespace App\Strategus\Services;

use App\Strategus\Repository\StrategusRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Exception;

class ExportExcelService
{
    private StrategusRepository $repository;

    public function __construct(StrategusRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Genera un archivo Excel en memoria basado en un rango de fechas
     * * @param string $fechaInicioParam Formato YYYY-MM-DD
     * @param string $fechaFinParam Formato YYYY-MM-DD
     * @return resource El stream del archivo temporal generado
     * @throws Exception
     */
    public function generateMonitoreosExcel(string $fechaInicioParam, string $fechaFinParam)
    {
        // Ajustar horas para asegurar el rango completo del día local
        $fechaInicio = $fechaInicioParam . ' 00:00:00';
        $fechaFin = $fechaFinParam . ' 23:59:59';

        // 1. Consultar datos al repositorio
        $data = $this->repository->getExportRecords($fechaInicio, $fechaFin);

        // 🔒 OPTIMIZACIÓN DE RAM: Si está vacío, lanzamos la excepción antes de tocar PhpSpreadsheet
        if (empty($data)) {
            throw new Exception('NO_ROWS_FOUND');
        }

        // 2. Inicializar la hoja de cálculo de PhpSpreadsheet (Solo se ejecuta si hay datos)
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoreos Strategus');

        // Encabezados
        $headers = ['Latitud', 'Longitud', 'Lote', 'Precisión', 'Fecha Registro', 'Fecha Revisión'];
        $sheet->fromArray($headers, NULL, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        // 3. Volcar datos obtenidos
        $sheet->fromArray($data, NULL, 'A2');

        // Auto-ajustar el tamaño de las columnas automáticamente
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 4. Preparar el puntero/stream temporal en PHP para Slim
        $writer = new Xlsx($spreadsheet);
        $stream = fopen('php://temp', 'r+');
        $writer->save($stream);
        rewind($stream);

        return $stream;
    }
}