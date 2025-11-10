<?php
// mao ni Database configurationn bro
define('DB_HOST', 'localhost');
define('DB_NAME', 'website_security');
define('DB_USER', 'root');
define('DB_PASS', '');

// mao ni Securityy configurationn bro
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('SESSION_TIMEOUT', 1800); // 30 minutes

// mao ni maka Createe databasee connectionnn
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $pdo = null;
}

// mao ni bro maka Starttt sessionnn withhh secureee settingssss brooo
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();

?>
