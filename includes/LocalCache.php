<?php
/**
 * LocalCache.php — SQLite lokal sebagai fallback saat Supabase down.
 *
 * Dipakai untuk dua hal:
 *  1. Token blacklist (JTI) — fail-closed: kalau Supabase error, cek SQLite.
 *     Jika SQLite juga error, TOLAK token (bukan izinkan).
 *  2. Session/user cache — graceful degrade: kalau Supabase down,
 *     user yang sudah login masih bisa jalan dari cache SQLite.
 *
 * File SQLite disimpan di luar webroot (/tmp/gudeg_cache.sqlite).
 * InfinityFree mengizinkan tulis ke /tmp.
 */
class LocalCache
{
    private static ?PDO $pdo = null;
    private static string $db_path = '/tmp/gudeg_cache.sqlite';

    /**
     * Resolve path SQLite — dari CACHE_PATH di .env kalau ada,
     * fallback ke /tmp/gudeg_cache.sqlite.
     * Kalau CACHE_PATH tidak writable, fallback ke /tmp dengan log WARNING.
     */
    private static function resolvePath(): string
    {
        if (defined('CACHE_PATH') && CACHE_PATH !== '') {
            $dir = dirname(CACHE_PATH);
            if (is_dir($dir) && is_writable($dir)) {
                return CACHE_PATH;
            }
            // Dir tidak writable — fallback ke /tmp
            function_exists('gudeg_log')
                ? gudeg_log('WARNING', 'LocalCache::resolvePath', 'CACHE_PATH dir tidak writable (' . $dir . '), fallback ke /tmp')
                : error_log('LocalCache::resolvePath: CACHE_PATH dir tidak writable, fallback ke /tmp');
        }
        return self::$db_path;
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    private static function pdo(): PDO
    {
        if (self::$pdo !== null) return self::$pdo;

        self::$pdo = new PDO('sqlite:' . self::resolvePath());
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->exec('PRAGMA journal_mode=WAL');  // lebih tahan concurrent write
        self::$pdo->exec('PRAGMA synchronous=NORMAL');

        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS token_blacklist (
                jti     TEXT PRIMARY KEY,
                exp     INTEGER NOT NULL
            )
        ");

        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS user_cache (
                user_id   TEXT PRIMARY KEY,
                data      TEXT NOT NULL,
                expires_at INTEGER NOT NULL
            )
        ");

        // Migrasi: rename cached_at → expires_at kalau kolom lama masih ada
        try {
            $cols = self::$pdo->query("PRAGMA table_info(user_cache)")->fetchAll(\PDO::FETCH_ASSOC);
            $names = array_column($cols, 'name');
            if (in_array('cached_at', $names, true) && !in_array('expires_at', $names, true)) {
                self::$pdo->exec('ALTER TABLE user_cache RENAME COLUMN cached_at TO expires_at');
            }
        } catch (\Throwable) { /* SQLite lama, abaikan */ }

        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS rate_limit (
                key_hash  TEXT PRIMARY KEY,
                count     INTEGER NOT NULL DEFAULT 0,
                last_at   INTEGER NOT NULL DEFAULT 0
            )
        ");

        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS meta (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )
        ");

        return self::$pdo;
    }

    // ── Token Blacklist ───────────────────────────────────────────────────────

    /**
     * Tandai JTI sebagai sudah dipakai.
     * Return false jika JTI sudah ada (replay attack).
     * Atomic: pakai INSERT OR IGNORE + rowCount().
     */
    public static function tokenUse(string $jti, int $exp): bool
    {
        try {
            $db  = self::pdo();
            $stmt = $db->prepare('INSERT OR IGNORE INTO token_blacklist (jti, exp) VALUES (?, ?)');
            $stmt->execute([$jti, $exp]);
            return $stmt->rowCount() === 1; // 1 = baru diinsert, 0 = sudah ada
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::tokenUse', $e->getMessage()) : error_log('LocalCache::tokenUse: ' . $e->getMessage());
            // Gagal insert = tolak token (fail-closed)
            return false;
        }
    }

    /**
     * Cek apakah JTI sudah ada di blacklist lokal.
     */
    public static function tokenUsed(string $jti): bool
    {
        try {
            $stmt = self::pdo()->prepare('SELECT 1 FROM token_blacklist WHERE jti = ? AND exp > ?');
            $stmt->execute([$jti, time()]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::tokenUsed', $e->getMessage()) : error_log('LocalCache::tokenUsed: ' . $e->getMessage());
            return false;
        }
    }

    // ── User Cache ────────────────────────────────────────────────────────────

    /**
     * Simpan data user ke cache SQLite.
     * TTL default 5 menit — cukup untuk graceful degrade saat Supabase sesaat down.
     */
    public static function setUser(string $user_id, array $data, int $ttl = 300): void
    {
        try {
            $stmt = self::pdo()->prepare('
                INSERT OR REPLACE INTO user_cache (user_id, data, expires_at)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$user_id, json_encode($data), time() + $ttl]);
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::setUser', $e->getMessage()) : error_log('LocalCache::setUser: ' . $e->getMessage());
        }
    }

    /**
     * Ambil data user dari cache. Return null jika tidak ada atau sudah expired.
     */
    public static function getUser(string $user_id): ?array
    {
        try {
            $stmt = self::pdo()->prepare('
                SELECT data FROM user_cache WHERE user_id = ? AND expires_at > ?
            ');
            $stmt->execute([$user_id, time()]);
            $row = $stmt->fetchColumn();
            if ($row === false) return null;
            return json_decode($row, true) ?: null;
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::getUser', $e->getMessage()) : error_log('LocalCache::getUser: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Hapus cache user tertentu (dipanggil saat logout / password reset).
     */
    public static function clearUser(string $user_id): void
    {
        try {
            $stmt = self::pdo()->prepare('DELETE FROM user_cache WHERE user_id = ?');
            $stmt->execute([$user_id]);
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::clearUser', $e->getMessage()) : error_log('LocalCache::clearUser: ' . $e->getMessage());
        }
    }

    /**
     * Cleanup cache expired — dipanggil sesekali dari verify.php atau healthcheck.
     */
    public static function cleanup(): void
    {
        try {
            $db = self::pdo();
            $db->exec('DELETE FROM token_blacklist WHERE exp < ' . time());
            $db->exec('DELETE FROM user_cache WHERE expires_at < ' . time());
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::cleanup', $e->getMessage()) : error_log('LocalCache::cleanup: ' . $e->getMessage());
        }
    }

    // ── Pending Log Queue ─────────────────────────────────────────────────────

    /**
     * Pastikan tabel pending_logs ada.
     * Dipanggil lazy dari logQueue() — tidak perlu diubah di pdo() supaya
     * tidak tambah overhead di setiap request yang tidak butuh antrian log.
     */
    private static function ensureLogTable(): void
    {
        self::pdo()->exec("
            CREATE TABLE IF NOT EXISTS pending_logs (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                tbl       TEXT NOT NULL,
                payload   TEXT NOT NULL,
                queued_at INTEGER NOT NULL
            )
        ");
    }

    /**
     * Antri satu log yang gagal dikirim ke Supabase.
     *
     * @param string $tbl     Nama tabel: 'user_logs' atau 'admin_logs'
     * @param array  $payload Data yang akan diinsert (kolom => nilai)
     */
    public static function logQueue(string $tbl, array $payload): void
    {
        if (!in_array($tbl, ['user_logs', 'admin_logs'], true)) {
            function_exists('gudeg_log')
                ? gudeg_log('WARNING', 'LocalCache::logQueue', "Tabel tidak diizinkan: $tbl")
                : error_log("LocalCache::logQueue: tabel tidak diizinkan: $tbl");
            return;
        }
        try {
            self::ensureLogTable();
            $stmt = self::pdo()->prepare(
                'INSERT INTO pending_logs (tbl, payload, queued_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([$tbl, json_encode($payload), time()]);
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::logQueue', $e->getMessage()) : error_log('LocalCache::logQueue: ' . $e->getMessage());
        }
    }

    /**
     * Kirim ulang log yang tertunda ke Supabase.
     * Dipanggil dari log_activity() di request berikutnya — tidak butuh cron.
     * Hanya kirim maksimal 10 log per request agar tidak memperlambat response.
     *
     * @param  callable $inserter  fn(string $tbl, array $payload): void
     *                             Caller inject Supabase insert supaya LocalCache
     *                             tidak tergantung langsung ke getSupabase().
     */
    public static function flushLogs(callable $inserter): void
    {
        try {
            self::ensureLogTable();
            $db = self::pdo();

            $stmt = $db->prepare(
                'SELECT id, tbl, payload FROM pending_logs ORDER BY queued_at ASC LIMIT 10'
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) return;

            foreach ($rows as $row) {
                $payload = json_decode($row['payload'], true);
                if (!is_array($payload)) {
                    // Payload corrupt — buang saja
                    function_exists('gudeg_log')
                        ? gudeg_log('WARNING', 'LocalCache::flushLogs', 'payload corrupt di-discard, id=' . $row['id'] . ' tbl=' . $row['tbl'])
                        : error_log('LocalCache::flushLogs: payload corrupt id=' . $row['id']);
                    $db->prepare('DELETE FROM pending_logs WHERE id = ?')->execute([$row['id']]);
                    continue;
                }

                // Whitelist tbl — cegah data aneh dari SQLite dipass ke Supabase
                if (!in_array($row['tbl'], ['user_logs', 'admin_logs'], true)) {
                    function_exists('gudeg_log')
                        ? gudeg_log('WARNING', 'LocalCache::flushLogs', 'tbl tidak diizinkan, di-discard id=' . $row['id'] . ' tbl=' . $row['tbl'])
                        : error_log('LocalCache::flushLogs: tbl tidak diizinkan id=' . $row['id']);
                    $db->prepare('DELETE FROM pending_logs WHERE id = ?')->execute([$row['id']]);
                    continue;
                }

                try {
                    $inserter($row['tbl'], $payload);
                    // Berhasil terkirim — hapus dari antrian
                    $db->prepare('DELETE FROM pending_logs WHERE id = ?')->execute([$row['id']]);
                } catch (\Throwable $e) {
                    // Masih gagal — biarkan di antrian, coba di request berikutnya
                    function_exists('gudeg_log') ? gudeg_log('WARNING', 'LocalCache::flushLogs', 'masih gagal kirim log id=' . $row['id'] . ': ' . $e->getMessage()) : error_log('LocalCache::flushLogs retry gagal id=' . $row['id'] . ': ' . $e->getMessage());
                    break; // Supabase masih down, hentikan flush — jangan loop sia-sia
                }
            }

            // Buang log yang terlalu lama (> 24 jam) agar SQLite tidak membengkak
            $db->exec('DELETE FROM pending_logs WHERE queued_at < ' . (time() - 86400));

        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::flushLogs', $e->getMessage()) : error_log('LocalCache::flushLogs: ' . $e->getMessage());
        }
    }

    // ── Rate Limit ────────────────────────────────────────────────────────────

    /**
     * Ambil data rate limit untuk key tertentu (IP+email hash).
     * Return ['count' => int, 'last_at' => int].
     */
    public static function rlGet(string $key_hash): array
    {
        try {
            $stmt = self::pdo()->prepare(
                'SELECT count, last_at FROM rate_limit WHERE key_hash = ?'
            );
            $stmt->execute([$key_hash]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: ['count' => 0, 'last_at' => 0];
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::rlGet', $e->getMessage()) : error_log('LocalCache::rlGet: ' . $e->getMessage());
            return ['count' => 0, 'last_at' => 0];
        }
    }

    /**
     * Simpan data rate limit. Pakai INSERT OR REPLACE agar atomic.
     */
    public static function rlSet(string $key_hash, int $count, int $last_at): void
    {
        try {
            $stmt = self::pdo()->prepare(
                'INSERT OR REPLACE INTO rate_limit (key_hash, count, last_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([$key_hash, $count, $last_at]);
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::rlSet', $e->getMessage()) : error_log('LocalCache::rlSet: ' . $e->getMessage());
        }
    }

    /**
     * Hapus entry rate limit (dipanggil saat login berhasil atau window habis).
     */
    public static function rlDelete(string $key_hash): void
    {
        try {
            $stmt = self::pdo()->prepare('DELETE FROM rate_limit WHERE key_hash = ?');
            $stmt->execute([$key_hash]);
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::rlDelete', $e->getMessage()) : error_log('LocalCache::rlDelete: ' . $e->getMessage());
        }
    }

    // ── Meta (internal key-value) ─────────────────────────────────────────────

    public static function metaGet(string $key): ?string
    {
        try {
            $stmt = self::pdo()->prepare('SELECT value FROM meta WHERE key = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? $row['value'] : null;
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::metaGet', $e->getMessage()) : error_log('LocalCache::metaGet: ' . $e->getMessage());
            return null;
        }
    }

    public static function metaSet(string $key, string $value): void
    {
        try {
            $stmt = self::pdo()->prepare('INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)');
            $stmt->execute([$key, $value]);
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'LocalCache::metaSet', $e->getMessage()) : error_log('LocalCache::metaSet: ' . $e->getMessage());
        }
    }
} — mengembalikan statistik pending_logs
     * tanpa harus expose method private pdo() via ReflectionClass.
     *
     * @return array{count: int, oldest_age_seconds: int}
     */
    public static function getPendingLogStats(): array
    {
        $stmt = self::pdo()->query(
            'SELECT COUNT(*) as cnt, MIN(queued_at) as oldest FROM pending_logs'
        );
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
        $cnt  = (int)($row['cnt'] ?? 0);
        $age  = ($row['oldest'] ?? 0) > 0 ? (time() - (int)$row['oldest']) : 0;
        return ['count' => $cnt, 'oldest_age_seconds' => $age];
    }
}
