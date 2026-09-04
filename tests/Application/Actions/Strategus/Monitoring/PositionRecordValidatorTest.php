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
            'userId'          => (string) $this->faker->numberBetween(1, 100),
            'growingAreaCode' => (string) $this->faker->numberBetween(1, 9),
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

    public function testFailsWhenRandomLatitudeIsOutOfRange(): void
    {
        $this->expectException(ValidationException::class);

        $input = $this->getValidRandomData();

        $input['latitude'] = (string) $this->faker->randomFloat(6, 11.0, 15.0);

        $this->validator->validate($input);
    }

    public function testFailsWhenRandomLongitudeIsOutOfRange(): void
    {
        $this->expectException(ValidationException::class);

        $input = $this->getValidRandomData();

        $input['longitude'] = (string) $this->faker->randomFloat(6, -71.9, -65.0);

        $this->validator->validate($input);
    }

    public function testFailsWhenRandomGrowingAreaCodeIsOutOfRange(): void
    {
        $this->expectException(ValidationException::class);

        $input = $this->getValidRandomData();

        $input['growingAreaCode'] = (string) $this->faker->numberBetween(10, 50);

        $this->validator->validate($input);
    }
}
