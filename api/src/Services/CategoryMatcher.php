<?php

declare(strict_types=1);

namespace App\Services;

class CategoryMatcher
{
    private \mysqli $db;

    // Keyword mapping — tambah sendiri sesuai kebutuhan
    private array $keywords = [
        'makan'     => 'Makan & Minum',
        'minum'     => 'Makan & Minum',
        'lunch'     => 'Makan & Minum',
        'dinner'    => 'Makan & Minum',
        'breakfast' => 'Makan & Minum',
        'kopi'      => 'Makan & Minum',
        'cafe'      => 'Makan & Minum',
        'resto'     => 'Makan & Minum',
        'warung'    => 'Makan & Minum',
        'jajan'     => 'Makan & Minum',
        'snack'     => 'Makan & Minum',
        'bensin'    => 'Transport',
        'parkir'    => 'Transport',
        'grab'      => 'Transport',
        'gojek'     => 'Transport',
        'ojek'      => 'Transport',
        'bus'       => 'Transport',
        'taxi'      => 'Transport',
        'toll'      => 'Transport',
        'tol'       => 'Transport',
        'listrik'   => 'Tagihan',
        'air'       => 'Tagihan',
        'internet'  => 'Tagihan',
        'wifi'      => 'Tagihan',
        'pulsa'     => 'Tagihan',
        'token'     => 'Tagihan',
        'bpjs'      => 'Kesehatan',
        'obat'      => 'Kesehatan',
        'dokter'    => 'Kesehatan',
        'apotek'    => 'Kesehatan',
        'rumah sakit' => 'Kesehatan',
        'belanja'   => 'Belanja',
        'shopee'    => 'Belanja',
        'tokopedia' => 'Belanja',
        'lazada'    => 'Belanja',
        'indomaret' => 'Belanja',
        'alfamart'  => 'Belanja',
        'gaji'      => 'Gaji Polibatam',
        'salary'    => 'Gaji Polibatam',
        'transfer'  => 'Transfer Masuk',
        'kirim'     => 'Transfer Masuk',
        'bonus'     => 'Bonus',
        'hiburan'   => 'Hiburan',
        'netflix'   => 'Hiburan',
        'spotify'   => 'Hiburan',
        'game'      => 'Hiburan',
        'bioskop'   => 'Hiburan',
        'investasi' => 'Investasi',
        'saham'     => 'Investasi',
        'nabung'    => 'Tabungan',
        'tabung'    => 'Tabungan',
    ];

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function match(int $userId, string $description, string $type): array
    {
        $descLower  = strtolower($description);
        $categories = $this->getUserCategories($userId, $type);

        // Step 1: Full name match — cek apakah nama kategori ada dalam deskripsi
        foreach ($categories as $cat) {
            $catName = strtolower($cat['name']);
            if (str_contains($descLower, $catName)) {
                return ['id' => $cat['id'], 'name' => $cat['name'], 'is_new' => false];
            }
        }

        // Step 2: Partial word match — cek per kata dari deskripsi ke nama kategori
        $descWords = explode(' ', $descLower);
        foreach ($descWords as $word) {
            if (strlen($word) < 3) continue;
            foreach ($categories as $cat) {
                $catWords = explode(' ', strtolower($cat['name']));
                foreach ($catWords as $catWord) {
                    if (strlen($catWord) >= 3 && $word === $catWord) {
                        return ['id' => $cat['id'], 'name' => $cat['name'], 'is_new' => false];
                    }
                }
            }
        }

        // Step 3: Keyword mapping
        foreach ($this->keywords as $keyword => $categoryName) {
            if (str_contains($descLower, $keyword)) {
                foreach ($categories as $cat) {
                    if (strtolower($cat['name']) === strtolower($categoryName)) {
                        return ['id' => $cat['id'], 'name' => $cat['name'], 'is_new' => false];
                    }
                }
            }
        }

        // Step 4: Fallback "Lainnya"
        foreach ($categories as $cat) {
            if (strtolower($cat['name']) === 'lainnya') {
                return ['id' => $cat['id'], 'name' => $cat['name'], 'is_new' => false];
            }
        }

        // Insert "Lainnya" kalau belum ada
        $icon = $type === 'in' ? '💰' : '📦';
        $stmt = $this->db->prepare("INSERT INTO categories (user_id, name, icon, type) VALUES (?, 'Lainnya', ?, ?)");
        $stmt->bind_param('iss', $userId, $icon, $type);
        $stmt->execute();

        return ['id' => $this->db->insert_id, 'name' => 'Lainnya', 'is_new' => false];
    }

    private function getUserCategories(int $userId, string $type): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT c.id, c.name 
            FROM categories c
            WHERE c.user_id = ? AND (c.type = ? OR c.type = 'both')
            
            UNION
            
            SELECT DISTINCT c.id, c.name
            FROM categories c
            JOIN family_members fm1 ON fm1.user_id = c.user_id
            JOIN family_members fm2 ON fm2.family_id = fm1.family_id
            WHERE fm2.user_id = ?
            AND c.shared_to_family = 1
            AND (c.type = ? OR c.type = 'both')
            AND fm1.kicked_at IS NULL
            AND fm2.kicked_at IS NULL
        ");
        $stmt->bind_param('isis', $userId, $type, $userId, $type);
        $stmt->execute();
        $result = $stmt->get_result();

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        return $categories;
    }

    public function matchByTag(int $userId, string $tag): array
    {
        // Cari kategori by nama
        $stmt = $this->db->prepare("
        SELECT id, name, icon FROM categories
        WHERE user_id = ? AND LOWER(name) LIKE LOWER(?)
        LIMIT 1
    ");
        $search = "%{$tag}%";
        $stmt->bind_param('is', $userId, $search);
        $stmt->execute();
        $cat = $stmt->get_result()->fetch_assoc();
        if ($cat) return $cat;

        // Fallback ke match biasa
        return $this->match($userId, $tag, 'out');
    }
}
