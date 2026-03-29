<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use Psr\Http\Message\ResponseInterface as Res;
use Psr\Http\Message\ServerRequestInterface as Req;

class AuthController
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function register(Req $request, Res $response): Res
    {
        $body = $request->getParsedBody();

        $username     = trim($body['username']     ?? '');
        $display_name = trim($body['display_name'] ?? '');
        $email        = trim($body['email']        ?? '');
        $password     = trim($body['password']     ?? '');

        // Validasi
        if (!$username || !$display_name || !$password) {
            return Response::error($response, 'Username, nama, dan password wajib diisi.', 422);
        }

        if (strlen($password) < 6) {
            return Response::error($response, 'Password minimal 6 karakter.', 422);
        }

        // Cek username sudah ada
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return Response::error($response, 'Username sudah dipakai.', 422);
        }

        // Cek email sudah ada
        if ($email) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return Response::error($response, 'Email sudah dipakai.', 422);
            }
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Insert user
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password_hash, display_name, email)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('ssss', $username, $password_hash, $display_name, $email);
        $stmt->execute();

        $newUserId = $this->db->insert_id;

        // Seed kategori default
        $this->seedDefaultCategories($newUserId);

        $userId = $this->db->insert_id;

        return Response::success($response, [
            'id'           => $userId,
            'username'     => $username,
            'display_name' => $display_name,
            'email'        => $email,
        ], 'Registrasi berhasil.', 201);
    }

    public function login(Req $request, Res $response): Res
    {
        $body = $request->getParsedBody();

        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');

        if (!$username || !$password) {
            return Response::error($response, 'Username dan password wajib diisi.', 422);
        }

        // Cari user
        if (str_contains($username, '@')) {
            $stmt = $this->db->prepare("
                SELECT id, username, password_hash, display_name, email
                FROM users WHERE email = ?
            ");
        } else {
            $stmt = $this->db->prepare("
                SELECT id, username, password_hash, display_name, email
                FROM users WHERE username = ?
            ");
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            return Response::error($response, 'Username atau password salah.', 401);
        }

        // Verifikasi password
        if (!password_verify($password, $user['password_hash'])) {
            return Response::error($response, 'Username atau password salah.', 401);
        }

        // Generate JWT token
        $token = $this->generateToken($user);

        return Response::success($response, [
            'id'           => $user['id'],
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
            'email'        => $user['email'],
            'jwt' => $token,
        ], 'Login berhasil.');
    }

    private function generateToken(array $user): string
    {
        $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'sub' => $user['id'],
            'username' => $user['username'],
            'iat' => time(),
            'exp' => time() + (60 * 60) + (60 * 10), // 1 jam 10 menit
        ]));

        $secret    = $_ENV['JWT_SECRET'] ?? 'secret';
        $signature = base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));

        return "{$header}.{$payload}.{$signature}";
    }

    // GET /auth/me
    public function me(Req $request, Res $response): Res
    {
        $userId = $request->getAttribute('user_id');

        $stmt = $this->db->prepare("
            SELECT id, username, display_name, email, telegram_id FROM users WHERE id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        return Response::success($response, ['user' => $user]);
    }

    // PUT /auth/profile
    public function updateProfile(Req $request, Res $response): Res
    {
        $userId = $request->getAttribute('user_id');
        $body   = $request->getParsedBody();

        // Ambil data existing dulu
        $stmt = $this->db->prepare("SELECT display_name, email, telegram_id FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        $display_name = trim($body['display_name'] ?? $existing['display_name']);
        $email        = trim($body['email']        ?? $existing['email']);
        $telegram_id  = array_key_exists('telegram_id', $body)
                        ? ($body['telegram_id'] !== '' ? (int) $body['telegram_id'] : null)
                        : $existing['telegram_id'];

        // Cek email duplikat
        if ($email) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param('si', $email, $userId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return Response::error($response, 'Email sudah dipakai.', 422);
            }
        }

        // Cek telegram_id duplikat
        if ($telegram_id) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE telegram_id = ? AND id != ?");
            $stmt->bind_param('ii', $telegram_id, $userId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return Response::error($response, 'Telegram ID sudah dipakai akun lain.', 422);
            }
        }

        // ← TARUH DI SINI verifikasi Telegram
        if ($telegram_id) {
            $token   = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
            $url     = "https://api.telegram.org/bot{$token}/sendMessage";
            $payload = json_encode([
                'chat_id' => $telegram_id,
                'text'    => "✅ Akun kamu berhasil terhubung dengan Cateeeet!\n\nSekarang kamu bisa mulai catat transaksi langsung dari Telegram.",
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result     = curl_exec($ch);
            curl_close($ch);

            $tgResponse = json_decode($result, true);
            if (!$tgResponse['ok']) {
                return Response::error($response, 'Telegram ID tidak valid atau bot belum di-start. Pastikan kamu sudah tap Start di bot @MoneyTrackerWanBot terlebih dahulu.', 422);
            }
        }

        $stmt = $this->db->prepare("
            UPDATE users SET display_name = ?, email = ?, telegram_id = ? WHERE id = ?
        ");
        $stmt->bind_param('ssii', $display_name, $email, $telegram_id, $userId);
        $stmt->execute();

        return Response::message($response, 'Profile berhasil diupdate.');
    }

    // PUT /auth/password
    public function updatePassword(Req $request, Res $response): Res
    {
        $userId = $request->getAttribute('user_id');
        $body   = $request->getParsedBody();

        $old_password = $body['old_password'] ?? '';
        $new_password = $body['new_password'] ?? '';

        if (!$old_password || !$new_password) {
            return Response::error($response, 'Password lama dan baru wajib diisi.', 422);
        }

        if (strlen($new_password) < 6) {
            return Response::error($response, 'Password baru minimal 6 karakter.', 422);
        }

        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!password_verify($old_password, $user['password_hash'])) {
            return Response::error($response, 'Password lama tidak sesuai.', 422);
        }

        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();

        return Response::message($response, 'Password berhasil diupdate.');
    }

    private function seedDefaultCategories(int $userId): void
    {
        $categories = [
            // Pemasukan
            ['Gaji',            '💼', 'in'],
            ['Bonus',           '🎁', 'in'],
            ['Freelance',       '💻', 'in'],
            ['Transfer Masuk',  '📥', 'in'],
            ['Investasi Balik', '📈', 'in'],

            // Pengeluaran
            ['Makan & Minum',   '🍽️', 'out'],
            ['Transport',       '🚗', 'out'],
            ['Belanja',         '🛍️', 'out'],
            ['Tagihan',         '💡', 'out'],
            ['Kesehatan',       '💊', 'out'],
            ['Pendidikan',      '📚', 'out'],
            ['Hiburan',         '🎮', 'out'],
            ['Perawatan',       '💆', 'out'],
            ['Rumah',           '🏠', 'out'],
            ['Investasi',       '📊', 'out'],
            ['Tabungan',        '🏦', 'out'],
            ['Transfer Keluar', '📤', 'out'],
            ['Transfer',        '🔄', 'out'],
            ['Lainnya',         '🗂️', 'out'],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO categories (user_id, name, icon, type) VALUES (?, ?, ?, ?)
        ");

        foreach ($categories as [$name, $icon, $type]) {
            $stmt->bind_param('isss', $userId, $name, $icon, $type);
            $stmt->execute();
        }
    }
    
}