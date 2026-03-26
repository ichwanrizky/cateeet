<?php

declare(strict_types=1);

namespace App\Services;

class TransactionParser
{
    /**
     * Parse teks dari Telegram menjadi array transaksi
     *
     * Format input:
     * 11/3
     * makan siang 30rb - bni
     * gaji 5jt + bsi
     */
    public function parse(string $text): array
    {
        $lines  = explode("\n", trim($text));
        $date   = date('Y-m-d'); // default hari ini
        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Cek apakah baris ini adalah tanggal (format: 11/3 atau 11/3/2026)
            if (preg_match('/^(\d{1,2})\/(\d{1,2})(?:\/(\d{4}))?$/', $line, $m)) {
                $year = $m[3] ?? date('Y');
                $date = sprintf('%s-%02d-%02d', $year, $m[2], $m[1]);
                continue;
            }

            // Parse transaksi: "deskripsi amount +/- wallet"
            // Contoh: "makan siang 30rb - bni"
            // Contoh: "gaji 5jt + bsi"
            if (preg_match('/^(.+?)\s+([\d.,]+(?:rb|k|jt|m)?)\s*([+\-])\s*(\w+)$/i', $line, $m)) {
                $result[] = [
                    'description' => trim($m[1]),
                    'amount'      => $this->parseAmount($m[2]),
                    'type'        => $m[3] === '+' ? 'in' : 'out',
                    'wallet'      => strtolower(trim($m[4])),
                    'date'        => $date,
                    'raw'         => $line,
                ];
            }
        }

        return $result;
    }

    /**
     * Convert amount string ke integer
     * 30rb → 30000
     * 5jt  → 5000000
     * 1.5jt → 1500000
     * 500k → 500000
     */
    private function parseAmount(string $amount): int
    {
        $amount = strtolower(str_replace(',', '.', trim($amount)));

        // Ada suffix jt/m
        if (preg_match('/^([\d.]+)(jt|m)$/', $amount, $m)) {
            return (int) ((float) $m[1] * 1_000_000);
        }

        // Ada suffix rb/k
        if (preg_match('/^([\d.]+)(rb|k)$/', $amount, $m)) {
            return (int) ((float) $m[1] * 1_000);
        }

        // Ada suffix perak (literal)
        if (preg_match('/^([\d.]+)perak$/', $amount, $m)) {
            return (int) (float) $m[1];
        }

        $numeric = (float) $amount;

        // 5 digit ke atas → literal
        if ($numeric >= 10000) {
            return (int) $numeric;
        }

        // Default × 1000
        return (int) ($numeric * 1_000);
    }
}