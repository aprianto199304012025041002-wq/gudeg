<?php
define('DB_HOST', 'sql107.infinityfree.com');
define('DB_USER', 'if0_41894489');
define('DB_PASS', '3142zxcvbnm');
define('DB_NAME', 'if0_41894489_db_gudeg');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'DB connection failed']));
        }
    }
    return $pdo;
}
