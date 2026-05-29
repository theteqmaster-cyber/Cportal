<?php
/**
 * File: db.php
 * Description: Establishes a secure database connection using PDO.
 * Importance: Essential for all database-driven operations in the portal.
 */

$host = '127.0.0.1';
$db   = 'project_db';
$user = 'php_dev';
$pass = 'secure_pass_123';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Fail gracefully to prevent database details exposure
     error_log("Database connection failed: " . $e->getMessage());
     die("Database connection failed. Please check back later.");
}

// Future Improvements: Migrate database credentials to environment variables (.env) for enhanced security.
?>
