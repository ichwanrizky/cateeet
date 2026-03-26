<?php

declare(strict_types=1);

namespace App\Services;

class TransactionService
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function save(int $userId, array $trx, int $categoryId): array
    {
        // Cari wallet berdasarkan nama & user_id
        $wallet = $this->findWallet($userId, $trx['wallet']);

        if (!$wallet) {
            return ['success' => false, 'message' => "Wallet '{$trx['wallet']}' tidak ditemukan."];
        }

        // Begin transaction
        $this->db->begin_transaction();

        try {
            // Insert transaksi
            $stmt = $this->db->prepare("
                INSERT INTO transactions (user_id, wallet_id, category_id, description, amount, type, date, raw_text)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'iiisdsss',
                $userId,
                $wallet['id'],
                $categoryId,
                $trx['description'],
                $trx['amount'],
                $trx['type'],
                $trx['date'],
                $trx['raw']
            );
            $stmt->execute();

            // Update saldo wallet
            if ($trx['type'] === 'in') {
                $sql = "UPDATE wallets SET current_balance = current_balance + ? WHERE id = ?";
            } else {
                $sql = "UPDATE wallets SET current_balance = current_balance - ? WHERE id = ?";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('di', $trx['amount'], $wallet['id']);
            $stmt->execute();

            $this->db->commit();

            return [
                'success' => true,
                'wallet'  => $wallet['name'],
                'balance' => $this->getWalletBalance($wallet['id']),
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function findWallet(int $userId, string $walletName): ?array
    {
        // Cari wallet milik user
        $stmt = $this->db->prepare("
            SELECT id, name, current_balance 
            FROM wallets 
            WHERE user_id = ? AND LOWER(name) = LOWER(?)
        ");
        $stmt->bind_param('is', $userId, $walletName);
        $stmt->execute();
        $result = $stmt->get_result();
        $wallet = $result->fetch_assoc();

        if ($wallet) return $wallet;

        // Cari wallet family yang di-share
        $stmt = $this->db->prepare("
            SELECT w.id, w.name, w.current_balance
            FROM wallets w
            JOIN family_members fm ON fm.user_id = w.user_id
            JOIN family_members fm2 ON fm2.family_id = fm.family_id
            WHERE fm2.user_id = ?
              AND LOWER(w.name) = LOWER(?)
              AND w.shared_to_family = 1
              AND fm.kicked_at IS NULL
              AND fm2.kicked_at IS NULL
        ");
        $stmt->bind_param('is', $userId, $walletName);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    private function getWalletBalance(int $walletId): float
    {
        $stmt = $this->db->prepare("SELECT current_balance FROM wallets WHERE id = ?");
        $stmt->bind_param('i', $walletId);
        $stmt->execute();
        return (float) $stmt->get_result()->fetch_assoc()['current_balance'];
    }
}