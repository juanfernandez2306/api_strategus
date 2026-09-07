<?php

declare(strict_types=1);

namespace Tests\Application\Shared\Services\Normalizers;

use App\Shared\Services\Normalizers\CompressedPayloadTransformer;
use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CompressedPayloadTransformerTest extends TestCase
{
    private FakerGenerator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = FakerFactory::create();
    }

    public function testUnpackReturnsEmptyArrayWhenPayloadIsEmpty(): void
    {
        $result = CompressedPayloadTransformer::unpack([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testUnpackReturnsSamePayloadWhenAlreadyAssociativeArray(): void
    {
        $associativePayload = [
            [
                'uuid' => $this->faker->uuid(),
                'latitude' => $this->faker->latitude(),
                'longitude' => $this->faker->longitude(),
            ],
            [
                'uuid' => $this->faker->uuid(),
                'latitude' => $this->faker->latitude(),
                'longitude' => $this->faker->longitude(),
            ],
        ];

        $result = CompressedPayloadTransformer::unpack($associativePayload);

        $this->assertSame($associativePayload, $result);
    }

    public function testUnpackSuccessfullyTransformsCompressedMatrixToAssociativeArray(): void
    {
        $uuid1 = $this->faker->uuid();
        $uuid2 = $this->faker->uuid();
        $lat1 = $this->faker->latitude();
        $lat2 = $this->faker->latitude();
        $lng1 = $this->faker->longitude();
        $lng2 = $this->faker->longitude();

        $headers = ['uuid', 'latitude', 'longitude'];
        $row1 = [$uuid1, $lat1, $lng1];
        $row2 = [$uuid2, $lat2, $lng2];

        $compressedPayload = [$headers, $row1, $row2];

        $result = CompressedPayloadTransformer::unpack($compressedPayload);

        $expected = [
            [
                'uuid' => $uuid1,
                'latitude' => $lat1,
                'longitude' => $lng1,
            ],
            [
                'uuid' => $uuid2,
                'latitude' => $lat2,
                'longitude' => $lng2,
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testUnpackThrowsInvalidArgumentExceptionWhenRowHeaderCountMismatches(): void
    {
        $headers = ['uuid', 'latitude', 'longitude'];
        $validRow = [$this->faker->uuid(), $this->faker->latitude(), $this->faker->longitude()];

        // Fila 2 con menos elementos de los esperados por los headers (2 elementos en lugar de 3)
        $invalidRow = [$this->faker->uuid(), $this->faker->latitude()];

        $compressedPayload = [$headers, $validRow, $invalidRow];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El registro en la fila 2 no coincide con el número de encabezados.');

        CompressedPayloadTransformer::unpack($compressedPayload);
    }
}
