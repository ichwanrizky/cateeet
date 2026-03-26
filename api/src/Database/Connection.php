<?php

declare(strict_types=1);

namespace App\Database;

class Connection
{
    public static function make(): \mysqli
    {
        $host     = $_ENV['DB_HOST']     ?? 'db';
        $port     = (int) ($_ENV['DB_PORT'] ?? 3306);
        $name     = $_ENV['DB_NAME']     ?? 'money_tracker';
        $user     = $_ENV['DB_USER']     ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        $conn = new \mysqli($host, $user, $password, $name, $port);

        if ($conn->connect_error) {
            throw new \RuntimeException('DB connection failed: ' . $conn->connect_error);
        }

        $conn->set_charset('utf8mb4');

        return $conn;
    }
}