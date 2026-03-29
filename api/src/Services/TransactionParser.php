<?php

declare(strict_types=1);

namespace App\Services;

class TransactionParser
{
    public function parse(string $text): array
    {
        $lines  = explode("\n", trim($text));
        $date   = date('Y-m-d');
        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Cek tanggal
            if (preg_match('/^(\d{1,2})\/(\d{1,2})(?:\/(\d{4}))?$/', $line, $m)) {
                $year = $m[3] ?? date('Y');
                $date = sprintf('%s-%02d-%02d', $year, $m[2], $m[1]);
                continue;
            }

            // Cek transfer: "transfer 500rb bni ke cash" atau "tf 500rb bni ke cash"
            if (preg_match('/^(?:transfer|tf)\s+([\d.,]+(?:rb|k|jt|m|perak)?)\s+(\w+)(?:\s+(?:ke|-|→)\s+|\s+)(\w+)(?:\s+([\d.,]+(?:rb|k|jt|m|perak)?))?$/i', $line, $m)) {

                $amount   = $this->parseAmount($m[1]);
                $from     = strtolower(trim($m[2]));
                $to       = strtolower(trim($m[3]));
                $adminFee = isset($m[4]) && $m[4] !== '' ? $this->parseAdminFee($m[4]) : 0;

                // OUT dari wallet asal (jumlah transfer)
                $result[] = [
                    'description' => "Transfer ke {$m[3]}",
                    'amount'      => $amount,
                    'type'        => 'out',
                    'wallet'      => $from,
                    'date'        => $date,
                    'raw'         => $line,
                    'is_transfer' => true,
                ];

                // IN ke wallet tujuan
                $result[] = [
                    'description' => "Transfer dari {$m[2]}",
                    'amount'      => $amount,
                    'type'        => 'in',
                    'wallet'      => $to,
                    'date'        => $date,
                    'raw'         => $line,
                    'is_transfer' => true,
                ];

                // OUT biaya admin kalau ada
                if ($adminFee > 0) {
                    $result[] = [
                        'description' => "Biaya admin transfer ke {$m[3]}",
                        'amount'      => $adminFee,
                        'type'        => 'out',
                        'wallet'      => $from,
                        'date'        => $date,
                        'raw'         => "Biaya admin transfer ke {$m[3]}",
                        'is_transfer' => false,
                    ];
                }

                continue;
            }

            // Parse transaksi biasa
            if (preg_match('/^(.+?)\s+([\d.,]+(?:rb|k|jt|m|perak)?)\s*([+\-])\s*(\w[\w\s]*?)(?:\s+#([\w\s]+))?$/i', $line, $m)) {
                $result[] = [
                    'description' => trim($m[1]),
                    'amount'      => $this->parseAmount($m[2]),
                    'type'        => $m[3] === '+' ? 'in' : 'out',
                    'wallet'      => strtolower(trim($m[4])),
                    'date'        => $date,
                    'raw'         => $line,
                    'is_transfer' => false,
                    'category_tag' => isset($m[5]) ? strtolower(trim($m[5])) : null,
                ];
            }
        }

        return $result;
    }

    private function parseAmount(string $amount): int
    {
        $amount = strtolower(str_replace(',', '.', trim($amount)));

        if (preg_match('/^([\d.]+)(jt|m)$/', $amount, $m)) {
            return (int) ((float) $m[1] * 1_000_000);
        }

        if (preg_match('/^([\d.]+)(rb|k)$/', $amount, $m)) {
            return (int) ((float) $m[1] * 1_000);
        }

        if (preg_match('/^([\d.]+)perak$/', $amount, $m)) {
            return (int) (float) $m[1];
        }

        $numeric = (float) $amount;

        if ($numeric >= 10000) {
            return (int) $numeric;
        }

        return (int) ($numeric * 1_000);
    }

    private function parseAdminFee(string $amount): int
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

        // Ada suffix perak
        if (preg_match('/^([\d.]+)perak$/', $amount, $m)) {
            return (int) (float) $m[1];
        }

        // Default → literal (2500 = Rp 2.500)
        return (int) (float) $amount;
    }
}
