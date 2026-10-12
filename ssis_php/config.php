<?php
/**
 * config.php
 * ---------------------------------------------------------------------
 * Single place that opens the database connection for the whole app.
 * Every page needs this file, so it is require_once'd, never included
 * twice.
 *
 * Edit the four constants below to match your own MySQL/MariaDB setup
 * (e.g. the defaults are what a stock XAMPP install uses: root user,
 * no password, server on localhost).
 * ---------------------------------------------------------------------
 */

const DB_HOST = 'localhost';
const DB_NAME = 'ssis_db';
const DB_USER = 'root';
const DB_PASS = '';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            // Throw exceptions on SQL errors instead of failing silently.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Fetch rows as associative arrays by default.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real prepared statements (safer, and needed for some
            // strict-mode ENUM/CHECK behaviour) instead of emulated ones.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // In production you would log this instead of echoing it, but for a
    // school project surfacing the real error makes setup issues obvious.
    die('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
