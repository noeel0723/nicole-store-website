<?php
/**
 * Aero's Store - Configuration
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'aeros_store');
define('DB_USER', 'root');
define('DB_PASS', '');

// App Configuration
define('APP_NAME', "Aero's Store");
define('APP_URL', 'http://localhost/aero\'s_store');
define('APP_VERSION', '1.0.0');

// MLBB Ranks
define('MLBB_RANKS', [
    'Warrior IV', 'Warrior III', 'Warrior II', 'Warrior I',
    'Elite IV', 'Elite III', 'Elite II', 'Elite I',
    'Master IV', 'Master III', 'Master II', 'Master I',
    'Grandmaster IV', 'Grandmaster III', 'Grandmaster II', 'Grandmaster I',
    'Epic V', 'Epic IV', 'Epic III', 'Epic II', 'Epic I',
    'Legend V', 'Legend IV', 'Legend III', 'Legend II', 'Legend I',
    'Mythic V', 'Mythic IV', 'Mythic III', 'Mythic II', 'Mythic I',
    'Mythical Honor',
    'Mythical Glory',
    'Immortal'
]);

// MLBB Roles
define('MLBB_ROLES', [
    'Midlaner', 'Roam', 'Jungler', 'Goldlane', 'Exp Lane'
]);

// PDO Database Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}
