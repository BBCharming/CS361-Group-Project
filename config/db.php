<?php
// config/database.php

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = 'localhost';
        $db   = 'zatcher_db';
        $user = 'zatcher_user';
        // Matches the ALTER USER password set while creating zatcher_user
        // locally. If you used a different password there, update it here too.
        $pass = 'demo12345';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            die('Database connection failed.');
        }
    }
    return $pdo;
}
