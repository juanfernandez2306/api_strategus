<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Strategus;

use App\Shared\Exceptions\ValidationException;
use App\Strategus\Validators\OilPalmGrowingAreaValidator;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;

class OilPalmGrowingAreaValidatorTest extends TestCase
{
    private OilPalmGrowingAreaValidator $validator;
    private Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new OilPalmGrowingAreaValidator();
        $this->faker = FakerFactory::create();
    }

    public function testItPassesValidationWithValidRandomData(): void
    {
        $validPayload = [
            'uuid'              => $this->faker->uuid(),
            'growing_area_code' => $this->faker->numberBetween(1, 9999),
            'palm_count'        => $this->faker->numberBetween(0, 5000),
            'boundary_wkt'      => $this->generateValidRandomWktPolygon(),
        ];

        $validPayload['uuid'] = '018c2934-7b1a-7d12-bf99-' . substr(md5((string) rand()), 0, 12);

        $result = $this->validator->validate($validPayload);

        $this->assertSame($validPayload['uuid'], $result['uuid']);
        $this->assertSame($validPayload['growing_area_code'], $result['growing_area_code']);
        $this->assertSame($validPayload['palm_count'], $result['palm_count']);
        $this->assertSame($validPayload['boundary_wkt'], $result['boundary_wkt']);
    }

    public function testItFailsWhenRequiredFieldsAreMissing(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->validator->validate([]);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertArrayHasKey('uuid', $errors);
            $this->assertArrayHasKey('growing_area_code', $errors);
            $this->assertArrayHasKey('palm_count', $errors);
            $this->assertArrayHasKey('boundary_wkt', $errors);

            throw $e;
        }
    }

    public function testItFailsWithInvalidUuidV7(): void
    {
        $invalidUuid = $this->faker->word(); // Genera un string aleatorio no UUID

        $payload = [
            'uuid'              => $invalidUuid,
            'growing_area_code' => $this->faker->numberBetween(1, 100),
            'palm_count'        => $this->faker->numberBetween(1, 100),
            'boundary_wkt'      => $this->generateValidRandomWktPolygon(),
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validate($payload);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('uuid', $e->getErrors());
            throw $e;
        }
    }

    public function testItFailsWithInvalidWktPolygon(): void
    {

        $invalidWkt = sprintf(
            'POLYGON((%f %f, %f %f, %f %f))',
            $this->faker->longitude(),
            $this->faker->latitude(),
            $this->faker->longitude(),
            $this->faker->latitude(),
            $this->faker->longitude(),
            $this->faker->latitude()
        );

        $payload = [
            'uuid'              => '018c2934-7b1a-7d12-bf99-234b9215881a',
            'growing_area_code' => $this->faker->numberBetween(1, 100),
            'palm_count'        => $this->faker->numberBetween(1, 100),
            'boundary_wkt'      => $invalidWkt,
        ];

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validate($payload);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('boundary_wkt', $e->getErrors());
            throw $e;
        }
    }

    private function generateValidRandomWktPolygon(): string
    {
        $baseLng = $this->faker->longitude(-73.5, -72.5);
        $baseLat = $this->faker->latitude(9.0, 10.0);

        $p1 = sprintf('%f %f', $baseLng, $baseLat);
        $p2 = sprintf('%f %f', $baseLng + 0.01, $baseLat);
        $p3 = sprintf('%f %f', $baseLng + 0.01, $baseLat + 0.01);
        $p4 = sprintf('%f %f', $baseLng, $baseLat + 0.01);
        return sprintf('POLYGON((%s, %s, %s, %s, %s))', $p1, $p2, $p3, $p4, $p1);
    }
}
