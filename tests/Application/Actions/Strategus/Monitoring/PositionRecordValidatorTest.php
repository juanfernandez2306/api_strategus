<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Strategus\Monitoring;

use App\Shared\Exceptions\ValidationException;
use App\Strategus\Validators\PositionRecordValidator;
use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use PHPUnit\Framework\TestCase;

final class PositionRecordValidatorTest extends TestCase
{
    private PositionRecordValidator $validator;
    private FakerGenerator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PositionRecordValidator();
        $this->faker = FakerFactory::create();
    }

    private function getValidRandomData(): array
    {
        return [
            'uuid'            => sprintf(
                '018f8e70-6c92-7%03x-%04x-%012x',
                $this->faker->numberBetween(0, 4095),
                $this->faker->numberBetween(32768, 49151),
                $this->faker->numberBetween(0, 281474976710655)
            ),
            'latitude'        => (string) $this->faker->randomFloat(6, 9.0, 10.5),
            'longitude'       => (string) $this->faker->randomFloat(6, -73.5, -72.0),
            'recordedDate'    => $this->faker->date('Y-m-d'),
            'recordedTime'    => $this->faker->time('H:i:s'),
            'galleryCount'    => (string) $this->faker->numberBetween(0, 10),
            'gpsAccuracy'     => (string) $this->faker->randomFloat(2, 0.5, 19.9),
            'isPlantReviewed' => $this->faker->boolean(),
            'isSynced'        => $this->faker->boolean(),
            'reviewedDate'    => $this->faker->optional(0.5)->date('Y-m-d'),
            'reviewedTime'    => $this->faker->optional(0.5)->time('H:i:s'),
        ];
    }

    public function testValidateSuccessfullyWithRandomNumericStrings(): void
    {
        $input = $this->getValidRandomData();
        $validated = $this->validator->validate($input);

        $this->assertIsArray($validated);
        $this->assertEquals($input['uuid'], $validated['uuid']);
    }

    public function testValidateBulkSuccessfullyWithMultipleRandomRecords(): void
    {
        $batch = [
            $this->getValidRandomData(),
            $this->getValidRandomData(),
            $this->getValidRandomData(),
        ];

        $validatedBatch = $this->validator->validateBulk($batch);

        $this->assertIsArray($validatedBatch);
        $this->assertCount(3, $validatedBatch);
    }

    public function testValidateBulkFailsWithIndexedErrorMessage(): void
    {
        $batch = [
            $this->getValidRandomData(),
            $this->getValidRandomData(),
        ];

        // Corrompemos el GPS en el índice 1
        $batch[1]['gpsAccuracy'] = 'PRECISION_NO_VALIDA';

        try {
            $this->validator->validateBulk($batch);
            $this->fail('Se esperaba ValidationException pero no fue lanzada.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('registro indexado en [1]', $e->getMessage());
        }
    }

    /**
     * Prueba exhaustiva de datos corruptos mediante Data Provider.
     *
     * @dataProvider corruptedDataProvider
     */
    public function testFailsWithCorruptedData(string $field, mixed $corruptedValue): void
    {
        $this->expectException(ValidationException::class);

        $input = $this->getValidRandomData();

        if ($corruptedValue === '__REMOVE_KEY__') {
            unset($input[$field]);
        } else {
            $input[$field] = $corruptedValue;
        }

        $this->validator->validate($input);
    }

    public static function corruptedDataProvider(): array
    {
        return [
            // Precisión GPS corrupta
            'gpsAccuracy_text'           => ['gpsAccuracy', 'alta_precision'],
            'gpsAccuracy_exceeds_max'    => ['gpsAccuracy', '25.5'],
            'gpsAccuracy_negative'       => ['gpsAccuracy', '-1.5'],

            // Coordenadas corruptas
            'latitude_text'              => ['latitude', 'latitud_norte'],
            'latitude_out_of_bounds'     => ['latitude', '12.8500'],
            'longitude_text'             => ['longitude', 'longitud_oeste'],
            'longitude_out_of_bounds'    => ['longitude', '-75.0000'],

            // UUID v7 corrupto o v4
            'uuid_invalid_string'        => ['uuid', 'not-a-valid-uuid'],
            'uuid_v4_instead_of_v7'      => ['uuid', '123e4567-e89b-12d3-a456-426614174000'],

            // Fechas y Horas corruptas
            'recordedDate_bad_format'    => ['recordedDate', '15/08/2026'],
            'recordedTime_bad_format'    => ['recordedTime', '25:61:99'],
            'reviewedDate_invalid_text'  => ['reviewedDate', 'ayer'],

            // Conteos y Banderas corruptos
            'galleryCount_negative'      => ['galleryCount', '-5'],
            'galleryCount_float'         => ['galleryCount', '3.14'],
            'isPlantReviewed_not_bool'   => ['isPlantReviewed', 'quizas'],

            // Campos obligatorios faltantes
            'missing_uuid'               => ['uuid', '__REMOVE_KEY__'],
            'missing_latitude'           => ['latitude', '__REMOVE_KEY__'],
            'missing_recordedDate'       => ['recordedDate', '__REMOVE_KEY__'],
        ];
    }
}
