<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Load .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

date_default_timezone_set('Asia/Jakarta');


// Container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../src/container.php');
$container = $containerBuilder->build();

// App
AppFactory::setContainer($container);
$app = AppFactory::create();

// Middleware
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(
    displayErrorDetails: ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    logErrors: true,
    logErrorDetails: true
);

// Routes
(require __DIR__ . '/../src/Routes/api.php')($app);

$app->run();