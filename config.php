<?php
date_default_timezone_set('Asia/Kolkata');

// Load .env file
$envFile = __DIR__ . '/.env';
$envVars = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) continue;
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $envVars[trim($key)] = trim($value);
        }
    }
}

// Mail configuration
define('MAIL_HOST', $envVars['MAIL_HOST'] ?? '');
define('MAIL_USERNAME', $envVars['MAIL_USERNAME'] ?? '');
define('MAIL_PASSWORD', $envVars['MAIL_PASSWORD'] ?? '');
define('MAIL_PORT', $envVars['MAIL_PORT'] ?? 587);
define('MAIL_FROM_EMAIL', $envVars['MAIL_FROM_EMAIL'] ?? '');
define('MAIL_FROM_NAME', $envVars['MAIL_FROM_NAME'] ?? '');

// Database configuration
define('DB_HOST', $envVars['DB_HOST'] ?? '');
define('DB_USER', $envVars['DB_USER'] ?? '');
define('DB_PASS', $envVars['DB_PASS'] ?? '');
define('DB_NAME', $envVars['DB_NAME'] ?? '');

// Boss credentials
define('BOSS_USERNAME', $envVars['BOSS_USERNAME'] ?? '');
define('BOSS_PASSWORD', $envVars['BOSS_PASSWORD'] ?? '');

$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn) {
    mysqli_query($conn, "SET time_zone = '+05:30'");
}
