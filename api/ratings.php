<?php
// api/ratings.php
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

function ensureRatingsTable($db) {
    $db->query("CREATE TABLE IF NOT EXISTS product_ratings (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        product_id  INT NOT NULL,
        user_id     INT NULL,
        reviewer_name VARCHAR(200) NOT NULL,
        rating      TINYINT(1) NOT NULL COMMENT '1-5',
        review      TEXT,
        is_verified TINYINT(1) DEFAULT 0 COMMENT 'purchased this product',
        status      ENUM('pending','approved','rejected') DEFAULT 'approved',
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_product (user_id, product_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
    ) ENGINE=InnoDB");
}

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'submit':       submitRating();       break;
    case 'get_product':  getProductRatings();  break;
    case 'list_all':     listAllRatings();     break;
    case 'moderate':     moderateRating();     break;
    case 'delete':       deleteRating();       break;
    default: sendJSON(['success' => false, 'message' => 'Invalid action'], 400);
}

function submitRating() {
    $body      = getRequestBody();
    $productId = (int)($body['productId']    ?? 0);
    $rating    = (int)($body['rating']       ?? 0);
    $review    = trim($body['review']        ?? '');
    $reviewer  = trim($body['reviewerName']  ?? '');

    if (!$productId)         sendJSON(['success' => false, 'message' => 'Product ID required'], 400);
    if ($rating < 1 || $rating > 5) sendJSON(['success' => false, 'message' => 'Rating must be 1-5'], 400);
    if (!$reviewer)          sendJSON(['success' => false, 'message' => 'Your name is required'], 400);

    $db = getDB();
    ensureRatingsTable($db);

    $userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $isVerified = 0;

    // Check if user has purchased this product
    if ($userId) {
        $check = $db->prepare(
            "SELECT oi.id FROM order_items oi
             JOIN orders o ON oi.order_id = o.id
             WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered' LIMIT 1"
        );
        $check->bind_param('ii', $userId, $productId);
        $check->execute();
        $isVerified = $check->get_result()->num_rows > 0 ? 1 : 0;
        $check->close();

        // Check for existing rating
        $exist = $db->prepare("SELECT id FROM product_ratings WHERE user_id = ? AND product_id = ?");
        $exist->bind_param('ii', $userId, $productId);
        $exist->execute();
        $existRow = $exist->get_result()->fetch_assoc();
        $exist->close();

        if ($existRow) {
            // Update existing
            $st = $db->prepare("UPDATE product_ratings SET rating=?, review=?, reviewer_name=?, is_verified=? WHERE id=?");
            $st->bind_param('issii', $rating, $review, $reviewer, $isVerified, $existRow['id']);
            $st->execute(); $st->close(); $db->close();
            sendJSON(['success' => true, 'message' => 'Review updated!']);
        }
    }

    $stmt = $db->prepare(
        "INSERT INTO product_ratings (product_id, user_id, reviewer_name, rating, review, is_verified)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iisisi', $productId, $userId, $reviewer, $rating, $review, $isVerified);

    if (!$stmt->execute()) {
        $err = $stmt->error; $stmt->close(); $db->close();
        sendJSON(['success' => false, 'message' => 'Failed to save review: ' . $err], 500);
    }
    $stmt->close(); $db->close();
    sendJSON(['success' => true, 'message' => 'Thank you for your review!']);
}

function getProductRatings() {
    $productId = (int)($_GET['product_id'] ?? 0);
    if (!$productId) sendJSON(['success' => false, 'message' => 'Product ID required'], 400);

    $db = getDB();
    ensureRatingsTable($db);

    $stmt = $db->prepare(
        "SELECT pr.*, u.first_name, u.last_name
         FROM product_ratings pr LEFT JOIN users u ON pr.user_id = u.id
         WHERE pr.product_id = ? AND pr.status = 'approved'
         ORDER BY pr.created_at DESC"
    );
    $stmt->bind_param('i', $productId); $stmt->execute();
    $result  = $stmt->get_result();
    $ratings = [];
    $total   = 0; $count = 0;

    while ($row = $result->fetch_assoc()) {
        $total += $row['rating']; $count++;
        $ratings[] = [
            'id'           => (int)$row['id'],
            'rating'       => (int)$row['rating'],
            'review'       => $row['review'],
            'reviewerName' => $row['reviewer_name'],
            'isVerified'   => (bool)$row['is_verified'],
            'date'         => date('d M Y', strtotime($row['created_at'])),
        ];
    }
    $stmt->close(); $db->close();

    sendJSON([
        'success'     => true,
        'ratings'     => $ratings,
        'average'     => $count > 0 ? round($total / $count, 1) : 0,
        'count'       => $count,
        'breakdown'   => array_count_values(array_column($ratings, 'rating'))
    ]);
}

function listAllRatings() {
    requireAdmin();
    $db = getDB();
    ensureRatingsTable($db);
    $status = $_GET['status'] ?? 'all';
    $sql    = "SELECT pr.*, p.name AS product_name FROM product_ratings pr LEFT JOIN products p ON pr.product_id = p.id";
    if ($status !== 'all') $sql .= " WHERE pr.status = '" . $db->real_escape_string($status) . "'";
    $sql   .= " ORDER BY pr.created_at DESC";
    $result = $db->query($sql);
    $list   = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    $db->close();
    sendJSON(['success' => true, 'ratings' => $list]);
}

function moderateRating() {
    requireAdmin();
    $body   = getRequestBody();
    $id     = (int)($body['id'] ?? 0);
    $status = in_array($body['status'] ?? '', ['approved','rejected']) ? $body['status'] : '';
    if (!$id || !$status) sendJSON(['success' => false, 'message' => 'ID and status required'], 400);
    $db   = getDB();
    ensureRatingsTable($db);
    $stmt = $db->prepare("UPDATE product_ratings SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id); $stmt->execute(); $stmt->close(); $db->close();
    sendJSON(['success' => true, 'message' => 'Rating ' . $status]);
}

function deleteRating() {
    requireAdmin();
    $body = getRequestBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) sendJSON(['success' => false, 'message' => 'ID required'], 400);
    $db   = getDB();
    ensureRatingsTable($db);
    $stmt = $db->prepare("DELETE FROM product_ratings WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); $db->close();
    sendJSON(['success' => true, 'message' => 'Deleted']);
}
?>
