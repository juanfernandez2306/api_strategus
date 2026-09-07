<?php

declare(strict_types=1);

namespace App\Shared\Services\Normalizers;

use InvalidArgumentException;

final class CompressedPayloadTransformer
{
    public static function unpack(array $rawPayload): array
    {
        if (empty($rawPayload)) {
            return [];
        }

        if (!isset($rawPayload[0]) || !is_array($rawPayload[0]) || !array_is_list($rawPayload[0])) {
            return $rawPayload;
        }

        $headers = array_shift($rawPayload);
        $headerCount = count($headers);
        $unpackedRecords = [];

        foreach ($rawPayload as $index => $row) {
            if (count($row) !== $headerCount) {
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
}
