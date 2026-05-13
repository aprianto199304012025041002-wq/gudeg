<?php

use Illuminate\Support\Str;

return [

    'default' => 'mysql',

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => null,
            'database' => database_path('database.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => null,
            'host' => 'sql107.infinityfree.com', // ← GANTI dengan host MySQL di cPanel
            'port' => '3306',
            'database' => 'if0_41894489_db_gudeg', // ← GANTI nama database kamu
            'username' => 'if0_41894489',         // ← GANTI username kamu
            'password' => '3142zxcvbnm',            // ← GANTI password kamu
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => null,
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'laravel',
            'username' => 'root',
            'password' => '',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => null,
            'host' => '127.0.0.1',
            'port' => '5432',
            'database' => 'laravel',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => null,
            'host' => 'localhost',
            'port' => '1433',
            'database' => 'laravel',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => 'phpredis',

        'options' => [
            'cluster' => 'redis',
            'prefix' => 'laravel-database-',
        ],

        'default' => [
            'url' => null,
            'host' => '127.0.0.1',
            'username' => null,
            'password' => null,
            'port' => '6379',
            'database' => '0',
        ],

        'cache' => [
            'url' => null,
            'host' => '127.0.0.1',
            'username' => null,
            'password' => null,
            'port' => '6379',
            'database' => '1',
        ],

    ],

];
