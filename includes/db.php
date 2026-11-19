<?php
// includes/db.php
// One database connection for the whole site (PDO).

require_once __DIR__ . '/functions.php';

function get_db(): PDO
{
    static $pdo = null;       // remembers the connection
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // errors become exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // rows as arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared statements
        ]);
    } catch (PDOException $ex) {
        app_log('ERROR', 'Database connection failed: ' . $ex->getMessage());
        throw new RuntimeException('Cannot connect to the database. Check the DB_ settings in config.php.');
    }

    return $pdo;
}