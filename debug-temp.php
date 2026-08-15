<?php
// File ini HANYA untuk debug — hapus setelah selesai
// Jangan commit file ini ke production dalam kondisi normal
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Step 1: PHP OK\n";

try {
    require_once __DIR__ . '/includes/env.php';
    echo "Step 2: env.php OK\n";
} catch (Throwable $e) {
    die("FAIL env.php: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/includes/logger.php';
    echo "Step 3: logger.php OK\n";
} catch (Throwable $e) {
    die("FAIL logger.php: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/includes/headers.php';
    echo "Step 4: headers.php OK\n";
} catch (Throwable $e) {
    die("FAIL headers.php: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/includes/SqliteSessionHandler.php';
    echo "Step 5: SqliteSessionHandler.php OK\n";
    $h = new SqliteSessionHandler();
    echo "Step 6: SqliteSessionHandler construct OK\n";
} catch (Throwable $e) {
    echo "FAIL SqliteSessionHandler: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/includes/session.php';
    echo "Step 7: session.php OK\n";
} catch (Throwable $e) {
    die("FAIL session.php: " . $e->getMessage());
}

echo "PHP version: " . phpversion() . "\n";
echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
echo "SQLite3: " . (class_exists('SQLite3') ? 'YES' : 'NO') . "\n";
