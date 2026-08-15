<?php
/**
 * token.php — JWT helpers dan token replay blacklist.
 *
 * Fungsi:
 *  - base64url_encode()  : encode base64 URL-safe (RFC 7519)
 *  - base64url_decode()  : decode base64 URL-safe (RFC 7519)
 *  - token_use()         : cek dan tandai token sebagai sudah dipakai (anti-replay)
 */

// ── Helper: base64url encoding/decoding (RFC 7519) ───────────────────────────
// Didefinisikan di sini sekali — dipakai oleh sso.php dan verify.php.

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

// ── Token replay blacklist ────────────────────────────────────────────────────

/**
 * Tandai token sebagai sudah dipakai (blacklist berdasarkan JTI).
 * Return false kalau token sudah pernah dipakai (replay attack).
 */
function token_use(string $jti, int $exp): bool
{
    // Coba Supabase dulu (sumber kebenaran utama)
    try {
        $sb = getSupabase();

        // Hapus token expired supaya tabel tidak membengkak
        try {
            $sb->delete('token_blacklist', ['exp' => 'lt.' . time()]);
        } catch (\Throwable $e) {}

        // Cek apakah JTI sudah ada
        $existing = $sb->selectOne('token_blacklist', ['jti' => $jti], 'jti');
        if ($existing !== null) {
            return false; // sudah dipakai = replay attack
        }

        // Simpan JTI ke blacklist Supabase
        $sb->insert('token_blacklist', [
            'jti'     => $jti,
            'exp'     => $exp,
            'used_at' => date('Y-m-d H:i:s'),
        ]);

        // Sinkron ke LocalCache juga sebagai redundansi
        if (class_exists('LocalCache')) {
            LocalCache::tokenUse($jti, $exp);
        }

        return true;

    } catch (\Throwable $e) {
        // Supabase error — fallback ke LocalCache (fail-closed)
        gudeg_log('WARNING', 'token_use', 'Supabase error, fallback ke LocalCache: ' . $e->getMessage());

        if (class_exists('LocalCache')) {
            // LocalCache::tokenUse atomic: return false jika JTI sudah ada
            return LocalCache::tokenUse($jti, $exp);
        }

        // Kedua sumber gagal — tolak token (fail-closed, bukan fail-open)
        gudeg_log('WARNING', 'token_use', 'LocalCache tidak tersedia, token ditolak sebagai precaution.');
        return false;
    }
}
