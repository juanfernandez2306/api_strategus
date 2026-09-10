<?php

declare(strict_types=1);

namespace App\Shared\Services\Normalizers;

use InvalidArgumentException;

final class CompressedPayloadTransformer
{
    /**
     * Claves comunes que suelen envolver el listado de registros.
     */
    private const WRAPPER_KEYS = ['records', 'data', 'items', 'payload'];

    public static function unpack(array $rawPayload): array
    {
        if (empty($rawPayload)) {
            return [];
        }

        // 1. Extraer el array interno si viene envuelto en un JSON asociativo (ej. {"records": [...]})
        $rawPayload = self::unwrapPayload($rawPayload);

        if (empty($rawPayload)) {
            return [];
        }

        // 2. Si es una lista de objetos JSON estándar [{"uuid": "..."}, ...], devolver tal cual
        if (!isset($rawPayload[0]) || !is_array($rawPayload[0]) || !array_is_list($rawPayload[0])) {
            return $rawPayload;
        }

        // 3. Si es el formato tabular/matriz [ ["cabeceras"], ["valores"] ], desempacar
        $headers = array_shift($rawPayload);
        $headerCount = count($headers);
        $unpackedRecords = [];

        foreach ($rawPayload as $index => $row) {
            if (!is_array($row) || count($row) !== $headerCount) {
                throw new InvalidArgumentException(
                    sprintf(
                        'El registro en la fila %d no coincide con el número de encabezados.',
                        $index + 1
                    )
                );
            }

            $unpackedRecords[] = array_combine($headers, $row);
        }

        return $unpackedRecords;
    }

    /**
     * Detecta y desenrolla el payload si está dentro de una propiedad contenedora.
     */
    private static function unwrapPayload(array $payload): array
    {
        // Si ya es una lista indexada (array secuencial), no necesita desenrollar
        if (array_is_list($payload)) {
            return $payload;
        }

        // Buscar si los registros vienen en alguna de las claves contenedoras estándar
        foreach (self::WRAPPER_KEYS as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return $payload;
    }
}
