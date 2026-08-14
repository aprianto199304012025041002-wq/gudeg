<?php
/**
 * RateLimiter.php — SQLite-based rate limiter untuk login.
 *
 * Sebelumnya file-based (/tmp/rl_*.json) yang volatile di InfinityFree —
 * bisa dihapus OS kapan saja, counter reset sendiri, celah brute force terbuka.
 *
 * Sekarang pakai LocalCache SQLite (gudeg_cache.sqlite) yang lebih persist.
 * Fallback ke file /tmp kalau SQLite error — supaya rate limiter tetap jalan.
 *
 * Public API tidak berubah: throttled(), fail(), reset(), secondsLeft()
 * sehingga login.php dan verify.php tidak perlu disentuh.
 *
 * Batas: 5 percobaan gagal dalam 10 menit per kombinasi IP+email.
 */
class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW       = 600; // 10 menit dalam detik
    private const TMP_DIR      = '/tmp';

    // ── Key helper ────────────────────────────────────────────────────────────

    private static function keyHash(string $ip, string $email): string
    {
        return hash('sha256', strtolower(trim($ip)) . strtolower(trim($email)));
    }

    // ── SQLite via LocalCache ─────────────────────────────────────────────────

    private static function sqliteGet(string $hash): array
    {
        if (!class_exists('LocalCache')) return ['count' => 0, 'last_at' => 0];
        $row = LocalCache::rlGet($hash);
        return ['count' => (int)$row['count'], 'last_at' => (int)$row['last_at']];
    }

    private static function sqliteSet(string $hash, int $count, int $last_at): bool
    {
        if (!class_exists('LocalCache')) return false;
        try {
            LocalCache::rlSet($hash, $count, $last_at);
            return true;
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'RateLimiter::sqliteSet', $e->getMessage()) : error_log('RateLimiter SQLite set: ' . $e->getMessage());
            return false;
        }
    }

    private static function sqliteDelete(string $hash): bool
    {
        if (!class_exists('LocalCache')) return false;
        try {
            LocalCache::rlDelete($hash);
            return true;
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'RateLimiter::sqliteDelete', $e->getMessage()) : error_log('RateLimiter SQLite delete: ' . $e->getMessage());
            return false;
        }
    }

    // ── File fallback (sama seperti implementasi lama) ────────────────────────

    private static function filePath(string $hash): string
    {
        return self::TMP_DIR . '/rl_' . $hash . '.json';
    }

    private static function fileGet(string $hash): array
    {
        $path = self::filePath($hash);
        if (!file_exists($path)) return ['count' => 0, 'last_at' => 0];
        try {
            $raw  = file_get_contents($path);
            $data = $raw !== false ? json_decode($raw, true) : null;
            if (!is_array($data)) return ['count' => 0, 'last_at' => 0];
            // support format lama (key 'last') maupun baru (key 'last_at')
            return [
                'count'   => (int)($data['count'] ?? 0),
                'last_at' => (int)($data['last_at'] ?? $data['last'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['count' => 0, 'last_at' => 0];
        }
    }

    private static function fileSet(string $hash, int $count, int $last_at): void
    {
        $path = self::filePath($hash);
        try {
            file_put_contents($path, json_encode(['count' => $count, 'last_at' => $last_at]), LOCK_EX);
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('WARNING', 'RateLimiter::fileSet', 'file fallback set error: ' . $e->getMessage()) : error_log('RateLimiter file fallback set: ' . $e->getMessage());
        }
    }

    private static function fileDelete(string $hash): void
    {
        $path = self::filePath($hash);
        if (file_exists($path)) @unlink($path);
    }

    // ── Baca data dari SQLite, fallback ke file kalau SQLite gagal ────────────

    private static function getData(string $hash): array
    {
        try {
            if (class_exists('LocalCache')) {
                return self::sqliteGet($hash);
            }
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('WARNING', 'RateLimiter::getData', 'SQLite read gagal, fallback file: ' . $e->getMessage()) : error_log('RateLimiter SQLite read gagal: ' . $e->getMessage());
        }
        return self::fileGet($hash);
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Cek apakah IP+email sedang di-throttle.
     */
    public static function throttled(string $ip, string $email): bool
    {
        try {
            $hash = self::keyHash($ip, $email);
            $data = self::getData($hash);

            // Window sudah habis → anggap bersih
            if ($data['last_at'] > 0 && (time() - $data['last_at']) > self::WINDOW) {
                self::reset($ip, $email);
                return false;
            }

            return $data['count'] >= self::MAX_ATTEMPTS;
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'RateLimiter::throttled', $e->getMessage()) : error_log('RateLimiter::throttled: ' . $e->getMessage());
            return false; // jangan block kalau error
        }
    }

    /**
     * Catat percobaan login gagal.
     */
    public static function fail(string $ip, string $email): void
    {
        try {
            $hash = self::keyHash($ip, $email);
            $data = self::getData($hash);

            // Reset counter kalau window sudah habis
            if ($data['last_at'] > 0 && (time() - $data['last_at']) > self::WINDOW) {
                $data = ['count' => 0, 'last_at' => 0];
            }

            $count   = $data['count'] + 1;
            $last_at = time();

            // Coba SQLite dulu, fallback ke file
            if (!self::sqliteSet($hash, $count, $last_at)) {
                self::fileSet($hash, $count, $last_at);
            }
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'RateLimiter::fail', $e->getMessage()) : error_log('RateLimiter::fail: ' . $e->getMessage());
        }
    }

    /**
     * Reset counter setelah login berhasil.
     */
    public static function reset(string $ip, string $email): void
    {
        try {
            $hash = self::keyHash($ip, $email);
            // Hapus dari keduanya — bersihkan sisa data lama di file juga
            self::sqliteDelete($hash);
            self::fileDelete($hash);
        } catch (\Throwable $e) {
            function_exists('gudeg_log') ? gudeg_log('ERROR', 'RateLimiter::reset', $e->getMessage()) : error_log('RateLimiter::reset: ' . $e->getMessage());
        }
    }

    /**
     * Berapa detik lagi window habis. Dipakai untuk pesan error ke user.
     */
    public static function secondsLeft(string $ip, string $email): int
    {
        try {
            $hash    = self::keyHash($ip, $email);
            $data    = self::getData($hash);
            $elapsed = time() - $data['last_at'];
            return max(0, self::WINDOW - $elapsed);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
