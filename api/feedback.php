<?php
// api/feedback.php
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

function ensureFeedbackTable($db) {
    $db->query("CREATE TABLE IF NOT EXISTS feedback (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT NULL,
        name        VARCHAR(200) NOT NULL,
        email       VARCHAR(255) NOT NULL,
        subject     VARCHAR(255),
        message     TEXT NOT NULL,
        rating      TINYINT(1) DEFAULT NULL COMMENT '1-5 stars',
        type        ENUM('general','product','service','complaint','suggestion') DEFAULT 'general',
        status      ENUM('new','read','replied','resolved') DEFAULT 'new',
        admin_reply TEXT NULL,
        replied_at  DATETIME NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");
}

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'submit':       submitFeedback();   break;
    case 'list':         listFeedback();     break;
    case 'get':          getFeedback();      break;
    case 'update':       updateFeedback();   break;
    case 'reply':        replyFeedback();    break;
    case 'delete':       deleteFeedback();   break;
    case 'my_feedback':  myFeedback();       break;
    default: sendJSON(['success' => false, 'message' => 'Invalid action'], 400);
}

function submitFeedback() {
    $body    = getRequestBody();
    $name    = trim($body['name']    ?? '');
    $email   = trim($body['email']   ?? '');
    $subject = trim($body['subject'] ?? '');
    $message = trim($body['message'] ?? '');
    $rating  = isset($body['rating']) ? (int)$body['rating'] : null;
    $type    = in_array($body['type'] ?? '', ['general','product','service','complaint','suggestion'])
               ? $body['type'] : 'general';

    if (!$name || !$email || !$message)
        sendJSON(['success' => false, 'message' => 'Name, email and message are required.'], 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        sendJSON(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
    if ($rating !== null && ($rating < 1 || $rating > 5))
        sendJSON(['success' => false, 'message' => 'Rating must be between 1 and 5.'], 400);

    $db = getDB();
    ensureFeedbackTable($db);

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $stmt   = $db->prepare(
        "INSERT INTO feedback (user_id, name, email, subject, message, rating, type)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issssss', $userId, $name, $email, $subject, $message, $rating, $type);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();
    $db->close();

    sendJSON(['success' => true, 'message' => 'Thank you for your feedback!', 'id' => $newId]);
}

function listFeedback() {
    requireAdmin();
    $db = getDB();
    ensureFeedbackTable($db);

    $status = $_GET['status'] ?? 'all';
    $type   = $_GET['type']   ?? 'all';

    $sql    = "SELECT f.*, u.first_name, u.last_name FROM feedback f LEFT JOIN users u ON f.user_id = u.id WHERE 1=1";
    $params = []; $types = '';
    if ($status !== 'all') { $sql .= " AND f.status = ?"; $params[] = $status; $types .= 's'; }
    if ($type   !== 'all') { $sql .= " AND f.type = ?";   $params[] = $type;   $types .= 's'; }
    $sql .= " ORDER BY f.created_at DESC";

    $stmt = $db->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result   = $stmt->get_result();
    $feedback = [];

    while ($row = $result->fetch_assoc()) {
        $feedback[] = [
            'id'         => (int)$row['id'],
            'userId'     => $row['user_id'],
            'userName'   => $row['user_id'] ? ($row['first_name'] . ' ' . $row['last_name']) : null,
            'name'       => $row['name'],
            'email'      => $row['email'],
            'subject'    => $row['subject'],
            'message'    => $row['message'],
            'rating'     => $row['rating'] ? (int)$row['rating'] : null,
            'type'       => $row['type'],
            'status'     => $row['status'],
            'adminReply' => $row['admin_reply'],
            'repliedAt'  => $row['replied_at'],
            'date'       => date('d M Y', strtotime($row['created_at'])),
        ];
    }
    $stmt->close(); $db->close();
    sendJSON(['success' => true, 'feedback' => $feedback]);
}

function getFeedback() {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) sendJSON(['success' => false, 'message' => 'ID required'], 400);

    $db   = getDB();
    ensureFeedbackTable($db);
    $stmt = $db->prepare("SELECT * FROM feedback WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $row  = $stmt->get_result()->fetch_assoc();
    $stmt->close(); $db->close();

    if (!$row) sendJSON(['success' => false, 'message' => 'Not found'], 404);

    // Mark as read
    $db2  = getDB();
    $st2  = $db2->prepare("UPDATE feedback SET status = 'read' WHERE id = ? AND status = 'new'");
    $st2->bind_param('i', $id); $st2->execute(); $st2->close(); $db2->close();

    sendJSON(['success' => true, 'feedback' => $row]);
}

function updateFeedback() {
    requireAdmin();
    $body   = getRequestBody();
    $id     = (int)($body['id']     ?? 0);
    $status = $body['status'] ?? '';
    $valid  = ['new','read','replied','resolved'];
    if (!$id || !in_array($status, $valid))
        sendJSON(['success' => false, 'message' => 'ID and valid status required'], 400);

    $db   = getDB();
    ensureFeedbackTable($db);
    $stmt = $db->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id); $stmt->execute(); $stmt->close(); $db->close();
    sendJSON(['success' => true, 'message' => 'Status updated']);
}

function replyFeedback() {
    requireAdmin();
    $body  = getRequestBody();
    $id    = (int)($body['id']    ?? 0);
    $reply = trim($body['reply']  ?? '');
    if (!$id || !$reply)
        sendJSON(['success' => false, 'message' => 'ID and reply are required'], 400);

    $db  = getDB();
    ensureFeedbackTable($db);
    $now = date('Y-m-d H:i:s');
    $st  = $db->prepare("UPDATE feedback SET admin_reply = ?, status = 'replied', replied_at = ? WHERE id = ?");
    $st->bind_param('ssi', $reply, $now, $id); $st->execute(); $st->close(); $db->close();
    sendJSON(['success' => true, 'message' => 'Reply saved']);
}

function deleteFeedback() {
    requireAdmin();
    $body = getRequestBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) sendJSON(['success' => false, 'message' => 'ID required'], 400);

    $db   = getDB();
    ensureFeedbackTable($db);
    $stmt = $db->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); $db->close();
    sendJSON(['success' => true, 'message' => 'Deleted']);
}

function myFeedback() {
    requireLogin();
    $db = getDB();
    ensureFeedbackTable($db);
    $uid  = (int)$_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $result = $stmt->get_result(); $list = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    $stmt->close(); $db->close();
    sendJSON(['success' => true, 'feedback' => $list]);
}
?>
