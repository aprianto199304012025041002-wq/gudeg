<?php
/**
 * logger.php - Structured JSON logger.
 */

/**
 * Tulis log terstruktur (JSON) ke PHP error log.
 * Format: {"ts":"...","level":"...","ctx":"...","msg":"..."}
 *
 * @param string $level  ERROR | WARNING | CRITICAL | INFO
 * @param string $ctx    Konteks: nama fungsi/file (misal 'log_activity', 'RateLimiter::fail')
 * @param string $msg    Pesan error/info
 */
function gudeg_log(string $level, string $ctx, string $msg): void
{
    error_log(json_encode([
        'ts'    => date('Y-m-d H:i:s'),
        'level' => $level,
        'ctx'   => $ctx,
        'msg'   => $msg,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
