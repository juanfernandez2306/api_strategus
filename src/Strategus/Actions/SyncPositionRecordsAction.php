<?php

declare(strict_types=1);

namespace App\Strategus\Actions;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use App\Strategus\Services\SyncPositionRecordsUseCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class SyncPositionRecordsAction
{
    public function __construct(
        private SyncPositionRecordsUseCase $syncUseCase
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');

        $rawRecords = (array) ($request->getParsedBody() ?? []);

        $outputDTO = $this->syncUseCase->execute(
            rawRecords: $rawRecords,
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