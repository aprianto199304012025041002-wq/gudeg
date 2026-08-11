<?php

class JWT {
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    // Generate JWT signed with RSA private key
    public static function generate(array $payload, int $expiry_seconds = 3600) {
        $private_key_path = __DIR__ . '/../keys/private.pem';
        if (!file_exists($private_key_path)) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::generate', 'private.pem tidak ditemukan di ' . $private_key_path);
            throw new Exception('Private key not found');
        }
        $raw_key = file_get_contents($private_key_path);
        if ($raw_key === false) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::generate', 'gagal baca private.pem — cek permission file');
            throw new Exception('Cannot read private key');
        }
        $private_key = openssl_pkey_get_private($raw_key);
        if (!$private_key) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::generate', 'private.pem ada tapi tidak valid atau corrupt');
            throw new Exception('Invalid private key');
        }

        $header = self::base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry_seconds;
        $payload['iss'] = 'gudeg-sso';
        $body = self::base64url_encode(json_encode($payload));

        $signing_input = "$header.$body";
        openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $sig = self::base64url_encode($signature);

        return "$header.$body.$sig";
    }

    // Verify JWT using RSA public key
    public static function verify(string $token) {
        $public_key_path = __DIR__ . '/../keys/public.pem';
        if (!file_exists($public_key_path)) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::verify', 'public.pem tidak ditemukan di ' . $public_key_path);
            throw new Exception('Public key not found');
        }
        $raw_key = file_get_contents($public_key_path);
        if ($raw_key === false) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::verify', 'gagal baca public.pem — cek permission file');
            throw new Exception('Cannot read public key');
        }
        $public_key = openssl_pkey_get_public($raw_key);
        if (!$public_key) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::verify', 'public.pem ada tapi tidak valid atau corrupt');
            throw new Exception('Invalid public key');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        [$header, $body, $sig] = $parts;
        $signing_input = "$header.$body";
        $signature = self::base64url_decode($sig);

        $valid = openssl_verify($signing_input, $signature, $public_key, OPENSSL_ALGO_SHA256);
        if ($valid !== 1) return false;

        $payload = json_decode(self::base64url_decode($body), true);
        if (!$payload) return false;
        if ($payload['exp'] < time()) return false;

        // Validasi issuer — tolak token yang bukan dari sistem ini
        if (($payload['iss'] ?? '') !== 'gudeg-sso') return false;

        return $payload;
    }
}