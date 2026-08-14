<?php

require_once __DIR__ . '/functions.php';

class JWT {
    private static ?string $cachedPrivateKey = null;
    private static ?string $cachedPublicKey  = null;

    private static function loadPrivateKey(): \OpenSSLAsymmetricKey {
        if (self::$cachedPrivateKey !== null) {
            $key = openssl_pkey_get_private(self::$cachedPrivateKey);
            if ($key) return $key;
        }
        $path = __DIR__ . '/../keys/private.pem';
        if (!file_exists($path)) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::loadPrivateKey', 'private.pem tidak ditemukan di ' . $path);
            throw new Exception('Private key not found');
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::loadPrivateKey', 'gagal baca private.pem — cek permission file');
            throw new Exception('Cannot read private key');
        }
        $key = openssl_pkey_get_private($raw);
        if (!$key) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::loadPrivateKey', 'private.pem ada tapi tidak valid atau corrupt');
            throw new Exception('Invalid private key');
        }
        self::$cachedPrivateKey = $raw;
        return $key;
    }

    private static function loadPublicKey(): \OpenSSLAsymmetricKey {
        if (self::$cachedPublicKey !== null) {
            $key = openssl_pkey_get_public(self::$cachedPublicKey);
            if ($key) return $key;
        }
        $path = __DIR__ . '/../keys/public.pem';
        if (!file_exists($path)) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::loadPublicKey', 'public.pem tidak ditemukan di ' . $path);
            throw new Exception('Public key not found');
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::loadPublicKey', 'gagal baca public.pem — cek permission file');
            throw new Exception('Cannot read public key');
        }
        $key = openssl_pkey_get_public($raw);
        if (!$key) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::loadPublicKey', 'public.pem ada tapi tidak valid atau corrupt');
            throw new Exception('Invalid public key');
        }
        self::$cachedPublicKey = $raw;
        return $key;
    }

    // Generate JWT signed with RSA private key
    public static function generate(array $payload, int $expiry_seconds = 3600) {
        $private_key = self::loadPrivateKey();

        $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry_seconds;
        $payload['iss'] = 'gudeg-sso';
        $body = base64url_encode(json_encode($payload));

        $signing_input = "$header.$body";
        if (!openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            function_exists('gudeg_log') && gudeg_log('CRITICAL', 'JWT::generate', 'openssl_sign() gagal: ' . openssl_error_string());
            throw new \Exception('JWT signing failed');
        }
        $sig = base64url_encode($signature);

        return "$header.$body.$sig";
    }

    // Verify JWT using RSA public key
    public static function verify(string $token) {
        $public_key = self::loadPublicKey();

        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        [$header, $body, $sig] = $parts;

        // Validasi header — tolak token dengan alg selain RS256 (algorithm confusion attack)
        $header_data = json_decode(base64url_decode($header), true);
        if (!is_array($header_data) || ($header_data['alg'] ?? '') !== 'RS256') {
            function_exists('gudeg_log') && gudeg_log('WARNING', 'JWT::verify', 'Token ditolak: alg tidak valid atau bukan RS256');
            return false;
        }

        $signing_input = "$header.$body";
        $signature = base64url_decode($sig);

        $valid = openssl_verify($signing_input, $signature, $public_key, OPENSSL_ALGO_SHA256);
        if ($valid !== 1) return false;

        $payload = json_decode(base64url_decode($body), true);
        if (!$payload) return false;
        if ($payload['exp'] < time()) return false;

        // Validasi issuer — tolak token yang bukan dari sistem ini
        if (($payload['iss'] ?? '') !== 'gudeg-sso') return false;

        return $payload;
    }
}