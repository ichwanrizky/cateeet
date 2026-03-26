<?php

declare(strict_types=1);

use App\Database\Connection;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

return [

    mysqli::class => function () {
        return Connection::make();
    },

    LoggerInterface::class => function () {
        $logger = new Logger('app');
        $logger->pushHandler(new StreamHandler(
            __DIR__ . '/../logs/app.log',
            Logger::DEBUG
        ));
        return $logger;
    },

];