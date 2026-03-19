<?php
/**
 * API: Orders & Status Update (AJAX)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'update_status':
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $validStatuses = ['unassigned', 'in_progress', 'pending_verification', 'completed'];

        if ($orderId && in_array($newStatus, $validStatuses)) {
            $completedAt = $newStatus === 'completed' ? date('Y-m-d H:i:s') : null;
            $stmt = $db->prepare("UPDATE orders SET status = ?, completed_at = COALESCE(?, completed_at), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $completedAt, $orderId]);

            if ($newStatus === 'completed') {
                $order = $db->prepare("SELECT * FROM orders WHERE id = ?");
                $order->execute([$orderId]);
                $orderData = $order->fetch();
                if ($orderData && $orderData['worker_id'] && $orderData['worker_commission'] > 0) {
                    $check = $db->prepare("SELECT COUNT(*) FROM worker_commissions WHERE order_id = ? AND type = 'earned'");
                    $check->execute([$orderId]);
                    if ($check->fetchColumn() == 0) {
                        $stmt = $db->prepare("INSERT INTO worker_commissions (worker_id, order_id, amount, type, notes) VALUES (?, ?, ?, 'earned', ?)");
                        $stmt->execute([$orderData['worker_id'], $orderId, $orderData['worker_commission'], 'Komisi pesanan #' . $orderId]);
                    }
                }
                if ($orderData) {
                    $db->prepare("UPDATE customers SET total_orders = total_orders + 1, total_spent = total_spent + ? WHERE id = ?")->execute([$orderData['price'], $orderData['customer_id']]);
                }
            }

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Invalid data']);
        }
        break;

    case 'delete_order':
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId) {
            $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Invalid ID']);
        }
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
