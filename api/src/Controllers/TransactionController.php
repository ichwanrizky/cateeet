<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use Psr\Http\Message\ResponseInterface as Res;
use Psr\Http\Message\ServerRequestInterface as Req;

class TransactionController
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    // GET /transactions
    public function index(Req $request, Res $response): Res
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $page    = (int) ($params['page']  ?? 1);
        $limit   = (int) ($params['limit'] ?? 20);
        $offset  = ($page - 1) * $limit;
        $date    = $params['date']   ?? null;
        $month   = $params['month']  ?? null;
        $type    = $params['type']   ?? null;
        $wallet  = $params['wallet'] ?? null;

        $where       = ["(t.user_id = ? OR t.user_id IN (
            SELECT fm2.user_id FROM family_members fm1
            JOIN family_members fm2 ON fm2.family_id = fm1.family_id
            WHERE fm1.user_id = ? AND fm1.kicked_at IS NULL AND fm2.kicked_at IS NULL
        ))"];
        $params_bind = [$userId, $userId];
        $types       = 'ii';

        if ($date) {
            $where[]       = 't.date = ?';
            $params_bind[] = $date;
            $types        .= 's';
        }

        if ($month) {
            $where[]       = 'DATE_FORMAT(t.date, "%Y-%m") = ?';
            $params_bind[] = $month;
            $types        .= 's';
        }

        if ($type) {
            $where[]       = 't.type = ?';
            $params_bind[] = $type;
            $types        .= 's';
        }

        if ($wallet) {
            $where[]       = 'w.name = ?';
            $params_bind[] = $wallet;
            $types        .= 's';
        }

        $whereClause = implode(' AND ', $where);

        // Total count
        $countSql = "SELECT COUNT(*) as total FROM transactions t
                    JOIN wallets w ON w.id = t.wallet_id
                    WHERE {$whereClause}";
        $stmt     = $this->db->prepare($countSql);
        $stmt->bind_param($types, ...$params_bind);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];

        // Data
        $sql = "SELECT t.id, t.description, t.amount, t.type, t.date, t.created_at, t.is_transfer, t.is_balancing,
                    w.name as wallet_name,
                    c.name as category_name,
                    c.icon as category_icon,
                    u.display_name as input_by
                FROM transactions t
                JOIN wallets w ON w.id = t.wallet_id
                LEFT JOIN categories c ON c.id = t.category_id
                JOIN users u ON u.id = t.user_id
                WHERE {$whereClause}
                ORDER BY t.date DESC, t.created_at DESC
                LIMIT ? OFFSET ?";

        $params_bind[] = $limit;
        $params_bind[] = $offset;
        $types        .= 'ii';

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params_bind);
        $stmt->execute();
        $result = $stmt->get_result();

        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $row['amount']      = (float) $row['amount'];
            $row['is_transfer'] = (bool)  $row['is_transfer'];
            $row['is_balancing'] = (bool)  $row['is_balancing'];
            $transactions[]     = $row;
        }

        return Response::success($response, [
            'transactions' => $transactions,
            'pagination'   => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }
    
    // POST /transactions
    public function store(Req $request, Res $response): Res
    {
        $userId = $request->getAttribute('user_id');
        $body   = $request->getParsedBody();

        $description = trim($body['description'] ?? '');
        $amount      = (float) ($body['amount']      ?? 0);
        $type        = $body['type']        ?? '';
        $wallet_id   = (int) ($body['wallet_id']   ?? 0);
        $category_id = isset($body['category_id']) && $body['category_id'] !== null && $body['category_id'] !== ''
                        ? (int) $body['category_id']
                        : null;
        $date        = $body['date'] ?? date('Y-m-d');
        $is_transfer = (int) ($body['is_transfer'] ?? 0);

        if ($is_transfer && !$category_id) {
            $catName = $type === 'in' ? 'Transfer Masuk' : 'Transfer Keluar';
            $catIcon = $type === 'in' ? '📥' : '📤';
            $catType = $type;

            $stmt = $this->db->prepare("
                SELECT id FROM categories 
                WHERE user_id = ? AND LOWER(name) = LOWER(?) 
                LIMIT 1
            ");
            $stmt->bind_param('is', $userId, $catName);
            $stmt->execute();
            $cat = $stmt->get_result()->fetch_assoc();

            if ($cat) {
                $category_id = (int) $cat['id'];
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO categories (user_id, name, icon, type) VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param('isss', $userId, $catName, $catIcon, $catType);
                $stmt->execute();
                $category_id = $this->db->insert_id;
            }
        }

        if (!$description || !$amount || !in_array($type, ['in', 'out']) || !$wallet_id) {
            return Response::error($response, 'Data tidak lengkap.', 422);
        }

        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO transactions (user_id, wallet_id, category_id, description, amount, type, date, is_transfer)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iiisdssi', $userId, $wallet_id, $category_id, $description, $amount, $type, $date, $is_transfer);            $stmt->execute();
            $sql  = $type === 'in'
                ? "UPDATE wallets SET current_balance = current_balance + ? WHERE id = ?"
                : "UPDATE wallets SET current_balance = current_balance - ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('di', $amount, $wallet_id);
            $stmt->execute();

            $this->db->commit();

            return Response::message($response, 'Transaksi berhasil disimpan.', 201);

        } catch (\Exception $e) {
            $this->db->rollback();
            return Response::error($response, $e->getMessage(), 500);
        }
    }

    // PUT /transactions/{id}
    public function update(Req $request, Res $response, array $args): Res
    {
        $userId = $request->getAttribute('user_id');
        $id     = (int) $args['id'];
        $body   = $request->getParsedBody();

        // Cek transaksi milik user
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();

        if (!$old) {
            return Response::error($response, 'Transaksi tidak ditemukan.', 404);
        }

        $description = trim($body['description'] ?? $old['description']);
        $amount      = (float) ($body['amount']      ?? $old['amount']);
        $type        = $body['type']        ?? $old['type'];
        $wallet_id   = (int) ($body['wallet_id']   ?? $old['wallet_id']);
        $category_id = (int) ($body['category_id'] ?? $old['category_id']);
        $date        = $body['date']        ?? $old['date'];

        $this->db->begin_transaction();

        try {
            // Revert saldo lama
            $revert = $old['type'] === 'in'
                ? "UPDATE wallets SET current_balance = current_balance - ? WHERE id = ?"
                : "UPDATE wallets SET current_balance = current_balance + ? WHERE id = ?";
            $stmt = $this->db->prepare($revert);
            $oldAmount = (float) $old['amount'];
            $stmt->bind_param('di', $oldAmount, $old['wallet_id']);
            $stmt->execute();

            // Update transaksi
            $stmt = $this->db->prepare("
                UPDATE transactions
                SET description = ?, amount = ?, type = ?, wallet_id = ?, category_id = ?, date = ?
                WHERE id = ?
            ");
            $nullCategory = $category_id ?: null;
            $stmt->bind_param('sdsiisi', $description, $amount, $type, $wallet_id, $nullCategory, $date, $id);
            $stmt->execute();

            // Apply saldo baru
            $apply = $type === 'in'
                ? "UPDATE wallets SET current_balance = current_balance + ? WHERE id = ?"
                : "UPDATE wallets SET current_balance = current_balance - ? WHERE id = ?";
            $stmt = $this->db->prepare($apply);
            $stmt->bind_param('di', $amount, $wallet_id);
            $stmt->execute();

            $this->db->commit();

            return Response::message($response, 'Transaksi berhasil diupdate.');

        } catch (\Exception $e) {
            $this->db->rollback();
            return Response::error($response, $e->getMessage(), 500);
        }
    }

    // DELETE /transactions/{id}
    public function destroy(Req $request, Res $response, array $args): Res
    {
        $userId = $request->getAttribute('user_id');
        $id     = (int) $args['id'];

        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $trx = $stmt->get_result()->fetch_assoc();

        if (!$trx) {
            return Response::error($response, 'Transaksi tidak ditemukan.', 404);
        }

        $this->db->begin_transaction();

        try {
            // Revert saldo
            $revert = $trx['type'] === 'in'
                ? "UPDATE wallets SET current_balance = current_balance - ? WHERE id = ?"
                : "UPDATE wallets SET current_balance = current_balance + ? WHERE id = ?";
            $stmt = $this->db->prepare($revert);
            $amount = (float) $trx['amount'];
            $stmt->bind_param('di', $amount, $trx['wallet_id']);
            $stmt->execute();

            // Delete
            $stmt = $this->db->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            $this->db->commit();

            return Response::message($response, 'Transaksi berhasil dihapus.');

        } catch (\Exception $e) {
            $this->db->rollback();
            return Response::error($response, $e->getMessage(), 500);
        }
    }


}