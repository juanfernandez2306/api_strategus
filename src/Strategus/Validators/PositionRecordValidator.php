<?php

declare(strict_types=1);

namespace App\Strategus\Validators;

use App\Shared\Exceptions\ValidationException;
use App\Shared\Http\HttpStatus;
use App\Shared\Validator\BaseValidator;

class PositionRecordValidator extends BaseValidator
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    protected function customAttributes(): array
    {
        return [
            'uuid'            => 'identificador único',
            'latitude'        => 'latitud',
            'longitude'       => 'longitud',
            'recordedDate'    => 'fecha de registro',
            'recordedTime'    => 'hora de registro',
            'galleryCount'    => 'conteo de galerías',
            'gpsAccuracy'     => 'precisión del GPS',
            'isPlantReviewed' => 'planta revisada',
            'isSynced'        => 'estado de sincronización',
            'reviewedDate'    => 'fecha de revisión',
            'reviewedTime'    => 'hora de revisión',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [
            'uuid'            => 'required|ramsey_uuid',
            'latitude'        => 'required|numeric',
            'longitude'       => 'required|numeric',
            'recordedDate'    => 'required|date_ymd',
            'recordedTime'    => 'required|time_hms',
            'galleryCount'    => 'required|integer|min:0|max:15',
            'gpsAccuracy'     => 'required|numeric|min:0|max:20',
            'isPlantReviewed' => 'required|boolean',
            'isSynced'        => 'required|boolean',
            'reviewedDate'    => 'nullable|date_ymd',
            'reviewedTime'    => 'nullable|time_hms'
        ];
    }

    /**
     * @param array<array<string, mixed>> $records
     * @return array<array<string, mixed>>
     */
    public function validateBulk(array $records): array
    {
        $validatedRecords = [];

        foreach ($records as $index => $record) {
            $uuid = is_array($record) && isset($record['uuid'])
                ? (string) $record['uuid']
                : 'S/I';

            try {
                $validatedRecords[$index] = $this->validate($record);
            } catch (ValidationException $e) {
                throw new ValidationException(
                    errors: $e->getErrors(),
                    message: sprintf(
                        'Error de validación en el registro con UUID [%s] (índice %d): %s',
                        $uuid,
                        $index,
                        $e->getMessage()
                    ),
                    code: HttpStatus::UNPROCESSABLE_ENTITY
                );
            }
        }

        return $validatedRecords;
    }
}
