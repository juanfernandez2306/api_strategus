<?php

declare(strict_types=1);

namespace App\Shared\Services\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    public static function create(): LoggerInterface
    {
        $logger = new Logger('app');
        
        $logFile = dirname(__DIR__, 4) . '/logs/app.log';
        
        $logger->pushHandler(new StreamHandler($logFile, Level::Debug));

        return $logger;
    }
}