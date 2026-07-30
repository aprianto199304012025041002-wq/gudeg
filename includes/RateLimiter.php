<?php
/**
 * RateLimiter.php — File-based rate limiter untuk login.
 *
 * Menggantikan session-based throttle di functions.php yang bisa di-bypass
 * dengan ganti session ID. File-based persist lintas session dan lintas tab.
 *
 * File counter disimpan di /tmp/rl_{hash}.json.
 * InfinityFree mengizinkan tulis ke /tmp.
 *
 * Batas: 5 percobaan gagal dalam 10 menit per kombinasi IP+email.
 */
class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW       = 600; // 10 menit dalam detik
    private const TMP_DIR      = '/tmp';

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function filePath(string $ip, string $email): string
    {
        $hash = md5(strtolower(trim($ip)) . strtolower(trim($email)));
        return self::TMP_DIR . '/rl_' . $hash . '.json';
    }

    private static function load(string $path): array
    {
        if (!file_exists($path)) {
            return ['count' => 0, 'last' => 0];
        }
        try {
            $raw  = file_get_contents($path);
            $data = $raw !== false ? json_decode($raw, true) : null;
            return is_array($data) ? $data : ['count' => 0, 'last' => 0];
        } catch (\Throwable $e) {
            return ['count' => 0, 'last' => 0];
        }
    }

    private static function save(string $path, array $data): void
    {
        try {
            $result = file_put_contents($path, json_encode($data), LOCK_EX);
            if ($result === false) {
                error_log('RateLimiter::save gagal tulis file: ' . $path);
            }
        } catch (\Throwable $e) {
            error_log('RateLimiter::save error: ' . $e->getMessage());
        }
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Cek apakah IP+email sedang di-throttle.
     */
    public static function throttled(string $ip, string $email): bool
    {
        try {
            $path = self::filePath($ip, $email);
            $data = self::load($path);

            // Window sudah habis → reset otomatis
            if (time() - $data['last'] > self::WINDOW) {
                @unlink($path);
                return false;
            }

            return $data['count'] >= self::MAX_ATTEMPTS;
        } catch (\Throwable $e) {
            error_log('RateLimiter::throttled error: ' . $e->getMessage());
            return false; // jangan block kalau error
        }
    }

    /**
     * Catat percobaan login gagal.
     */
    public static function fail(string $ip, string $email): void
    {
        try {
            $path = self::filePath($ip, $email);
            $data = self::load($path);

            // Reset counter kalau window sudah habis
            if (time() - $data['last'] > self::WINDOW) {
                $data = ['count' => 0, 'last' => 0];
            }

            $data['count'] += 1;
            $data['last']   = time();
            self::save($path, $data);
        } catch (\Throwable $e) {
            error_log('RateLimiter::fail error: ' . $e->getMessage());
        }
    }

    /**
     * Reset counter setelah login berhasil.
     */
    public static function reset(string $ip, string $email): void
    {
        try {
            $path = self::filePath($ip, $email);
            if (file_exists($path)) {
                @unlink($path);
            }
        } catch (\Throwable $e) {
            error_log('RateLimiter::reset error: ' . $e->getMessage());
        }
    }

    /**
     * Berapa detik lagi window habis. Dipakai untuk pesan error ke user.
     */
    public static function secondsLeft(string $ip, string $email): int
    {
        try {
            $path = self::filePath($ip, $email);
            $data = self::load($path);
            $elapsed = time() - $data['last'];
            return max(0, self::WINDOW - $elapsed);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
