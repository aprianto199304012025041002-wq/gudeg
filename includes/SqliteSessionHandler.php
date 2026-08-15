<?php
/**
 * SqliteSessionHandler - Custom PHP session handler berbasis SQLite.
 *
 * Menggantikan default file-based session (/tmp) yang volatile di InfinityFree.
 * SQLite disimpan di path yang dikonfigurasi via SESSION_DB_PATH di .env,
 * fallback ke /tmp/gudeg_sessions.sqlite.
 *
 * Cara kerja:
 *  1. Didaftarkan di session_start_safe() via session_set_save_handler().
 *  2. PHP memanggil read()/write()/destroy()/gc() otomatis saat session_start(),
 *     session_write_close(), session_destroy(), dan probabilistic GC.
 *
 * Trade-off vs file session:
 *  + Lebih persist: SQLite tidak di-clear bersama /tmp kalau direktori berbed
 *  + Atomic write: PDO transaction cegah partial write
 *  + GC terkontrol: cleanup expired session via gc()
 *  - Sedikit lebih lambat dari file (PDO overhead minimal)
 */
class SqliteSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $path = $this->resolvePath();

        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA synchronous=NORMAL');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS php_sessions (
                id         TEXT PRIMARY KEY,
                data       TEXT NOT NULL DEFAULT \'\',
                expires_at INTEGER NOT NULL
            )
        ');
        $this->pdo->exec('
            CREATE INDEX IF NOT EXISTS idx_sessions_expires ON php_sessions(expires_at)
        ');
    }

    private function resolvePath(): string
    {
        if (defined('SESSION_DB_PATH') && SESSION_DB_PATH !== '') {
            $dir = dirname(SESSION_DB_PATH);
            if (is_dir($dir) && is_writable($dir)) {
                return SESSION_DB_PATH;
            }
        }
        // Fallback: simpan di direktori yang sama dengan LocalCache
        if (defined('CACHE_PATH') && CACHE_PATH !== '') {
            $dir = dirname(CACHE_PATH);
            if (is_dir($dir) && is_writable($dir)) {
                return $dir . '/gudeg_sessions.sqlite';
            }
        }
        return '/tmp/gudeg_sessions.sqlite';
    }

    public function open(string $path, string $name): bool
    {
        return true; // koneksi sudah dibuka di constructor
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT data FROM php_sessions WHERE id = ? AND expires_at > ?'
            );
            $stmt->execute([$id, time()]);
            $row = $stmt->fetchColumn();
            return ($row !== false) ? $row : '';
        } catch (Throwable $e) {
            function_exists('gudeg_log')
                ? gudeg_log('ERROR', 'SqliteSessionHandler::read', $e->getMessage())
                : error_log('SqliteSessionHandler::read: ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $lifetime   = (int)(ini_get('session.gc_maxlifetime') ?: 1440);
            $expires_at = time() + $lifetime;

            $stmt = $this->pdo->prepare('
                INSERT INTO php_sessions (id, data, expires_at)
                VALUES (?, ?, ?)
                ON CONFLICT(id) DO UPDATE SET data = excluded.data, expires_at = excluded.expires_at
            ');
            $stmt->execute([$id, $data, $expires_at]);
            return true;
        } catch (Throwable $e) {
            function_exists('gudeg_log')
                ? gudeg_log('ERROR', 'SqliteSessionHandler::write', $e->getMessage())
                : error_log('SqliteSessionHandler::write: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM php_sessions WHERE id = ?');
            $stmt->execute([$id]);
            return true;
        } catch (Throwable $e) {
            function_exists('gudeg_log')
                ? gudeg_log('ERROR', 'SqliteSessionHandler::destroy', $e->getMessage())
                : error_log('SqliteSessionHandler::destroy: ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM php_sessions WHERE expires_at < ?');
            $stmt->execute([time()]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            function_exists('gudeg_log')
                ? gudeg_log('ERROR', 'SqliteSessionHandler::gc', $e->getMessage())
                : error_log('SqliteSessionHandler::gc: ' . $e->getMessage());
            return false;
        }
    }
}
