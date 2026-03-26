<?php

declare(strict_types=1);

use Slim\App;

return function (App $app) {

    // Health check
    $app->get('/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Telegram webhook (public, tanpa auth)
    $app->post('/webhook/telegram', \App\Controllers\TelegramController::class . ':handle');


    // API v1
    $app->group('/api/v1', function ($group) {
        // Auth
        $group->post('/auth/register', \App\Controllers\AuthController::class . ':register');
        $group->post('/auth/login',    \App\Controllers\AuthController::class . ':login');
    });
};