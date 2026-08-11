<?php
/**
 * SupabaseClient — pengganti getDB() lama.
 *
 * Dulu: $db = getDB(); $db->prepare("SELECT ...")->execute([...]);
 * Sekarang: $sb = getSupabase(); $sb->select('users', ['id' => $id]);
 *
 * Semua request lewat cURL ke Supabase REST API (PostgREST) via HTTPS,
 * karena InfinityFree memblokir koneksi database langsung ke luar server mereka.
 *
 * PENTING: pakai service_role key di sini (bukan anon key), karena PHP ini
 * jalan di server (bukan browser). service_role key WAJIB tetap rahasia —
 * simpan hanya di config/.env, jangan pernah di-commit ke git atau dikirim
 * ke client/browser.
 */

class SupabaseException extends Exception {}

class SupabaseClient
{
    private string $baseUrl;
    private string $serviceKey;

    public function __construct(string $baseUrl, string $serviceKey)
    {
        // Rapikan trailing slash biar konsisten
        $this->baseUrl    = rtrim($baseUrl, '/');
        $this->serviceKey = $serviceKey;
    }

    // Operator PostgREST yang valid di depan sebuah filter, mis. "eq.", "lt.", "is."
    private const FILTER_OPERATORS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'like', 'ilike', 'is', 'in', 'not'];

    /**
     * Ubah value filter jadi format PostgREST yang benar.
     * PENTING: dulu pakai cek "mengandung titik" buat deteksi operator manual,
     * itu BUG — email seperti "admin@dummy.com" juga mengandung titik dan
     * kesalah-deteksi sebagai filter mentah. Sekarang cek prefix operator
     * yang benar-benar valid pakai regex, baru dianggap "sudah ada operator".
     */
    private function formatFilterValue($val): string
    {
        $val = (string)$val;
        $pattern = '/^(' . implode('|', self::FILTER_OPERATORS) . ')\./';
        if (preg_match($pattern, $val)) {
            return $val; // sudah dalam format "operator.value", pakai apa adanya
        }
        return 'eq.' . $val; // default: exact match
    }

    // ── SELECT ──────────────────────────────────────────────────────────────
    /**
     * Ambil data dari sebuah tabel.
     *
     * @param string $table   Nama tabel, mis. 'users'
     * @param array  $filters Filter kolom. Default operator = eq (sama dengan).
     *                        Contoh: ['id' => 5] artinya WHERE id = 5
     *                        Untuk operator lain, tulis manual:
     *                        ['exp' => 'lt.' . time()] artinya WHERE exp < time()
     *                        Operator yang didukung PostgREST: eq, neq, gt, gte, lt, lte, like, ilike, is
     * @param string $columns Kolom yang diambil, default semua ('*')
     * @param array  $options Opsi tambahan: 'limit' => int, 'order' => 'kolom.asc'/'kolom.desc'
     * @return array Array asosiatif hasil query (bisa kosong)
     */
    public function select(string $table, array $filters = [], string $columns = '*', array $options = []): array
    {
        $query = ['select' => $columns];
        foreach ($filters as $col => $val) {
            $query[$col] = $this->formatFilterValue($val);
        }
        if (!empty($options['limit'])) {
            $query['limit'] = $options['limit'];
        }
        if (!empty($options['order'])) {
            $query['order'] = $options['order'];
        }

        return $this->request('GET', $table, $query);
    }

    /**
     * Ambil satu baris saja. Return null kalau tidak ketemu.
     */
    public function selectOne(string $table, array $filters = [], string $columns = '*'): ?array
    {
        $rows = $this->select($table, $filters, $columns, ['limit' => 1]);
        return $rows[0] ?? null;
    }

    // ── INSERT ──────────────────────────────────────────────────────────────
    /**
     * Insert satu baris baru. Return baris yang baru dibuat (termasuk id auto-generated).
     *
     * @param array $data Data yang mau diinsert, mis. ['name' => 'Budi', 'email' => 'budi@mail.com']
     */
    public function insert(string $table, array $data, int $timeout = 10): array
    {
        $result = $this->request('POST', $table, [], $data, $timeout);
        return $result[0] ?? [];
    }

    // ── UPDATE ──────────────────────────────────────────────────────────────
    /**
     * Update baris yang cocok dengan filter. Return baris-baris yang diupdate.
     *
     * @param array $filters Sama seperti di select(). WAJIB diisi — kalau kosong,
     *                        request akan ditolak sengaja supaya tidak update seluruh tabel.
     */
    public function update(string $table, array $filters, array $data): array
    {
        if (empty($filters)) {
            throw new SupabaseException("update() butuh minimal 1 filter, supaya tidak update seluruh tabel '$table'.");
        }
        $query = [];
        foreach ($filters as $col => $val) {
            $query[$col] = $this->formatFilterValue($val);
        }
        return $this->request('PATCH', $table, $query, $data);
    }

    // ── DELETE ──────────────────────────────────────────────────────────────
    /**
     * Hapus baris yang cocok dengan filter.
     *
     * @param array $filters WAJIB diisi — sama alasannya dengan update().
     */
    public function delete(string $table, array $filters): array
    {
        if (empty($filters)) {
            throw new SupabaseException("delete() butuh minimal 1 filter, supaya tidak hapus seluruh tabel '$table'.");
        }
        $query = [];
        foreach ($filters as $col => $val) {
            $query[$col] = $this->formatFilterValue($val);
        }
        return $this->request('DELETE', $table, $query);
    }

    // ── RPC (buat query yang butuh transaksi/logic gabungan) ───────────────
    /**
     * Panggil Postgres function yang sudah dibuat lewat SQL Editor Supabase.
     * Dipakai kalau ada operasi yang harus atomic (misal: cek + insert sekaligus),
     * karena REST API biasa tidak mendukung multi-statement transaction seperti PDO.
     */
    public function rpc(string $functionName, array $params = []): array
    {
        return $this->request('POST', 'rpc/' . $functionName, [], $params);
    }

    // ── SELECT tingkat lanjut ────────────────────────────────────────────────
    /**
     * Dipakai untuk kasus yang tidak bisa dicover select() biasa:
     * - pencarian OR di banyak kolom sekaligus (mis. search box admin logs)
     * - filter yang sama kolomnya dipakai 2x (mis. rentang tanggal: created_at >= X AND created_at < Y)
     * - butuh total jumlah baris buat pagination (COUNT(*) versi MySQL)
     *
     * @param string   $table
     * @param string[] $rawFilters  Array string query PostgREST siap pakai, SUDAH di-encode
     *                               sendiri oleh caller. Contoh:
     *                               ["ip_address=ilike." . rawurlencode('*182*'),
     *                                "created_at=gte." . rawurlencode('2026-01-01T00:00:00'),
     *                                "or=(" . "action.ilike." . rawurlencode('*login*') . ")"]
     *                               Dipisah jadi array (bukan assoc array) khusus supaya
     *                               kolom yang sama boleh muncul lebih dari sekali
     *                               (assoc array PHP tidak bisa punya key dobel).
     * @param bool     $withCount   Kalau true, kirim header "Prefer: count=exact" dan baca
     *                               total baris dari header response "Content-Range".
     * @return array{data: array, total: ?int}
     */
    public function selectRaw(
        string $table,
        array $rawFilters = [],
        string $columns = '*',
        ?int $limit = null,
        ?int $offset = null,
        ?string $order = null,
        bool $withCount = false
    ): array {
        $parts = ['select=' . rawurlencode($columns)];
        foreach ($rawFilters as $f) {
            $parts[] = $f;
        }
        if ($order !== null)  $parts[] = 'order=' . rawurlencode($order);
        if ($limit !== null)  $parts[] = 'limit=' . (int)$limit;
        if ($offset !== null) $parts[] = 'offset=' . (int)$offset;

        $url = $this->baseUrl . '/' . $table . '?' . implode('&', $parts);

        $headers = [
            'apikey: ' . $this->serviceKey,
            'Authorization: Bearer ' . $this->serviceKey,
            'Content-Type: application/json',
        ];
        if ($withCount) {
            $headers[] = 'Prefer: count=exact';
        }

        $responseHeaders = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $responseHeaders .= $header;
                return strlen($header);
            },
        ]);
        $body      = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            error_log("Supabase cURL error (selectRaw $table): $curlError");
            throw new SupabaseException("Gagal menghubungi Supabase: $curlError");
        }
        if ($httpCode >= 400) {
            error_log("Supabase error $httpCode (selectRaw $table): $body");
            throw new SupabaseException("Supabase menolak request ($httpCode): $body");
        }

        $data = $body === '' ? [] : json_decode($body, true);
        if (!is_array($data)) {
            $data = [];
        }

        $total = null;
        if ($withCount && preg_match('#Content-Range:\s*\S*/(\d+)#i', $responseHeaders, $m)) {
            $total = (int)$m[1];
        }

        return ['data' => $data, 'total' => $total];
    }

    // ── Inti pengiriman request ─────────────────────────────────────────────
    private function request(string $method, string $path, array $query = [], ?array $body = null, int $timeout = 10): array
    {
        $url = $this->baseUrl . '/' . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'apikey: ' . $this->serviceKey,
            'Authorization: Bearer ' . $this->serviceKey,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Supabase cURL error ($method $path): $curlError");
            throw new SupabaseException("Gagal menghubungi Supabase: $curlError");
        }

        if ($httpCode >= 400) {
            error_log("Supabase error $httpCode ($method $path): $response");
            throw new SupabaseException("Supabase menolak request ($httpCode): $response");
        }

        if ($response === '') {
            return [];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Supabase JSON decode error ($method $path): " . json_last_error_msg());
            throw new SupabaseException('Gagal membaca respons Supabase.');
        }

        // rpc() ke function yang return single value bisa balikin non-array
        return is_array($decoded) ? $decoded : [$decoded];
    }
}

/**
 * Helper global, dipakai kaya getDB() lama.
 * Baca SUPABASE_URL & SUPABASE_SERVICE_KEY dari config/.env.
 */
function getSupabase(): SupabaseClient
{
    static $client = null;
    if ($client === null) {
        if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY') || !SUPABASE_URL || !SUPABASE_SERVICE_KEY) {
            error_log('Supabase config missing — cek SUPABASE_URL & SUPABASE_SERVICE_KEY di config/.env');
            // Lempar exception agar caller bisa catch dan tetap render halaman HTML.
            // JANGAN die() atau http_response_code(500) di sini — kalau dipanggil dari
            // login.php, WebView akan mendapat ERR_HTTP_RESPONSE_CODE_FAILURE.
            throw new SupabaseException('Konfigurasi server tidak lengkap. Hubungi administrator.');
        }
        $client = new SupabaseClient(SUPABASE_URL, SUPABASE_SERVICE_KEY);
    }
    return $client;
}