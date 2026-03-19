<?php
/**
 * API: Customer Search (AJAX Autocomplete)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDB();
$query = $_GET['q'] ?? '';

if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("SELECT id, name, phone, game_id FROM customers WHERE name LIKE ? OR game_id LIKE ? OR phone LIKE ? ORDER BY name LIMIT 10");
$stmt->execute(["%$query%", "%$query%", "%$query%"]);
$results = $stmt->fetchAll();

echo json_encode($results);
