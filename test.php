<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Test apakah path ke laravel benar
echo "Path test: ";
var_dump(file_exists(__DIR__.'/../laravel/vendor/autoload.php'));

// Test koneksi DB
try {
    $pdo = new PDO(
        'mysql:host=sql107.infinityfree.com;dbname=if0_41894489_db_gudeg',
        'if0_41894489',
        '3142zxcvbnm'
    );
    echo "DB OK!";
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}