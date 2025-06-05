<?php
// /www/wwwroot/maxcaulfield.cn/db.php

// 1. Include config.php to get database constants and session_start
require_once __DIR__ . '/config.php';

// 2. Define the getPDO() function
function getPDO() {
    // Use DB_CHARSET if defined in config.php, otherwise default to utf8mb4
    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . $charset;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (\PDOException $e) {
        // Log the error for server-side review
        error_log("PDO Connection Error: " . $e->getMessage());
        // For development, you might want to see the error.
        // For production, you'd show a generic error message.
        throw new \PDOException("Database connection failed. Please try again later.", (int)$e->getCode());
    }
}
?>