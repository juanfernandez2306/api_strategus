<?php

declare(strict_types=1);

namespace App\Strategus\Validators;

use App\Users\Validators\BaseValidator;
use App\Shared\Exceptions\ValidationException;

class PositionRecordValidator extends BaseValidator
{
    public const UUID_V7_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    public const DATE_REGEX = '/^\d{4}-\d{2}-\d{2}$/';
    public const TIME_REGEX = '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/';

    public function __construct()
    {
        parent::__construct();

        $this->validator->setMessages([
            'uuid:regex'          => 'El campo :attribute debe ser un UUID v7 válido.',
            'recordedDate:regex'  => 'La fecha de registro debe tener el formato YYYY-MM-DD.',
            'recordedTime:regex'  => 'La hora de registro debe tener el formato HH:mm:ss.',
            'reviewedDate:regex'  => 'La fecha de revisión debe tener el formato YYYY-MM-DD.',
            'reviewedTime:regex'  => 'La hora de revisión debe tener el formato HH:mm:ss.',
        ]);
    }

    protected function customAttributes(): array
    {
        return [
            'uuid'            => 'identificador único (UUID v7)',
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

    protected function rules(): array
    {
        return [
            'uuid'            => 'required|regex:' . self::UUID_V7_REGEX,
            'latitude'        => 'required|numeric|min:-90|max:90',
            'longitude'       => 'required|numeric|min:-180|max:180',
            'recordedDate'    => 'required|regex:' . self::DATE_REGEX,
            'recordedTime'    => 'required|regex:' . self::TIME_REGEX,
            'galleryCount'    => 'required|integer|min:0',
            'gpsAccuracy'     => 'required|numeric|min:0|max:999.99',
            'isPlantReviewed' => 'required|boolean',
            'isSynced'        => 'required|boolean',
            'reviewedDate'    => 'nullable|regex:' . self::DATE_REGEX,
            'reviewedTime'    => 'nullable|regex:' . self::TIME_REGEX,
        ];
    }

    public function validateBulk(array $records): array
    {
        $validatedRecords = [];

        foreach ($records as $index => $record) {
            try {
                $validatedRecords[] = $this->validate($record);
            } catch (ValidationException $e) {
                throw new ValidationException([
                    'index'  => $index,
                    'errors' => $e->getErrors()
                ]);
            }
        }

        return $validatedRecords;
    }
}
