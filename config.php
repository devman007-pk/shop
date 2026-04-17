<?php
declare(strict_types=1);

/**
 * config.php
 * - เก็บการตั้งค่าการเชื่อมต่อ DB และฟังก์ชัน getPDO()
 * - อ่านจาก ENV หากมี: DB_HOST, DB_NAME, DB_USER, DB_PASS
 */

$DB_HOST    = getenv('DB_HOST') ?: 'localhost';
$DB_NAME    = getenv('DB_NAME') ?: 'shop';
$DB_USER    = getenv('DB_USER') ?: 'root';
$DB_PASS    = getenv('DB_PASS') ?: '';
$DB_CHARSET = 'utf8mb4';

function getPDO(): PDO {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET;

    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $DB_USER, $DB_PASS, $options);
}