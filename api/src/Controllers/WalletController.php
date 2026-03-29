<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use Psr\Http\Message\ResponseInterface as Res;
use Psr\Http\Message\ServerRequestInterface as Req;

class WalletController
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function index(Req $request, Res $response): Res
    {
        $userId = $request->getAttribute('user_id');

        $stmt = $this->db->prepare("
            SELECT DISTINCT w.id, w.user_id, w.name, w.initial_balance, w.current_balance, w.shared_to_family, w.created_at
            FROM wallets w
            WHERE w.user_id = ?

            UNION

            SELECT DISTINCT w.id, w.user_id, w.name, w.initial_balance, w.current_balance, w.shared_to_family, w.created_at
            FROM wallets w
            JOIN family_members fm1 ON fm1.user_id = w.user_id
            JOIN family_members fm2 ON fm2.family_id = fm1.family_id
            WHERE fm2.user_id = ?
            AND w.shared_to_family = 1
            AND fm1.kicked_at IS NULL
            AND fm2.kicked_at IS NULL

            ORDER BY name
        ");
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $wallets = [];
        while ($row = $result->fetch_assoc()) {
            $row['initial_balance']  = (float) $row['initial_balance'];
            $row['current_balance']  = (float) $row['current_balance'];
            $row['shared_to_family'] = (bool)  $row['shared_to_family'];
            $wallets[] = $row;
        }

        return Response::success($response, ['wallets' => $wallets]);
    }

    public function store(Req $request, Res $response): Res
    {
        $userId = $request->getAttribute('user_id');
        $body   = $request->getParsedBody();

        $name            = trim($body['name']            ?? '');
        $initial_balance = (float) ($body['initial_balance'] ?? 0);
        $shared          = (int)   ($body['shared_to_family'] ?? 0);

        if (!$name) {
            return Response::error($response, 'Nama wallet wajib diisi.', 422);
        }

        $stmt = $this->db->prepare("
            INSERT INTO wallets (user_id, name, initial_balance, current_balance, shared_to_family)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isddi', $userId, $name, $initial_balance, $initial_balance, $shared);
        $stmt->execute();

        return Response::message($response, 'Wallet berhasil dibuat.', 201);
    }


    public function update(Req $request, Res $response, array $args): Res
    {
        $userId = $request->getAttribute('user_id');
        $id     = (int) $args['id'];
        $body   = $request->getParsedBody();

        $stmt = $this->db->prepare("SELECT * FROM wallets WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $wallet = $stmt->get_result()->fetch_assoc();

        if (!$wallet) {
            return Response::error($response, 'Wallet tidak ditemukan.', 404);
        }

        $name            = trim($body['name']            ?? $wallet['name']);
        $shared          = (int) ($body['shared_to_family'] ?? $wallet['shared_to_family']);
        $newBalance      = isset($body['current_balance'])
                            ? (float) $body['current_balance']
                            : null;

        if (!$name) {
            return Response::error($response, 'Nama wallet wajib diisi.', 422);
        }

        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare("UPDATE wallets SET name = ?, shared_to_family = ? WHERE id = ?");
            $stmt->bind_param('sii', $name, $shared, $id);
            $stmt->execute();

            // Kalau ada perubahan saldo
            if ($newBalance !== null) {
                $currentBalance = (float) $wallet['current_balance'];
                $diff           = $newBalance - $currentBalance;

                if ($diff != 0) {
                    $type        = $diff > 0 ? 'in' : 'out';
                    $amount      = abs($diff);
                    $description = 'Balancing';
                    $date        = date('Y-m-d');
                    
                    // Insert balancing transaction
                    $stmt = $this->db->prepare("
                        INSERT INTO transactions (user_id, wallet_id, category_id, description, amount, type, date, is_transfer, is_balancing)
                        VALUES (?, ?, NULL, 'Balancing', ?, ?, CURDATE(), 0, 1)
                    ");
                    $stmt->bind_param('iids', $userId, $wallet_id, $diffAmount, $diffType);
                    $stmt->execute();

                    $stmt = $this->db->prepare("UPDATE wallets SET current_balance = ? WHERE id = ?");
                    $stmt->bind_param('di', $newBalance, $id);
                    $stmt->execute();
                }
            }

            $this->db->commit();
            return Response::message($response, 'Wallet berhasil diupdate.');

        } catch (\Exception $e) {
            $this->db->rollback();
            return Response::error($response, $e->getMessage(), 500);
        }
    }

    public function destroy(Req $request, Res $response, array $args): Res
    {
        $userId = $request->getAttribute('user_id');
        $id     = (int) $args['id'];

        $stmt = $this->db->prepare("SELECT id FROM wallets WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            return Response::error($response, 'Wallet tidak ditemukan.', 404);
        }

        // Cek ada transaksi tidak
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM transactions WHERE wallet_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];

        if ($total > 0) {
            return Response::error($response, 'Wallet tidak bisa dihapus karena masih ada transaksi.', 422);
        }

        $stmt = $this->db->prepare("DELETE FROM wallets WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return Response::message($response, 'Wallet berhasil dihapus.');
    }
}