<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TelegramController
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function handle(Request $request, Response $response): Response
    {
        $secret       = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
        $headerSecret = $request->getHeaderLine('X-Telegram-Bot-Api-Secret-Token');

        if ($secret !== $headerSecret) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $body = $request->getParsedBody();

        if (!isset($body['message'])) {
            $response->getBody()->write(json_encode(['ok' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $message = $body['message'];
        $chatId  = $message['chat']['id'];
        $text    = $message['text'] ?? '';

        $user = $this->getUserByTelegramId($chatId);
        if (!$user) {
            $this->sendMessage($chatId, "Akun kamu belum terdaftar. Hubungi admin.");
            $response->getBody()->write(json_encode(['ok' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $userId = $user['id'];

        if (str_starts_with($text, '/')) {
            $this->handleCommand($chatId, $text, $userId);
            $response->getBody()->write(json_encode(['ok' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $parser  = new \App\Services\TransactionParser();
        $matcher = new \App\Services\CategoryMatcher($this->db);
        $saver   = new \App\Services\TransactionService($this->db);
        $parsed  = $parser->parse($text);

        if (empty($parsed)) {
            $this->sendMessage($chatId, "Format tidak dikenali. Contoh:\n\n11/3\nmakan siang 30rb - bni\ngaji 5jt + bsi");
        } else {
            $reply = "✅ Transaksi tersimpan:\n\n";
            foreach ($parsed as $trx) {
                $category = $matcher->match($userId, $trx['description'], $trx['type']);
                $result   = $saver->save($userId, $trx, $category['id']);
                $type     = $trx['type'] === 'in' ? '💰 Masuk' : '💸 Keluar';
                $amount   = number_format((float) $trx['amount'], 0, ',', '.');

                if ($result['success']) {
                    $balance = number_format((float) $result['balance'], 0, ',', '.');
                    $days    = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                    $dayName = $days[date('w', strtotime($trx['date']))];
                    $tgl     = $dayName . ', ' . date('d/m/Y', strtotime($trx['date']));

                    $reply .= "{$type} Rp {$amount}\n";
                    $reply .= "📝 {$trx['description']}\n";
                    $reply .= "🏷️ {$category['name']}\n";
                    $reply .= "👛 {$result['wallet']} (saldo: Rp {$balance})\n";
                    $reply .= "📅 {$tgl}\n\n";
                } else {
                    $reply .= "❌ {$trx['description']}: {$result['message']}\n\n";
                }
            }
            $this->sendMessage($chatId, $reply);
        }

        $response->getBody()->write(json_encode(['ok' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function sendMessage(int|string $chatId, string $text): void
    {
        $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        $url   = "https://api.telegram.org/bot{$token}/sendMessage";

        $payload = json_encode([
            'chat_id' => $chatId,
            'text'    => $text,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    private function getUserByTelegramId(int $telegramId): ?array
    {
        $stmt = $this->db->prepare("SELECT id, username, display_name FROM users WHERE telegram_id = ?");
        $stmt->bind_param('i', $telegramId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    private function handleCommand(int|string $chatId, string $text, int $userId): void
    {
        $parts   = explode(' ', trim($text));
        $command = strtolower($parts[0]);

        switch ($command) {
            case '/saldo':
                $this->sendMessage($chatId, $this->getSaldoText($userId));
                break;

            case '/hari':
                if (isset($parts[1])) {
                    $date = $this->parseDate($parts[1]);
                    if (!$date) {
                        $this->sendMessage($chatId, "Format tanggal salah. Gunakan: /hari 25/3/2026");
                        break;
                    }
                } else {
                    $date = date('Y-m-d');
                }
                $this->sendMessage($chatId, $this->getTransaksiHariText($userId, $date));
                break;

            default:
                $this->sendMessage($chatId, "Command tidak dikenal.\n\nCommand yang tersedia:\n/saldo — lihat semua saldo wallet\n/hari — transaksi hari ini\n/hari 25/3/2026 — transaksi tanggal tertentu");        }
    }

    private function getSaldoText(int $userId): string
    {
        $stmt = $this->db->prepare("
            SELECT name, current_balance 
            FROM wallets 
            WHERE user_id = ? 
            ORDER BY name
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $text  = "💳 Saldo Wallet\n";
        $text .= str_repeat("─", 20) . "\n";
        $total = 0.0;

        while ($row = $result->fetch_assoc()) {
            $balance = number_format((float) $row['current_balance'], 0, ',', '.');
            $text   .= "👛 {$row['name']}: Rp {$balance}\n";
            $total  += (float) $row['current_balance'];
        }

        $text .= str_repeat("─", 20) . "\n";
        $text .= "💰 Total: Rp " . number_format($total, 0, ',', '.');

        return $text;
    }

    private function getTransaksiHariText(int $userId, string $date): string
    {
        $stmt = $this->db->prepare("
            SELECT t.description, t.amount, t.type, t.date, t.created_at,
                w.name as wallet_name,
                c.name as category_name,
                u.display_name as input_by
            FROM transactions t
            JOIN wallets w ON w.id = t.wallet_id
            LEFT JOIN categories c ON c.id = t.category_id
            JOIN users u ON u.id = t.user_id
            WHERE t.date = ?
            AND (
                t.user_id = ?
                OR t.user_id IN (
                    SELECT fm2.user_id
                    FROM family_members fm1
                    JOIN family_members fm2 ON fm2.family_id = fm1.family_id
                    WHERE fm1.user_id = ?
                        AND fm1.kicked_at IS NULL
                        AND fm2.kicked_at IS NULL
                )
            )
            ORDER BY t.created_at DESC
        ");
        $stmt->bind_param('sii', $date, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $dayName   = $days[date('w', strtotime($date))];
        $dateLabel = $dayName . ', ' . date('d/m/Y', strtotime($date));
        $text      = "📅 Transaksi {$dateLabel}\n";
        $text     .= str_repeat("─", 20) . "\n";

        $totalIn  = 0.0;
        $totalOut = 0.0;
        $rows     = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
            if ($row['type'] === 'in') $totalIn  += (float) $row['amount'];
            else                       $totalOut += (float) $row['amount'];
        }

        if (empty($rows)) {
            return "📅 Tidak ada transaksi pada {$dateLabel}.";
        }

        foreach ($rows as $row) {
            $icon      = $row['type'] === 'in' ? '💰' : '💸';
            $amount    = number_format((float) $row['amount'], 0, ',', '.');
            $time      = date('H:i:s', strtotime($row['created_at']));

            $inputBy   = $row['input_by'];

            $text .= "{$icon} {$row['description']} — Rp {$amount}\n";
            $text .= "   🏷️ {$row['category_name']} | 👛 {$row['wallet_name']}\n";
            $text .= "   👤 {$inputBy} • 🕐 {$time}\n\n";
        }

        $text .= str_repeat("─", 20) . "\n";
        $text .= "💰 Masuk : Rp " . number_format($totalIn,  0, ',', '.') . "\n";
        $text .= "💸 Keluar: Rp " . number_format($totalOut, 0, ',', '.') . "\n";
        $text .= "📊 Selisih: Rp " . number_format($totalIn - $totalOut, 0, ',', '.');

        return $text;
    }

    private function parseDate(string $input): ?string
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $input, $m)) {
            return sprintf('%s-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        return null;
    }
}