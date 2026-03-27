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
            $this->sendMessage(
                $chatId,
                "👋 Halo! Kamu belum terdaftar di Cateeeet.\n\n" .
                    "Cara daftar:\n" .
                    "1. Buka aplikasi web Cateeeet\n" .
                    "2. Daftar akun baru\n" .
                    "3. Masuk ke menu *Telegram*\n" .
                    "4. Masukkan Telegram ID kamu: `{$chatId}`\n\n" .
                    "Telegram ID kamu: *{$chatId}*\n\n" .
                    "Ketik /start untuk info lebih lanjut."
            );
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
            $this->sendMessage(
                $chatId,
                "❓ Format tidak dikenali.\n\n" .
                    "📌 Contoh transaksi:\n" .
                    "11/3\n" .
                    "makan siang 30 - bni\n" .
                    "gaji 5jt + bsi\n" .
                    "coffee 50 - bni #jajan\n\n" .
                    "🔄 Format transfer:\n" .
                    "tf 50rb bni ke cash\n" .
                    "tf 50rb bni cash 2500\n\n" .
                    "💡 Format nominal:\n" .
                    "30 → Rp 30.000\n" .
                    "30rb / 30k → Rp 30.000\n" .
                    "5jt → Rp 5.000.000\n" .
                    "500perak → Rp 500\n\n" .
                    "⌨️ Command:\n" .
                    "/saldo — cek semua saldo wallet\n" .
                    "/hari — transaksi hari ini\n" .
                    "/hari 26/3/2026 — transaksi tanggal tertentu\n" .
                    "/help — panduan lengkap"
            );
        } else {
            $reply = "✅ Transaksi tersimpan:\n\n";
            foreach ($parsed as $trx) {
                // Kategorisasi
                if ($trx['is_transfer']) {
                    $categoryId   = $this->getOrCreateTransferCategory($userId);
                    $categoryName = 'Transfer';
                } elseif (!empty($trx['category_tag'])) {
                    // Manual kategori via #tag
                    $cat = $matcher->matchByTag($userId, $trx['category_tag']);
                    $categoryId   = $cat['id'];
                    $categoryName = $cat['name'];
                } else {
                    $category     = $matcher->match($userId, $trx['description'], $trx['type']);
                    $categoryId   = $category['id'];
                    $categoryName = $category['name'];
                }

                $result = $saver->save($userId, $trx, $categoryId);
                $type   = $trx['type'] === 'in' ? '💰 Masuk' : '💸 Keluar';
                $amount = number_format((float) $trx['amount'], 0, ',', '.');

                if ($result['success']) {
                    $balance = number_format((float) $result['balance'], 0, ',', '.');
                    $days    = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                    $dayName = $days[date('w', strtotime($trx['date']))];
                    $tgl     = $dayName . ', ' . date('d/m/Y', strtotime($trx['date']));

                    $reply .= "{$type} Rp {$amount}\n";
                    $reply .= "📝 {$trx['description']}\n";
                    $reply .= "🏷️ {$categoryName}\n";
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

    private function getOrCreateTransferCategory(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM categories 
            WHERE user_id = ? AND LOWER(name) = 'transfer' 
            LIMIT 1
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $cat = $stmt->get_result()->fetch_assoc();

        if ($cat) return (int) $cat['id'];

        $stmt = $this->db->prepare("
            INSERT INTO categories (user_id, name, icon, type) VALUES (?, 'Transfer', '🔄', 'both')
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $this->db->insert_id;
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
            case '/start':
                $this->sendMessage(
                    $chatId,
                    "👋 Halo! Selamat datang di *Cateeeet* 💸\n\n" .
                        "Bot ini membantu kamu catat keuangan harian langsung dari Telegram.\n\n" .
                        "Untuk mulai, kamu perlu:\n" .
                        "1. Daftar akun di web\n" .
                        "2. Hubungkan Telegram ID kamu di menu Telegram\n\n" .
                        "Sudah punya akun? Langsung catat transaksi!\n\n" .
                        "Contoh:\n" .
                        "makan siang 30 - bni\n" .
                        "gaji 5jt + bsi\n\n" .
                        "Ketik /help untuk bantuan lebih lanjut."
                );
                break;

            case '/help':
                $this->sendMessage(
                    $chatId,
                    "📖 Panduan Cateeeet\n\n" .
                        "💬 Format transaksi:\n" .
                        "makan siang 30 - bni\n" .
                        "gaji 5jt + bsi\n\n" .
                        "🏷️ Kategori manual (#tag):\n" .
                        "coffee 50 - bni #jajan\n" .
                        "nasi padang 25 - cash #makan\n" .
                        "→ Bot akan cocokkan #tag ke kategori kamu\n\n" .
                        "👛 Nama wallet fleksibel:\n" .
                        "bensin 50 - bni pol\n" .
                        "→ 'bni pol' akan match ke 'BNI Polibatam'\n\n" .
                        "🔄 Format transfer:\n" .
                        "tf 50rb bni ke cash\n" .
                        "tf 50rb bni - cash\n" .
                        "tf 50rb bni cash\n" .
                        "tf 50rb bni cash 2500  ← dengan biaya admin\n\n" .
                        "📅 Format tanggal:\n" .
                        "26/3\n" .
                        "makan siang 30 - bni\n" .
                        "→ Tanpa tanggal = hari ini\n\n" .
                        "💡 Format nominal:\n" .
                        "30 → Rp 30.000\n" .
                        "30rb / 30k → Rp 30.000\n" .
                        "5jt → Rp 5.000.000\n" .
                        "500perak → Rp 500\n" .
                        "30600 → Rp 30.600 (literal)\n\n" .
                        "⌨️ Command:\n" .
                        "/saldo — cek semua saldo wallet\n" .
                        "/hari — transaksi hari ini\n" .
                        "/hari 26/3/2026 — transaksi tanggal tertentu\n" .
                        "/help — panduan ini"
                );
                break;

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
                $this->sendMessage(
                    $chatId,
                    "❓ Command tidak dikenal.\n\n" .
                        "⌨️ Command yang tersedia:\n" .
                        "/start — info & panduan singkat\n" .
                        "/help — panduan lengkap\n" .
                        "/saldo — cek semua saldo wallet\n" .
                        "/hari — transaksi hari ini\n" .
                        "/hari 26/3/2026 — transaksi tanggal tertentu\n\n" .
                        "💬 Format transaksi:\n" .
                        "makan siang 30 - bni\n" .
                        "gaji 5jt + bsi\n" .
                        "coffee 50 - bni #jajan\n\n" .
                        "🔄 Format transfer:\n" .
                        "tf 50rb bni ke cash\n" .
                        "tf 50rb bni cash 2500"
                );
        }
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
            SELECT t.description, t.amount, t.type, t.date, t.created_at, t.is_transfer,
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

        $days      = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $dayName   = $days[date('w', strtotime($date))];
        $dateLabel = $dayName . ', ' . date('d/m/Y', strtotime($date));
        $text      = "📅 Transaksi {$dateLabel}\n";
        $text     .= str_repeat("─", 20) . "\n";

        $totalIn  = 0.0;
        $totalOut = 0.0;
        $rows     = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
            if ((int) $row['is_transfer'] === 0) {
                if ($row['type'] === 'in') $totalIn  += (float) $row['amount'];
                else                       $totalOut += (float) $row['amount'];
            }
        }

        if (empty($rows)) {
            return "📅 Tidak ada transaksi pada {$dateLabel}.";
        }

        foreach ($rows as $row) {
            $icon       = $row['type'] === 'in' ? '💰' : '💸';
            $amount     = number_format((float) $row['amount'], 0, ',', '.');
            $time       = date('H:i:s', strtotime($row['created_at']));
            $transferTag = (int) $row['is_transfer'] === 1 ? ' 🔄' : '';

            $text .= "{$icon} {$row['description']}{$transferTag} — Rp {$amount}\n";
            $text .= "   🏷️ {$row['category_name']} | 👛 {$row['wallet_name']}\n";
            $text .= "   👤 {$row['input_by']} • 🕐 {$time}\n\n";
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
