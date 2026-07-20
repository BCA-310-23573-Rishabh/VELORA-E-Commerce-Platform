<?php
// api/returns.php
require_once '../config.php';
ini_set('display_errors', 0);
set_error_handler(function($no, $str, $file, $line) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Error: $str (line $line)"]);
    exit;
});
setJSONHeaders();
startSession();

function ensureReturnsTable($db) {
    $db->query("CREATE TABLE IF NOT EXISTS return_requests (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        return_number VARCHAR(20) NOT NULL UNIQUE,
        order_id     INT NOT NULL,
        user_id      INT NOT NULL,
        reason       ENUM('damaged','wrong_item','not_as_described','changed_mind','size_issue','other') NOT NULL,
        description  TEXT,
        items_json   TEXT COMMENT 'JSON array of items to return',
        status       ENUM('requested','approved','rejected','pickup_scheduled','received','refunded') DEFAULT 'requested',
        admin_note   TEXT NULL,
        refund_amount DECIMAL(10,2) DEFAULT 0,
        requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
    ) ENGINE=InnoDB");
}

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'request':      requestReturn();    break;
    case 'my_returns':   myReturns();        break;
    case 'list':         listReturns();      break;
    case 'update':       updateReturn();     break;
    case 'get':          getReturn();        break;
    default: sendJSON(['success' => false, 'message' => 'Invalid action'], 400);
}

function requestReturn() {
    requireLogin();
    $body        = getRequestBody();
    $orderNumber = trim($body['orderNumber'] ?? '');
    $reason      = $body['reason']           ?? '';
    $description = trim($body['description'] ?? '');
    $items       = $body['items']            ?? [];

    $validReasons = ['damaged','wrong_item','not_as_described','changed_mind','size_issue','other'];
    if (!$orderNumber)                  sendJSON(['success' => false, 'message' => 'Order number required'], 400);
    if (!in_array($reason, $validReasons)) sendJSON(['success' => false, 'message' => 'Valid return reason required'], 400);

    $db  = getDB();
    ensureReturnsTable($db);
    $uid = (int)$_SESSION['user_id'];

    // Verify order belongs to user and is delivered
    $stmt = $db->prepare("SELECT id, total, status FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->bind_param('si', $orderNumber, $uid);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order)                    sendJSON(['success' => false, 'message' => 'Order not found'], 404);
    if ($order['status'] !== 'delivered') sendJSON(['success' => false, 'message' => 'Only delivered orders can be returned'], 400);

    // Check no existing return for this order
    $check = $db->prepare("SELECT id FROM return_requests WHERE order_id = ?");
    $check->bind_param('i', $order['id']); $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close(); $db->close();
        sendJSON(['success' => false, 'message' => 'A return request already exists for this order'], 400);
    }
    $check->close();

    $returnNumber = 'RTN-' . strtoupper(substr(md5(uniqid(rand(),true)), 0, 8));
    $itemsJson    = json_encode($items);
    $refundAmount = (float)$order['total'];

    $st = $db->prepare(
        "INSERT INTO return_requests (return_number, order_id, user_id, reason, description, items_json, refund_amount)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $st->bind_param('siisssd', $returnNumber, $order['id'], $uid, $reason, $description, $itemsJson, $refundAmount);

    if (!$st->execute()) {
        $err = $st->error; $st->close(); $db->close();
        sendJSON(['success' => false, 'message' => 'Failed: ' . $err], 500);
    }
    $st->close(); $db->close();
    sendJSON(['success' => true, 'message' => 'Return request submitted successfully!', 'returnNumber' => $returnNumber]);
}

function myReturns() {
    requireLogin();
    $db  = getDB();
    ensureReturnsTable($db);
    $uid = (int)$_SESSION['user_id'];
    $stmt = $db->prepare(
        "SELECT rr.*, o.order_number FROM return_requests rr
         JOIN orders o ON rr.order_id = o.id
         WHERE rr.user_id = ? ORDER BY rr.requested_at DESC"
    );
    $stmt->bind_param('i', $uid); $stmt->execute();
    $result = $stmt->get_result(); $list = [];
    while ($row = $result->fetch_assoc()) {
        $list[] = [
            'id'           => (int)$row['id'],
            'returnNumber' => $row['return_number'],
            'orderNumber'  => $row['order_number'],
            'reason'       => $row['reason'],
            'description'  => $row['description'],
            'status'       => $row['status'],
            'refundAmount' => (float)$row['refund_amount'],
            'adminNote'    => $row['admin_note'],
            'date'         => date('d M Y', strtotime($row['requested_at'])),
        ];
    }
    $stmt->close(); $db->close();
    sendJSON(['success' => true, 'returns' => $list]);
}

function listReturns() {
    requireAdmin();
    $db     = getDB();
    ensureReturnsTable($db);
    $status = $_GET['status'] ?? 'all';
    $sql    = "SELECT rr.*, o.order_number, u.first_name, u.last_name, u.email
               FROM return_requests rr
               JOIN orders o ON rr.order_id = o.id
               JOIN users u  ON rr.user_id  = u.id";
    if ($status !== 'all') $sql .= " WHERE rr.status = '" . $db->real_escape_string($status) . "'";
    $sql   .= " ORDER BY rr.requested_at DESC";
    $result = $db->query($sql); $list = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    $db->close();
    sendJSON(['success' => true, 'returns' => $list]);
}

function getReturn() {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) sendJSON(['success' => false, 'message' => 'ID required'], 400);
    $db   = getDB();
    ensureReturnsTable($db);
    $stmt = $db->prepare(
        "SELECT rr.*, o.order_number FROM return_requests rr
         JOIN orders o ON rr.order_id = o.id WHERE rr.id = ?"
    );
    $stmt->bind_param('i', $id); $stmt->execute();
    $row  = $stmt->get_result()->fetch_assoc();
    $stmt->close(); $db->close();
    if (!$row) sendJSON(['success' => false, 'message' => 'Not found'], 404);
    sendJSON(['success' => true, 'return' => $row]);
}

function updateReturn() {
    requireAdmin();
    $body   = getRequestBody();
    $id     = (int)($body['id']          ?? 0);
    $status = $body['status']            ?? '';
    $note   = trim($body['adminNote']    ?? '');
    $refund = (float)($body['refundAmount'] ?? 0);
    $valid  = ['requested','approved','rejected','pickup_scheduled','received','refunded'];
    if (!$id || !in_array($status, $valid))
        sendJSON(['success' => false, 'message' => 'ID and valid status required'], 400);

    $db   = getDB();
    ensureReturnsTable($db);
    $stmt = $db->prepare("UPDATE return_requests SET status=?, admin_note=?, refund_amount=? WHERE id=?");
    $stmt->bind_param('ssdi', $status, $note, $refund, $id);
    $stmt->execute(); $stmt->close(); $db->close();
    sendJSON(['success' => true, 'message' => 'Return updated']);
}
?>
