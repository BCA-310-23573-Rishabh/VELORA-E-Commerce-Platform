<?php
// api/account.php - User account endpoints
require_once '../config.php';

setJSONHeaders();
startSession();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'profile':         getProfile();       break;
    case 'update_profile':  updateProfile();    break;
    case 'change_password': changePassword();   break;
    case 'orders':          getMyOrders();      break;
    case 'order_detail':    getOrderDetail();   break;
    case 'addresses':       getAddresses();     break;
    case 'save_address':    saveAddress();      break;
    case 'delete_address':  deleteAddress();    break;
    default: sendJSON(['success' => false, 'message' => 'Invalid action'], 400);
}

// ── GET PROFILE ───────────────────────────────────────────────────────
function getProfile() {
    requireLogin();
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, created_at FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$user) sendJSON(['success' => false, 'message' => 'User not found'], 404);

    sendJSON([
        'success' => true,
        'user'    => [
            'id'          => (int)$user['id'],
            'firstName'   => $user['first_name'],
            'lastName'    => $user['last_name'],
            'email'       => $user['email'],
            'memberSince' => date('F Y', strtotime($user['created_at']))
        ]
    ]);
}

// ── UPDATE PROFILE ────────────────────────────────────────────────────
function updateProfile() {
    requireLogin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        sendJSON(['success' => false, 'message' => 'POST required'], 405);

    $body      = getRequestBody();
    $firstName = trim($body['firstName'] ?? '');
    $lastName  = trim($body['lastName']  ?? '');
    $email     = trim(strtolower($body['email'] ?? ''));

    if (!$firstName || !$lastName || !$email)
        sendJSON(['success' => false, 'message' => 'All fields are required'], 400);

    if (!preg_match('/^[a-zA-Z\s\'\-]{2,50}$/', $firstName))
        sendJSON(['success' => false, 'message' => 'First name can only contain letters'], 400);

    if (!preg_match('/^[a-zA-Z\s\'\-]{2,50}$/', $lastName))
        sendJSON(['success' => false, 'message' => 'Last name can only contain letters'], 400);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        sendJSON(['success' => false, 'message' => 'Please enter a valid email address'], 400);

    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param('si', $email, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close(); $db->close();
        sendJSON(['success' => false, 'message' => 'This email is already in use'], 409);
    }
    $stmt->close();

    $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
    $stmt->bind_param('sssi', $firstName, $lastName, $email, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    $db->close();

    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name']  = $lastName;
    $_SESSION['email']      = $email;

    sendJSON(['success' => true, 'message' => 'Profile updated successfully']);
}

// ── CHANGE PASSWORD ───────────────────────────────────────────────────
function changePassword() {
    requireLogin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        sendJSON(['success' => false, 'message' => 'POST required'], 405);

    $body        = getRequestBody();
    $current     = $body['currentPassword'] ?? '';
    $newPassword = $body['newPassword']     ?? '';

    if (!$current || !$newPassword)
        sendJSON(['success' => false, 'message' => 'Both fields are required'], 400);

    if (strlen($newPassword) < 6)
        sendJSON(['success' => false, 'message' => 'New password must be at least 6 characters'], 400);

    $db   = getDB();
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($current, $row['password'])) {
        $db->close();
        sendJSON(['success' => false, 'message' => 'Current password is incorrect'], 401);
    }

    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt   = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $hashed, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    $db->close();

    sendJSON(['success' => true, 'message' => 'Password changed successfully']);
}

// ── MY ORDERS ─────────────────────────────────────────────────────────
function getMyOrders() {
    requireLogin();
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT o.*, COUNT(oi.id) as item_count
         FROM orders o
         LEFT JOIN order_items oi ON o.id = oi.order_id
         WHERE o.user_id = ?
         GROUP BY o.id
         ORDER BY o.created_at DESC"
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];

    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'id'          => (int)$row['id'],
            'orderNumber' => $row['order_number'],
            'date'        => date('d M Y', strtotime($row['created_at'])),
            'status'      => $row['status'],
            'total'       => (float)$row['total'],
            'itemCount'   => (int)$row['item_count'],
            'createdAt'   => $row['created_at']
        ];
    }

    $stmt->close();
    $db->close();
    sendJSON(['success' => true, 'orders' => $orders]);
}

// ── ORDER DETAIL ──────────────────────────────────────────────────────
function getOrderDetail() {
    requireLogin();
    $orderNumber = $_GET['order_number'] ?? '';
    if (!$orderNumber) sendJSON(['success' => false, 'message' => 'Order number required'], 400);

    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->bind_param('si', $orderNumber, $_SESSION['user_id']);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) { $db->close(); sendJSON(['success' => false, 'message' => 'Order not found'], 404); }

    $stmt   = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->bind_param('i', $order['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $items  = [];

    while ($item = $result->fetch_assoc()) {
        $items[] = [
            'name'     => $item['product_name'],
            'price'    => (float)$item['price'],
            'quantity' => (int)$item['quantity'],
            'size'     => $item['size'],
            'color'    => $item['color'],
            'total'    => (float)$item['item_total']
        ];
    }
    $stmt->close();
    $db->close();

    $allSteps   = ['pending', 'processing', 'shipped', 'delivered'];
    $currentIdx = array_search($order['status'], $allSteps);
    if ($currentIdx === false) $currentIdx = -1;

    // Determine pincode column name (handle both old and new schema)
    $pincode = $order['shipping_pincode'] ?? $order['shipping_zip'] ?? '';

    sendJSON([
        'success' => true,
        'order'   => [
            'orderNumber'     => $order['order_number'],
            'date'            => date('d M Y', strtotime($order['created_at'])),
            'status'          => $order['status'],
            'items'           => $items,
            'subtotal'        => (float)$order['subtotal'],
            'gstAmount'       => (float)($order['gst_amount'] ?? 0),
            'discount'        => (float)$order['discount'],
            'shipping'        => (float)$order['shipping_cost'],
            'total'           => (float)$order['total'],
            'shippingAddress' => [
                'address' => $order['shipping_address'],
                'city'    => $order['shipping_city'],
                'state'   => $order['shipping_state'],
                'pincode' => $pincode,
                'country' => $order['shipping_country']
            ],
            'trackingSteps' => [
                ['label' => 'Order Placed', 'done' => $currentIdx >= 0, 'icon' => '📋'],
                ['label' => 'Processing',   'done' => $currentIdx >= 1, 'icon' => '⚙️'],
                ['label' => 'Shipped',      'done' => $currentIdx >= 2, 'icon' => '🚚'],
                ['label' => 'Delivered',    'done' => $currentIdx >= 3, 'icon' => '✅'],
            ]
        ]
    ]);
}

// ── ADDRESSES ─────────────────────────────────────────────────────────

// Creates the table if it doesn't exist — uses pincode column throughout
function ensureAddressTable($db) {
    $db->query("CREATE TABLE IF NOT EXISTS user_addresses (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT NOT NULL,
        label       VARCHAR(50)  DEFAULT 'Home',
        full_name   VARCHAR(200),
        address     VARCHAR(300),
        city        VARCHAR(100),
        state       VARCHAR(100),
        pincode     VARCHAR(10),
        country     VARCHAR(100) DEFAULT 'India',
        phone       VARCHAR(15),
        is_default  TINYINT(1)   DEFAULT 0,
        created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // If table already exists with old 'zip' column, add 'pincode' column safely
    $check = $db->query("SHOW COLUMNS FROM user_addresses LIKE 'pincode'");
    if ($check && $check->num_rows === 0) {
        $db->query("ALTER TABLE user_addresses ADD COLUMN pincode VARCHAR(10) AFTER state");
        // Copy existing zip data to pincode if zip column exists
        $zipCheck = $db->query("SHOW COLUMNS FROM user_addresses LIKE 'zip'");
        if ($zipCheck && $zipCheck->num_rows > 0) {
            $db->query("UPDATE user_addresses SET pincode = zip");
        }
    }
}

function getAddresses() {
    requireLogin();
    $db = getDB();
    ensureAddressTable($db);

    $stmt = $db->prepare(
        "SELECT id, label, full_name, address, city, state, pincode, country, phone, is_default
         FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC"
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result    = $stmt->get_result();
    $addresses = [];

    while ($row = $result->fetch_assoc()) {
        $addresses[] = [
            'id'        => (int)$row['id'],
            'label'     => $row['label'],
            'fullName'  => $row['full_name'],
            'address'   => $row['address'],
            'city'      => $row['city'],
            'state'     => $row['state'],
            'pincode'   => $row['pincode'],
            'country'   => $row['country'] ?: 'India',
            'phone'     => $row['phone'],
            'isDefault' => (bool)$row['is_default']
        ];
    }

    $stmt->close();
    $db->close();
    sendJSON(['success' => true, 'addresses' => $addresses]);
}

function saveAddress() {
    requireLogin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        sendJSON(['success' => false, 'message' => 'POST required'], 405);

    $body      = getRequestBody();
    $id        = (int)($body['id']        ?? 0);
    $label     = trim($body['label']      ?? 'Home');
    $fullName  = trim($body['fullName']   ?? '');
    $address   = trim($body['address']    ?? '');
    $city      = trim($body['city']       ?? '');
    $state     = trim($body['state']      ?? '');
    $pincode   = trim($body['pincode']    ?? '');
    $country   = trim($body['country']    ?? 'India');
    $phone     = trim($body['phone']      ?? '');
    $isDefault = (int)($body['isDefault'] ?? 0);

    // Required fields
    if (!$address || !$city)
        sendJSON(['success' => false, 'message' => 'Address and city are required'], 400);

    // Validate Indian PIN code (exactly 6 digits)
    if ($pincode !== '' && !preg_match('/^[1-9][0-9]{5}$/', $pincode))
        sendJSON(['success' => false, 'message' => 'Please enter a valid 6-digit PIN code'], 400);

    // Validate Indian mobile number (10 digits starting with 6-9)
    if ($phone !== '' && !preg_match('/^[6-9][0-9]{9}$/', $phone))
        sendJSON(['success' => false, 'message' => 'Please enter a valid 10-digit Indian mobile number'], 400);

    $db = getDB();
    ensureAddressTable($db);

    // Clear other defaults first if this is being set as default
    if ($isDefault) {
        $stmt0 = $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $stmt0->bind_param('i', $_SESSION['user_id']);
        $stmt0->execute();
        $stmt0->close();
    }

    if ($id) {
        // UPDATE existing address
        $stmt = $db->prepare(
            "UPDATE user_addresses
             SET label=?, full_name=?, address=?, city=?, state=?, pincode=?, country=?, phone=?, is_default=?
             WHERE id=? AND user_id=?"
        );
        $stmt->bind_param(
            'sssssssiii',
            $label, $fullName, $address, $city, $state, $pincode, $country, $phone,
            $isDefault, $id, $_SESSION['user_id']
        );
    } else {
        // INSERT new address
        $stmt = $db->prepare(
            "INSERT INTO user_addresses (user_id, label, full_name, address, city, state, pincode, country, phone, is_default)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'issssssssi',
            $_SESSION['user_id'], $label, $fullName, $address, $city, $state, $pincode, $country, $phone, $isDefault
        );
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        $db->close();
        sendJSON(['success' => false, 'message' => 'Failed to save address: ' . $err], 500);
    }

    $newId = $id ?: $db->insert_id;
    $stmt->close();
    $db->close();

    sendJSON(['success' => true, 'message' => 'Address saved successfully', 'id' => $newId]);
}

function deleteAddress() {
    requireLogin();
    $body = getRequestBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) sendJSON(['success' => false, 'message' => 'Address ID required'], 400);

    $db   = getDB();
    ensureAddressTable($db);
    $stmt = $db->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    $db->close();

    sendJSON(['success' => true, 'message' => 'Address deleted successfully']);
}
?>
