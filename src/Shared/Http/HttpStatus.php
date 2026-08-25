<?php

declare(strict_types=1);

namespace App\Shared\Http;

class HttpStatus
{
    public const OK = 200;
    public const CREATED = 201;
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const CONFLICT = 409;
    public const UNPROCESSABLE_ENTITY = 422;
    public const INTERNAL_SERVER_ERROR = 500;
}
