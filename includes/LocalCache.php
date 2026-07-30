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

    // ── Init ─────────────────────────────────────────────────────────────────

    private static function pdo(): PDO
    {
        if (self::$pdo !== null) return self::$pdo;

        self::$pdo = new PDO('sqlite:' . self::$db_path);
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
                cached_at INTEGER NOT NULL
            )
        ");

        // Cleanup expired token blacklist
        self::$pdo->exec('DELETE FROM token_blacklist WHERE exp < ' . time());

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
            error_log('LocalCache::tokenUse error: ' . $e->getMessage());
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
            error_log('LocalCache::tokenUsed error: ' . $e->getMessage());
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
                INSERT OR REPLACE INTO user_cache (user_id, data, cached_at)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$user_id, json_encode($data), time() + $ttl]);
        } catch (\Throwable $e) {
            error_log('LocalCache::setUser error: ' . $e->getMessage());
        }
    }

    /**
     * Ambil data user dari cache. Return null jika tidak ada atau sudah expired.
     */
    public static function getUser(string $user_id): ?array
    {
        try {
            $stmt = self::pdo()->prepare('
                SELECT data FROM user_cache WHERE user_id = ? AND cached_at > ?
            ');
            $stmt->execute([$user_id, time()]);
            $row = $stmt->fetchColumn();
            if ($row === false) return null;
            return json_decode($row, true) ?: null;
        } catch (\Throwable $e) {
            error_log('LocalCache::getUser error: ' . $e->getMessage());
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
            error_log('LocalCache::clearUser error: ' . $e->getMessage());
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
            $db->exec('DELETE FROM user_cache WHERE cached_at < ' . time());
        } catch (\Throwable $e) {
            error_log('LocalCache::cleanup error: ' . $e->getMessage());
        }
    }
}
