<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Strategus\Monitoring;

use App\Strategus\Repositories\OilPalmGrowingAreaRepositoryInterface;
use App\Shared\Exceptions\MonitoringUuidAlreadyExistsException;
use App\Strategus\DTOs\Monitoring\BulkSyncOutputDTO;
use App\Strategus\DTOs\Monitoring\SpatialMatchOutputDTO;
use App\Strategus\Repositories\StrategusMonitoringRepositoryInterface;
use App\Strategus\Services\SyncPositionRecordsUseCase;
use App\Strategus\Validators\PositionRecordValidator;
use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PDO;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class SyncPositionRecordsUseCaseTest extends TestCase
{
    /** @var PositionRecordValidator|MockObject */
    private $validatorMock;

    /** @var OilPalmGrowingAreaRepositoryInterface|MockObject */
    private $growingAreaRepositoryMock;

    /** @var StrategusMonitoringRepositoryInterface|MockObject */
    private $monitoringRepositoryMock;

    /** @var PDO|MockObject */
    private $pdoMock;

    /** @var LoggerInterface|MockObject */
    private $loggerMock;

    private FakerGenerator $faker;
    private SyncPositionRecordsUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = FakerFactory::create();

        $this->validatorMock = $this->createMock(PositionRecordValidator::class);
        $this->growingAreaRepositoryMock = $this->createMock(OilPalmGrowingAreaRepositoryInterface::class);
        $this->monitoringRepositoryMock = $this->createMock(StrategusMonitoringRepositoryInterface::class);
        $this->pdoMock = $this->createMock(PDO::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->useCase = new SyncPositionRecordsUseCase(
            validator: $this->validatorMock,
            growingAreaRepository: $this->growingAreaRepositoryMock,
            monitoringRepository: $this->monitoringRepositoryMock,
            pdo: $this->pdoMock,
            logger: $this->loggerMock
        );
    }

    private function generateRawRecord(?string $uuid = null, bool $isReviewed = false): array
    {
        return [
            'uuid'            => $uuid ?? $this->faker->uuid(),
            'latitude'        => $this->faker->latitude(9.0, 10.5),
            'longitude'       => $this->faker->longitude(-73.5, -72.0),
            'recordedDate'    => $this->faker->date('Y-m-d'),
            'recordedTime'    => $this->faker->time('H:i:s'),
            'galleryCount'    => $this->faker->numberBetween(0, 5),
            'gpsAccuracy'     => $this->faker->randomFloat(2, 1, 10),
            'isPlantReviewed' => $isReviewed,
            'isSynced'        => false,
            'reviewedDate'    => $isReviewed ? $this->faker->date('Y-m-d') : null,
            'reviewedTime'    => $isReviewed ? $this->faker->time('H:i:s') : null,
        ];
    }

    public function testExecuteSuccessfulSyncForIncompleteRecord(): void
    {
        $userId = $this->faker->numberBetween(1, 100);
        $growingAreaCode = $this->faker->numberBetween(100, 200);
        $rawRecord = $this->generateRawRecord(isReviewed: false);
        $rawRecords = [$rawRecord];

        $this->validatorMock->expects($this->once())
            ->method('validateBulk')
            ->with($rawRecords)
            ->willReturn($rawRecords);

        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->pdoMock->expects($this->once())->method('commit');

        $this->growingAreaRepositoryMock->expects($this->once())
            ->method('findCodeByLocation')
            ->with((float) $rawRecord['latitude'], (float) $rawRecord['longitude'])
            ->willReturn($growingAreaCode);

        $this->monitoringRepositoryMock->expects($this->once())
            ->method('findDuplicateInRadius')
            ->willReturn(SpatialMatchOutputDTO::notFound());

        $this->monitoringRepositoryMock->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $result = $this->useCase->execute($rawRecords, $userId);

        $this->assertInstanceOf(BulkSyncOutputDTO::class, $result);
        $this->assertSame(1, $result->insertedCountRegister);
        $this->assertEmpty($result->deletedUuids);
        $this->assertContains($rawRecord['uuid'], $result->syncedIncompleteUuids);
    }

    public function testExecutePointOutsidePolygonShouldAddToDeletedUuids(): void
    {
        $userId = $this->faker->numberBetween(1, 100);
        $rawRecord = $this->generateRawRecord();
        $rawRecords = [$rawRecord];

        $this->validatorMock->expects($this->once())
            ->method('validateBulk')
            ->willReturn($rawRecords);

        $this->pdoMock->expects($this->once())->method('beginTransaction');
        $this->pdoMock->expects($this->once())->method('commit');

        $this->growingAreaRepositoryMock->expects($this->once())
            ->method('findCodeByLocation')
            ->willReturn(null);

        $this->monitoringRepositoryMock->expects($this->never())->method('findDuplicateInRadius');
        $this->monitoringRepositoryMock->expects($this->never())->method('create');

        $result = $this->useCase->execute($rawRecords, $userId);

        $this->assertSame(0, $result->insertedCountRegister);
        $this->assertContains($rawRecord['uuid'], $result->deletedUuids);
        $this->assertEmpty($result->syncedIncompleteUuids);
    }

    public function testExecuteSpatialDuplicateReplaceOldIncompleteWithNewReviewed(): void
    {
        $userId = $this->faker->numberBetween(1, 100);
        $growingAreaCode = $this->faker->numberBetween(100, 200);
        $oldUuid = $this->faker->uuid();

        $newRawRecord = $this->generateRawRecord(isReviewed: true);
        $rawRecords = [$newRawRecord];

        $this->validatorMock->method('validateBulk')->willReturn($rawRecords);
        $this->growingAreaRepositoryMock->method('findCodeByLocation')->willReturn($growingAreaCode);

        $this->monitoringRepositoryMock->expects($this->once())
            ->method('findDuplicateInRadius')
            ->willReturn(new SpatialMatchOutputDTO(
                found: true,
                uuid: $oldUuid,
                isReviewed: false
            ));

        $this->monitoringRepositoryMock->expects($this->once())
            ->method('delete')
            ->with($oldUuid)
            ->willReturn(true);

        $this->monitoringRepositoryMock->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $result = $this->useCase->execute($rawRecords, $userId);

        $this->assertSame(1, $result->insertedCountRegister);
        $this->assertContains($newRawRecord['uuid'], $result->deletedUuids);
        $this->assertEmpty($result->syncedIncompleteUuids);
    }

    public function testExecutePrimaryKeyCollisionUpdateWhenIncomingIsReviewed(): void
    {
        $userId = $this->faker->numberBetween(1, 100);
        $growingAreaCode = $this->faker->numberBetween(100, 200);
        $sharedUuid = $this->faker->uuid();

        $incomingRecord = $this->generateRawRecord(uuid: $sharedUuid, isReviewed: true);
        $rawRecords = [$incomingRecord];

        $this->validatorMock->method('validateBulk')->willReturn($rawRecords);
        $this->growingAreaRepositoryMock->method('findCodeByLocation')->willReturn($growingAreaCode);
        $this->monitoringRepositoryMock->method('findDuplicateInRadius')->willReturn(SpatialMatchOutputDTO::notFound());

        $this->monitoringRepositoryMock->expects($this->once())
            ->method('create')
            ->willThrowException(new MonitoringUuidAlreadyExistsException($sharedUuid));

        $this->monitoringRepositoryMock->expects($this->once())
            ->method('findByUuid')
            ->with($sharedUuid)
            ->willReturn([
                'uuid'        => $sharedUuid,
                'reviewed_at' => null
            ]);

        $this->monitoringRepositoryMock->expects($this->once())
            ->method('updateReviewedAt')
            ->willReturn(true);

        $result = $this->useCase->execute($rawRecords, $userId);

        $this->assertSame(0, $result->insertedCountRegister);
        $this->assertContains($sharedUuid, $result->deletedUuids);
    }

    public function testExecuteRollbacksTransactionAndLogsErrorOnUnexpectedException(): void
    {
        $userId = $this->faker->numberBetween(1, 100);
        $rawRecord = $this->generateRawRecord();
        $rawRecords = [$rawRecord];

        $this->validatorMock->method('validateBulk')->willReturn($rawRecords);

        $this->pdoMock->expects($this->once())->method('beginTransaction');

        $this->growingAreaRepositoryMock->method('findCodeByLocation')
            ->willThrowException(new RuntimeException('Fatal Database Connection Loss'));

        $this->pdoMock->expects($this->once())->method('inTransaction')->willReturn(true);
        $this->pdoMock->expects($this->once())->method('rollBack');

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                'Error durante la sincronización por fragmentos',
                $this->callback(function (array $context) use ($userId) {
                    return $context['user_id'] === $userId
                        && $context['chunk_index'] === 0
                        && $context['chunk_size'] === 1
                        && $context['error'] === 'Fatal Database Connection Loss';
                })
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fatal Database Connection Loss');

        $this->useCase->execute($rawRecords, $userId);
    }
}
