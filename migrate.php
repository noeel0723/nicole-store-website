<?php
/**
 * Migration: Apply database changes
 */
require_once __DIR__ . '/config.php';

$db = getDB();

$migrations = [
    "ALTER TABLE customers MODIFY phone VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE orders ADD COLUMN joki_type ENUM('joki_gendong','joki_login') DEFAULT NULL AFTER request_role",
    "ALTER TABLE orders ADD COLUMN proof_photo VARCHAR(255) DEFAULT NULL AFTER completed_at",
];

foreach ($migrations as $sql) {
    try {
        $db->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        // Ignore duplicate column errors
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP (already exists): $sql\n";
        } else {
            echo "ERROR: " . $e->getMessage() . " -- $sql\n";
        }
    }
}

echo "\nAll migrations completed!\n";
