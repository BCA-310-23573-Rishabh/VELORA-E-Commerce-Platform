<?php
// api/admin.php - Admin-only endpoints
require_once '../config.php';

setJSONHeaders();
startSession();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'stats':
        getDashboardStats();
        break;
    case 'users':
        listUsers();
        break;
    case 'make_admin':
        makeAdmin();
        break;
    case 'delete_user':
        deleteUser();
        break;
    default:
        sendJSON(['success' => false, 'message' => 'Invalid action'], 400);
}

// =============================================
// DASHBOARD STATS
// =============================================
function getDashboardStats() {
    requireAdmin();
    $db = getDB();

    // Total products
    $r1 = $db->query("SELECT COUNT(*) as cnt FROM products WHERE is_active = 1");
    $totalProducts = $r1->fetch_assoc()['cnt'];

    // Total orders
    $r2 = $db->query("SELECT COUNT(*) as cnt FROM orders");
    $totalOrders = $r2->fetch_assoc()['cnt'];

    // Total revenue
    $r3 = $db->query("SELECT COALESCE(SUM(total), 0) as revenue FROM orders WHERE status != 'cancelled'");
    $totalRevenue = (float)$r3->fetch_assoc()['revenue'];

    // Total users
    $r4 = $db->query("SELECT COUNT(*) as cnt FROM users");
    $totalUsers = $r4->fetch_assoc()['cnt'];

    // Recent 5 orders
    $r5 = $db->query(
        "SELECT order_number, customer_name, total, status, created_at
         FROM orders ORDER BY created_at DESC LIMIT 5"
    );
    $recentOrders = [];
    while ($row = $r5->fetch_assoc()) {
        $recentOrders[] = [
            'orderNumber' => $row['order_number'],
            'customer'    => ['name' => $row['customer_name']],
            'total'       => (float)$row['total'],
            'status'      => $row['status'],
            'date'        => date('M d, Y', strtotime($row['created_at']))
        ];
    }

    // Low stock products (stock < 5)
    $r6 = $db->query("SELECT id, name, stock FROM products WHERE stock < 5 AND is_active = 1 ORDER BY stock ASC");
    $lowStock = [];
    while ($row = $r6->fetch_assoc()) {
        $lowStock[] = [
            'id'    => (int)$row['id'],
            'name'  => $row['name'],
            'stock' => (int)$row['stock']
        ];
    }

    $db->close();

    sendJSON([
        'success'       => true,
        'totalProducts' => (int)$totalProducts,
        'totalOrders'   => (int)$totalOrders,
        'totalRevenue'  => $totalRevenue,
        'totalUsers'    => (int)$totalUsers,
        'recentOrders'  => $recentOrders,
        'lowStock'      => $lowStock
    ]);
}

// =============================================
// LIST USERS
// =============================================
function listUsers() {
    requireAdmin();
    $db     = getDB();
    $result = $db->query("SELECT id, first_name, last_name, email, is_admin, created_at FROM users ORDER BY id ASC");
    $users  = [];

    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id'        => (int)$row['id'],
            'firstName' => $row['first_name'],
            'lastName'  => $row['last_name'],
            'email'     => $row['email'],
            'isAdmin'   => (bool)$row['is_admin'],
            'createdAt' => $row['created_at']
        ];
    }

    $db->close();
    sendJSON(['success' => true, 'users' => $users]);
}

// =============================================
// MAKE USER ADMIN
// =============================================
function makeAdmin() {
    requireAdmin();
    $body   = getRequestBody();
    $userId = (int)($body['userId'] ?? 0);

    if (!$userId) sendJSON(['success' => false, 'message' => 'User ID required'], 400);

    $db   = getDB();
    $stmt = $db->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    $db->close();

    sendJSON(['success' => true, 'message' => 'User promoted to admin']);
}

// =============================================
// DELETE USER
// =============================================
function deleteUser() {
    requireAdmin();
    $body   = getRequestBody();
    $userId = (int)($body['userId'] ?? 0);

    // Prevent self-deletion
    if ($userId === (int)$_SESSION['user_id']) {
        sendJSON(['success' => false, 'message' => 'Cannot delete your own account'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    $db->close();

    sendJSON(['success' => true, 'message' => 'User deleted']);
}
?>
