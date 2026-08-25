<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Shared\Http\ApiResponse;
use App\Shared\Http\HttpStatus;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;
use Throwable;
use App\Shared\Exceptions\ValidationException;

class HttpErrorHandler extends SlimErrorHandler
{
    protected function respond(): Response
    {
        $exception = $this->exception;
        $statusCode = HttpStatus::INTERNAL_SERVER_ERROR;
        $message = 'Ha ocurrido un error interno al procesar su solicitud.';
        $errors = null;

        if ($exception instanceof ValidationException) {
            $statusCode = HttpStatus::UNPROCESSABLE_ENTITY;
            $message = $exception->getMessage();
            $errors = $exception->getErrors();
        } elseif ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
            $message = $exception->getMessage();

            if ($exception instanceof HttpNotFoundException) {
                $statusCode = HttpStatus::NOT_FOUND;
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $statusCode = HttpStatus::METHOD_NOT_ALLOWED;
            } elseif ($exception instanceof HttpUnauthorizedException) {
                $statusCode = HttpStatus::UNAUTHORIZED;
            } elseif ($exception instanceof HttpForbiddenException) {
                $statusCode = HttpStatus::FORBIDDEN;
            } elseif ($exception instanceof HttpBadRequestException) {
                $statusCode = HttpStatus::BAD_REQUEST;
            } elseif ($exception instanceof HttpNotImplementedException) {
                $statusCode = HttpStatus::NOT_IMPLEMENTED;
            }
        } elseif ($exception instanceof Throwable && $this->displayErrorDetails) {
            $message = $exception->getMessage();
        }

        $response = $this->responseFactory->createResponse();

        try {
            return ApiResponse::json(
                $response,
                $statusCode,
                $message,
                null,
                $errors
            );
        } catch (Throwable $e) {
            $fallbackPayload = json_encode([
                'status'     => 'error',
                'statusCode' => HttpStatus::INTERNAL_SERVER_ERROR,
                'message'    => 'Error crítico e imprevisto en el servidor.',
                'data'       => null,
                'errors'     => null
            ]);

            $response->getBody()->write($fallbackPayload);

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(HttpStatus::INTERNAL_SERVER_ERROR);
        }
    }
}
