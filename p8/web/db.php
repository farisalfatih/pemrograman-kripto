<?php
// ============================================================
// db.php — Koneksi PDO ke PostgreSQL
// ============================================================

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = $_ENV['POSTGRES_HOST']     ?? getenv('POSTGRES_HOST')     ?? 'postgres';
    $port = $_ENV['POSTGRES_PORT']     ?? getenv('POSTGRES_PORT')     ?? '5432';
    $db   = $_ENV['POSTGRES_DB']       ?? getenv('POSTGRES_DB')       ?? 'crypto_trading';
    $user = $_ENV['POSTGRES_USER']     ?? getenv('POSTGRES_USER')     ?? 'crypto_user';
    $pass = $_ENV['POSTGRES_PASSWORD'] ?? getenv('POSTGRES_PASSWORD') ?? 'crypto_pass123';

    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT         => false,
    ]);

    return $pdo;
}
