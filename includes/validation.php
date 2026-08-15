<?php
/**
 * validation.php — Input validation, sanitasi, password strength, IP detection.
 *
 * Fungsi:
 *  - validate_password_strength()  : cek kekuatan password, return pesan error atau null
 *  - is_new_ip()                   : deteksi IP baru untuk notifikasi login mencurigakan
 *  - hash_client_secret()          : hash client_secret sebelum simpan ke DB
 *  - verify_client_secret()        : verifikasi client_secret dari request vs hash di DB
 *  - sanitize_input()              : trim + potong string input
 *  - validate_length()             : validasi panjang field, return pesan error atau null
 */

/**
 * Validasi kekuatan password.
 * Return string pesan error, atau null jika valid.
 * Syarat: 8+ karakter, huruf besar, huruf kecil, angka, karakter spesial.
 */
function validate_password_strength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password minimal 8 karakter.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password harus mengandung minimal 1 huruf besar.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password harus mengandung minimal 1 huruf kecil.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password harus mengandung minimal 1 angka.';
    }
    if (!preg_match('/[\W_]/', $password)) {
        return 'Password harus mengandung minimal 1 karakter spesial (!, @, #, dll).';
    }
    return null;
}

/**
 * Cek apakah IP ini baru (belum pernah dipakai user ini login).
 * Return true jika IP baru/mencurigakan.
 */
function is_new_ip(int $user_id, string $ip): bool
{
    try {
        $rows = getSupabase()->select('user_logs', [
            'user_id'    => $user_id,
            'ip_address' => $ip,
            'activity'   => ACTIVITY_LOGIN,
        ], 'id', ['limit' => 1]);
        return empty($rows);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Hash client_secret sebelum disimpan ke DB.
 * Pakai password_hash agar tidak bisa di-reverse kalau DB bocor.
 */
function hash_client_secret(string $secret): string
{
    return password_hash($secret, PASSWORD_BCRYPT);
}

/**
 * Verifikasi client_secret dari request terhadap hash di DB.
 */
function verify_client_secret(string $plain, string $hash): bool
{
    return password_verify($plain, $hash);
}

/**
 * Potong dan bersihkan string input — cegah spam DB dan buffer overflow.
 * Gunakan di semua form input sebelum simpan ke DB.
 */
function sanitize_input(string $value, int $max_len = 255): string
{
    return substr(trim($value), 0, $max_len);
}

/**
 * Validasi panjang input, return pesan error atau null jika valid.
 */
function validate_length(string $field, string $value, int $max): ?string
{
    if (strlen($value) > $max) {
        return "$field terlalu panjang (maksimal $max karakter).";
    }
    return null;
}
