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

        $is_transfer = (int) ($trx['is_transfer'] ?? 0);

        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO transactions (user_id, wallet_id, category_id, description, amount, type, date, raw_text, is_transfer)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'iiisdsssi',
                $userId,
                $wallet['id'],
                $categoryId,
                $trx['description'],
                $trx['amount'],
                $trx['type'],
                $trx['date'],
                $trx['raw'],
                $is_transfer
            );
            $stmt->execute();

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

    private function findWallet(int $userId, string $name): ?array
    {
        // Exact match dulu
        $stmt = $this->db->prepare("
        SELECT id, name, current_balance 
        FROM wallets 
        WHERE user_id = ? AND LOWER(name) = LOWER(?)
        LIMIT 1
    ");
        $stmt->bind_param('is', $userId, $name);
        $stmt->execute();
        $wallet = $stmt->get_result()->fetch_assoc();
        if ($wallet) return $wallet;

        // Partial match — nama wallet mengandung keyword atau keyword mengandung nama wallet
        $stmt = $this->db->prepare("
        SELECT id, name, current_balance 
        FROM wallets 
        WHERE user_id = ? AND (
            LOWER(name) LIKE LOWER(?) OR
            LOWER(?) LIKE CONCAT('%', LOWER(name), '%')
        )
        LIMIT 1
    ");
        $search = "%{$name}%";
        $stmt->bind_param('iss', $userId, $search, $name);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private function getWalletBalance(int $walletId): float
    {
        $stmt = $this->db->prepare("SELECT current_balance FROM wallets WHERE id = ?");
        $stmt->bind_param('i', $walletId);
        $stmt->execute();
        return (float) $stmt->get_result()->fetch_assoc()['current_balance'];
    }
}
