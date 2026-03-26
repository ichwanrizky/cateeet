<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ResponseInterface;

class Response
{
    public static function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public static function success(ResponseInterface $response, mixed $data = null, string $message = 'Success', int $status = 200): ResponseInterface
    {
        return self::json($response, [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function error(ResponseInterface $response, string $message, int $status = 400, mixed $errors = null): ResponseInterface
    {
        return self::json($response, [
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}