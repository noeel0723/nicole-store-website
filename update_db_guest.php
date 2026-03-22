<?php
require_once __DIR__ . '/config.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE customers ADD COLUMN is_guest TINYINT(1) DEFAULT 0 AFTER game_id");
    echo "Column added successfully.";
} catch (Exception $e) {
    echo "Error or column already exists: " . $e->getMessage();
}
