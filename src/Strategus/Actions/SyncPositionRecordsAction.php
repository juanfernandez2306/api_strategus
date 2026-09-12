<?php

declare(strict_types=1);

namespace App\Strategus\Actions;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Shared\Services\Normalizers\CompressedPayloadTransformer;
use App\Strategus\DTOs\Monitoring\PositionRecordItemInputDTO;
use App\Strategus\Services\SyncPositionRecordsUseCase;
use App\Strategus\Validators\PositionRecordValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class SyncPositionRecordsAction
{
    public function __construct(
        private PositionRecordValidator $validator,
        private SyncPositionRecordsUseCase $syncUseCase
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');

        $rawBody = (array) ($request->getParsedBody() ?? []);

        $unpackedRecords = CompressedPayloadTransformer::unpack($rawBody);

        $validatedRecords = $this->validator->validateBulk($unpackedRecords);

        $dtoRecords = array_map(
            fn(array $item) => PositionRecordItemInputDTO::fromArray($item),
            $validatedRecords
        );

        $outputDTO = $this->syncUseCase->execute(
            dtoRecords: $dtoRecords,
            userId: $userId
        );

        return ApiResponse::json(
            response: $response,
            statusCode: HttpStatus::OK,
            message: 'Sincronización procesada correctamente.',
            data: $outputDTO->toArray()
        );
    }
}
