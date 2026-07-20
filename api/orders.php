<?php
/**
 * api/orders.php
 * Handles: place order, list, get, update status, update tracking
 * Self-healing: auto-migrates DB columns on every call
 */

// Catch ALL PHP errors and return them as JSON (prevents blank responses)
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($no, $str, $file, $line) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Server error: $str (line $line)"]);
    exit;
});
set_exception_handler(function($e) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer/PHPMailer.php';
require_once __DIR__ . '/../mailer/SMTP.php';
require_once __DIR__ . '/../mailer/Exception.php';
setJSONHeaders();
startSession();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'place':           placeOrder();         break;
    case 'list':            listOrders();         break;
    case 'get':             getOrder();           break;
    case 'retry_sms':       retrySMS();           break;
    case 'update_status':   updateOrderStatus();  break;
    case 'update_tracking': updateTracking();     break;
    case 'diagnose':        diagnoseSchema();     break;
    default:
        sendJSON(['success' => false, 'message' => 'Unknown action: ' . $action], 400);
}

// ─────────────────────────────────────────────────────────────────────
// SCHEMA MIGRATION  (safe to run on every request)
// ─────────────────────────────────────────────────────────────────────
function migrate($db) {
    $migrations = [
        // products
        "products.gst_rate" => "ALTER TABLE products ADD COLUMN gst_rate DECIMAL(5,2) NOT NULL DEFAULT 12.00 AFTER price",
        // orders
        "orders.gst_amount" => "ALTER TABLE orders ADD COLUMN gst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal",
        "orders.tracking_note" => "ALTER TABLE orders ADD COLUMN tracking_note TEXT NULL AFTER status",
        "orders.tracking_updated_at" => "ALTER TABLE orders ADD COLUMN tracking_updated_at DATETIME NULL AFTER tracking_note",
        "orders.sms_otp" => "ALTER TABLE orders ADD COLUMN sms_otp VARCHAR(10) NULL AFTER tracking_updated_at",
        "orders.sms_otp_sent_at" => "ALTER TABLE orders ADD COLUMN sms_otp_sent_at DATETIME NULL AFTER sms_otp",
        "orders.invoice_token" => "ALTER TABLE orders ADD COLUMN invoice_token VARCHAR(64) NULL AFTER sms_otp_sent_at",
        "orders.sms_status" => "ALTER TABLE orders ADD COLUMN sms_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER invoice_token",
        "orders.sms_error" => "ALTER TABLE orders ADD COLUMN sms_error TEXT NULL AFTER sms_status",
        "orders.sms_retry_count" => "ALTER TABLE orders ADD COLUMN sms_retry_count INT NOT NULL DEFAULT 0 AFTER sms_error",
        "orders.sms_last_attempt_at" => "ALTER TABLE orders ADD COLUMN sms_last_attempt_at DATETIME NULL AFTER sms_retry_count",
        // order_items
        "order_items.gst_rate" => "ALTER TABLE order_items ADD COLUMN gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER price",
        "order_items.gst_amount" => "ALTER TABLE order_items ADD COLUMN gst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER gst_rate",
    ];

    foreach ($migrations as $key => $sql) {
        list($table, $column) = explode('.', $key);
        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if ($check && $check->num_rows === 0) {
            $db->query($sql);
        }
    }

    // Rename shipping_zip → shipping_pincode if old schema
    $hasZip     = $db->query("SHOW COLUMNS FROM orders LIKE 'shipping_zip'");
    $hasPincode = $db->query("SHOW COLUMNS FROM orders LIKE 'shipping_pincode'");
    if ($hasZip && $hasZip->num_rows > 0 && $hasPincode && $hasPincode->num_rows === 0) {
        $db->query("ALTER TABLE orders CHANGE shipping_zip shipping_pincode VARCHAR(10)");
    }
    if ($hasPincode && $hasPincode->num_rows === 0 && (!$hasZip || $hasZip->num_rows === 0)) {
        $db->query("ALTER TABLE orders ADD COLUMN shipping_pincode VARCHAR(10) AFTER shipping_state");
    }

    // SMS logging table for retry diagnostics
    $smsLogCheck = $db->query("SHOW TABLES LIKE 'sms_logs'");
    if ($smsLogCheck && $smsLogCheck->num_rows === 0) {
        $db->query(
            "CREATE TABLE IF NOT EXISTS sms_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                order_number VARCHAR(32) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                attempt_at DATETIME NOT NULL,
                success TINYINT(1) NOT NULL,
                status VARCHAR(20) NOT NULL,
                error_text TEXT NULL,
                response_text TEXT NULL,
                request_payload TEXT NULL,
                message_text TEXT NULL,
                INDEX(order_id),
                INDEX(order_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } else {
        $smsLogColumn = $db->query("SHOW COLUMNS FROM `sms_logs` LIKE 'request_payload'");
        if ($smsLogColumn && $smsLogColumn->num_rows === 0) {
            $db->query("ALTER TABLE sms_logs ADD COLUMN request_payload TEXT NULL AFTER response_text");
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
// DIAGNOSE  — visit ?action=diagnose to check your DB
// ─────────────────────────────────────────────────────────────────────
function diagnoseSchema() {
    $db = getDB();
    migrate($db);
    $out = [];
    foreach (['products', 'orders', 'order_items'] as $table) {
        $cols   = [];
        $result = $db->query("SHOW COLUMNS FROM `{$table}`");
        while ($row = $result->fetch_assoc()) {
            $cols[] = $row['Field'] . ' (' . $row['Type'] . ')';
        }
        $count  = $db->query("SELECT COUNT(*) AS c FROM `{$table}`")->fetch_assoc()['c'];
        $out[$table] = ['columns' => $cols, 'rows' => (int)$count];
    }
    $db->close();
    sendJSON(['success' => true, 'schema' => $out]);
}

// ─────────────────────────────────────────────────────────────────────
// PLACE ORDER
// ─────────────────────────────────────────────────────────────────────
function placeOrder() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'message' => 'POST method required'], 405);
    }

    $body = getRequestBody();

    // Read fields
    $email    = trim(isset($body['email'])     ? $body['email']     : '');
    $phone    = trim(isset($body['phone'])     ? $body['phone']     : '');
    $fname    = trim(isset($body['firstName']) ? $body['firstName'] : '');
    $lname    = trim(isset($body['lastName'])  ? $body['lastName']  : '');
    $address  = trim(isset($body['address'])   ? $body['address']   : '');
    $city     = trim(isset($body['city'])      ? $body['city']      : '');
    $state    = trim(isset($body['state'])     ? $body['state']     : '');
    $pincode  = trim(isset($body['pincode'])   ? $body['pincode']   : '');
    $country  = trim(isset($body['country'])   ? $body['country']   : 'India');
    $payment  = trim(isset($body['payment'])   ? $body['payment']   : 'upi');
    $promo    = strtoupper(trim(isset($body['promoCode']) ? $body['promoCode'] : ''));
    $items    = isset($body['items']) ? $body['items'] : [];
    $custName = trim($fname . ' ' . $lname);

    // Validate
    if (empty($items)) {
        sendJSON(['success' => false, 'message' => 'Your cart is empty.'], 400);
    }
    if (empty($email)) {
        sendJSON(['success' => false, 'message' => 'Email address is required.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJSON(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
    }
    if (empty($fname)) {
        sendJSON(['success' => false, 'message' => 'First name is required.'], 400);
    }
    if (empty($address)) {
        sendJSON(['success' => false, 'message' => 'Shipping address is required.'], 400);
    }
    if (empty($city)) {
        sendJSON(['success' => false, 'message' => 'City is required.'], 400);
    }
    if (!empty($phone) && !preg_match('/^[6-9][0-9]{9}$/', $phone)) {
        sendJSON(['success' => false, 'message' => 'Mobile number must be 10 digits starting with 6-9.'], 400);
    }
    if (!empty($pincode) && !preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
        sendJSON(['success' => false, 'message' => 'PIN code must be 6 digits.'], 400);
    }

    // Connect and migrate schema
    $db = getDB(true);
    if (!$db) {
        $demoOrderNumber = 'VLR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $demoTotal = round($subtotal + $gstTotal - $discount + (($subtotal + $gstTotal) >= 5000.0 ? 0 : 99), 2);
        sendJSON([
            'success' => true,
            'message' => 'Order placed successfully (demo mode).',
            'orderNumber' => $demoOrderNumber,
            'orderId' => 0,
            'subtotal' => round($subtotal, 2),
            'gstAmount' => round($gstTotal, 2),
            'discount' => round($discount, 2),
            'shipping' => (($subtotal + $gstTotal) >= 5000.0 ? 0 : 99),
            'total' => round($demoTotal, 2),
            'emailSent' => false,
            'smsSent' => false,
            'smsStatus' => 'skipped',
            'smsRetryCount' => 0,
            'smsWarning' => 'Database unavailable; order stored in demo mode.'
        ]);
    }
    migrate($db);

    // Calculate totals
    $subtotal = 0.0;
    $gstTotal = 0.0;
    $rows     = [];

    foreach ($items as $item) {
        $pid   = isset($item['id'])       ? (int)$item['id']       : null;
        $pname = isset($item['name'])     ? trim($item['name'])     : 'Product';
        $price = isset($item['price'])    ? (float)$item['price']  : 0.0;
        $qty   = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $sz    = isset($item['size'])     ? trim($item['size'])     : '';
        $clr   = isset($item['color'])    ? trim($item['color'])   : '';

        if ($qty < 1) $qty = 1;

        // Fetch GST rate from products table (defaults to 12%)
        $gstRate = 12.0;
        if ($pid !== null) {
            $gs = $db->prepare("SELECT gst_rate FROM products WHERE id = ?");
            $gs->bind_param('i', $pid);
            $gs->execute();
            $gr = $gs->get_result()->fetch_assoc();
            $gs->close();
            if ($gr !== null && isset($gr['gst_rate'])) {
                $gstRate = (float)$gr['gst_rate'];
            }
        }

        $base  = round($price * $qty, 2);
        $gst   = round($base * $gstRate / 100.0, 2);
        $iTotal = $base + $gst;

        $subtotal += $base;
        $gstTotal += $gst;

        $rows[] = array(
            'pid'     => $pid,
            'pname'   => $pname,
            'price'   => $price,
            'gstRate' => $gstRate,
            'gst'     => $gst,
            'qty'     => $qty,
            'sz'      => $sz,
            'clr'     => $clr,
            'iTotal'  => $iTotal,
        );
    }

    $subtotal = round($subtotal, 2);
    $gstTotal = round($gstTotal, 2);

    // Shipping cost
    $shipCost = ($subtotal + $gstTotal) >= 5000.0 ? 0 : 99;

    // Promo codes
    $discount   = 0.0;
    $promoCodes = array(
        'WELCOME10' => array('type' => 'percentage', 'value' => 10),
        'FREESHIP'  => array('type' => 'shipping',   'value' => 0),
        'SAVE20'    => array('type' => 'percentage', 'value' => 20),
        'SUMMER25'  => array('type' => 'percentage', 'value' => 25),
    );
    if (!empty($promo) && isset($promoCodes[$promo])) {
        $pc = $promoCodes[$promo];
        if ($pc['type'] === 'percentage') {
            $discount = round($subtotal * $pc['value'] / 100.0, 2);
        } elseif ($pc['type'] === 'shipping') {
            $shipCost = 0;
        }
    }

    $grandTotal  = round($subtotal + $gstTotal - $discount + $shipCost, 2);
    $orderNum    = 'VLR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    $userId      = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $orderOtp    = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $invoiceToken = bin2hex(random_bytes(16));
    $smsSentAt   = date('Y-m-d H:i:s');

    // ── INSERT ORDER ──────────────────────────────────────────────────
    // 20 columns → type string 'sisssssssssdddsddsss' (20 chars)
    $sql = "INSERT INTO orders
            (order_number, user_id, customer_name, customer_email, customer_phone,
             shipping_address, shipping_city, shipping_state, shipping_pincode, shipping_country,
             payment_method, subtotal, gst_amount, discount, promo_code, shipping_cost, total, sms_otp, sms_otp_sent_at, invoice_token, status)
            VALUES (?, ?, ?, ?, ?,  ?, ?, ?, ?, ?,  ?, ?, ?, ?, ?,  ?, ?, ?, ?, ?, 'pending')";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $errMsg = $db->error;
        $db->close();
        sendJSON(['success' => false, 'message' => 'Order prepare failed: ' . $errMsg], 500);
    }

    $stmt->bind_param(
        'sisssssssssdddsddsss',
        $orderNum, $userId, $custName, $email, $phone,
        $address, $city, $state, $pincode, $country,
        $payment, $subtotal, $gstTotal, $discount, $promo, $shipCost, $grandTotal,
        $orderOtp, $smsSentAt, $invoiceToken
    );

    if (!$stmt->execute()) {
        $errMsg = $stmt->error;
        $stmt->close();
        $db->close();
        sendJSON(['success' => false, 'message' => 'Order insert failed: ' . $errMsg], 500);
    }

    $orderId = $stmt->insert_id;
    $stmt->close();

    // ── INSERT ORDER ITEMS ────────────────────────────────────────────
    // 10 columns → type string 'iisdddissd' (10 chars, verified)
    $itemSql = "INSERT INTO order_items
                (order_id, product_id, product_name, price, gst_rate, gst_amount,
                 quantity, size, color, item_total)
                VALUES (?, ?, ?, ?, ?, ?,  ?, ?, ?, ?)";

    foreach ($rows as $r) {
        $itemStmt = $db->prepare($itemSql);
        if (!$itemStmt) continue;

        $pid2   = $r['pid'];
        $pname2 = $r['pname'];
        $price2 = $r['price'];
        $grate2 = $r['gstRate'];
        $gamt2  = $r['gst'];
        $qty2   = $r['qty'];
        $sz2    = $r['sz'];
        $clr2   = $r['clr'];
        $tot2   = $r['iTotal'];

        $itemStmt->bind_param(
            'iisdddissd',
            $orderId, $pid2, $pname2, $price2, $grate2, $gamt2,
            $qty2, $sz2, $clr2, $tot2
        );
        $itemStmt->execute();
        $itemStmt->close();

        // Reduce stock
        if ($pid2 !== null) {
            $stockStmt = $db->prepare("UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?");
            $stockStmt->bind_param('ii', $qty2, $pid2);
            $stockStmt->execute();
            $stockStmt->close();
        }
    }

    $emailResult = sendOrderConfirmationEmail(
        $email,
        $custName,
        $orderNum,
        $subtotal,
        $gstTotal,
        $discount,
        $shipCost,
        $grandTotal,
        $rows
    );

    $smsResult = ['success' => false, 'message' => 'No phone number provided', 'raw' => null, 'message_text' => ''];
    $orderSmsStatus = 'skipped';
    $smsError = null;
    $smsRetryCount = 0;
    $smsLastAttemptAt = null;

    if (!empty($phone)) {
        $message = getOrderConfirmationSMSMessage($orderNum, $grandTotal, $rows);
        $smsResult = sendSMSWithRetry($db, $orderId, $orderNum, $phone, $message, 3);
        $smsRetryCount = $smsResult['attempts'] ?? 0;
        $smsLastAttemptAt = $smsResult['lastAttemptAt'] ?? date('Y-m-d H:i:s');
        if ($smsResult['success']) {
            $orderSmsStatus = 'sent';
        } else {
            $orderSmsStatus = 'failed';
            $smsError = $smsResult['errorMessage'] ?? $smsResult['message'] ?? 'SMS failed';
        }
    }

    updateOrderSmsStatus($db, $orderId, $orderSmsStatus, $smsRetryCount, $smsLastAttemptAt, $smsError);
    $db->close();

    $response = array(
        'success'     => true,
        'message'     => 'Order placed successfully!',
        'orderNumber' => $orderNum,
        'orderId'     => $orderId,
        'subtotal'    => $subtotal,
        'gstAmount'   => $gstTotal,
        'discount'    => $discount,
        'shipping'    => $shipCost,
        'total'       => $grandTotal,
        'emailSent'   => $emailResult['success'],
        'smsSent'     => $smsResult['success'],
        'smsStatus'   => $orderSmsStatus,
        'smsRetryCount' => $smsRetryCount,
    );

    if (!$emailResult['success']) {
        $response['emailWarning'] = $emailResult['message'];
    }
    if (!$smsResult['success'] && !empty($phone)) {
        $response['smsWarning'] = $smsResult['message'];
    }

    sendJSON($response);
}

function getOrderConfirmationSMSMessage($orderNumber, $grandTotal, $items) {
    $details = [];
    foreach ($items as $item) {
        $name = trim(isset($item['pname']) ? $item['pname'] : (isset($item['name']) ? $item['name'] : 'Product'));
        $qty = isset($item['qty']) ? (int)$item['qty'] : (isset($item['quantity']) ? (int)$item['quantity'] : 1);
        $details[] = $name . ' x' . $qty;
    }

    $itemsText = implode(', ', $details);
    if (strlen($itemsText) > 240) {
        $itemsText = substr($itemsText, 0, 240) . '...';
    }

    return "VELORA Order Confirmed! Order: {$orderNumber}. Total: Rs. {$grandTotal}. Items: {$itemsText}";
}

function sendOrderConfirmationSMS($phone, $orderNumber, $grandTotal, $items) {
    $message = getOrderConfirmationSMSMessage($orderNumber, $grandTotal, $items);
    $smsResult = sendSMS($phone, $message);
    if ($smsResult['success']) {
        return ['success' => true, 'message' => 'SMS sent successfully.', 'raw' => $smsResult['raw'] ?? null, 'message_text' => $message];
    }
    $errorMessage = $smsResult['message'] ?? 'Unknown error';
    if (isset($smsResult['raw'])) {
        $errorMessage .= ' | raw: ' . substr(is_string($smsResult['raw']) ? $smsResult['raw'] : json_encode($smsResult['raw']), 0, 200);
    }
    return ['success' => false, 'message' => 'SMS failed: ' . $errorMessage, 'raw' => $smsResult['raw'] ?? null, 'message_text' => $message];
}

function sendSMSWithRetry($db, $orderId, $orderNumber, $phone, $message, $maxAttempts = 3) {
    $attempts = 0;
    $lastAttemptAt = null;
    $lastResult = [
        'success' => false,
        'message' => 'SMS not attempted',
        'raw' => null,
        'retryable' => false,
    ];

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $attempts = $attempt;
        $smsResult = sendSMS($phone, $message);
        $record = recordSMSAttempt($db, $orderId, $orderNumber, $phone, $message, $smsResult);
        $lastAttemptAt = $record['attemptAt'] ?? date('Y-m-d H:i:s');
        $lastResult = $smsResult;

        if ($smsResult['success']) {
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'attempts' => $attempts,
                'lastAttemptAt' => $lastAttemptAt,
                'raw' => $smsResult['raw'] ?? null,
                'message_text' => $message,
                'errorMessage' => null,
            ];
        }

        if (isset($smsResult['retryable']) && $smsResult['retryable'] === false) {
            break;
        }
    }

    $errorMessage = $lastResult['message'] ?? 'SMS failure';
    if (!empty($lastResult['http_status']) && stripos($errorMessage, 'http ') !== 0) {
        $errorMessage = 'HTTP ' . $lastResult['http_status'] . ': ' . $errorMessage;
    }

    return [
        'success' => false,
        'message' => 'SMS failed after ' . $attempts . ' attempts: ' . $errorMessage,
        'attempts' => $attempts,
        'lastAttemptAt' => $lastAttemptAt,
        'errorMessage' => $errorMessage,
        'raw' => $lastResult['raw'] ?? null,
        'message_text' => $message,
    ];
}

function recordSMSAttempt($db, $orderId, $orderNumber, $phone, $message, $smsResult) {
    $attemptAt = date('Y-m-d H:i:s');
    $status = $smsResult['success'] ? 'sent' : 'failed';
    $errorText = $smsResult['success'] ? null : ($smsResult['message'] ?? null);
    $responseText = isset($smsResult['raw']) ? json_encode($smsResult['raw']) : null;
    $requestPayload = isset($smsResult['request_payload']) ? $smsResult['request_payload'] : null;

    $stmt = $db->prepare(
        "INSERT INTO sms_logs (order_id, order_number, phone, attempt_at, success, status, error_text, response_text, request_payload, message_text)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        $successFlag = $smsResult['success'] ? 1 : 0;
        $stmt->bind_param(
            'isssisssss',
            $orderId,
            $orderNumber,
            $phone,
            $attemptAt,
            $successFlag,
            $status,
            $errorText,
            $responseText,
            $requestPayload,
            $message
        );
        $stmt->execute();
        $stmt->close();
    }

    return array(
        'status' => $status,
        'errorText' => $errorText,
        'responseText' => $responseText,
        'requestPayload' => $requestPayload,
        'attemptAt' => $attemptAt,
    );
}

function updateOrderSmsStatus($db, $orderId, $status, $retryCount, $lastAttemptAt, $errorMessage) {
    $stmt = $db->prepare(
        "UPDATE orders SET sms_status = ?, sms_retry_count = ?, sms_last_attempt_at = ?, sms_error = ? WHERE id = ?"
    );
    if ($stmt) {
        $stmt->bind_param('sisss', $status, $retryCount, $lastAttemptAt, $errorMessage, $orderId);
        $stmt->execute();
        $stmt->close();
    }
}

function retrySMS() {
    requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'message' => 'POST required.'], 405);
    }

    $body = getRequestBody();
    $orderNumber = trim(isset($body['orderNumber']) ? $body['orderNumber'] : '');
    if (empty($orderNumber)) {
        sendJSON(['success' => false, 'message' => 'Order number is required.'], 400);
    }

    $db = getDB();
    migrate($db);

    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->bind_param('s', $orderNumber);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $db->close();
        sendJSON(['success' => false, 'message' => 'Order not found.'], 404);
    }

    if (empty($order['customer_phone']) || !preg_match('/^[6-9][0-9]{9}$/', $order['customer_phone'])) {
        $db->close();
        sendJSON(['success' => false, 'message' => 'Valid customer phone number is required.'], 400);
    }

    $invoiceToken = $order['invoice_token'];
    if (empty($invoiceToken)) {
        $invoiceToken = bin2hex(random_bytes(16));
        $updateTokenStmt = $db->prepare("UPDATE orders SET invoice_token = ? WHERE id = ?");
        if ($updateTokenStmt) {
            $updateTokenStmt->bind_param('si', $invoiceToken, $order['id']);
            $updateTokenStmt->execute();
            $updateTokenStmt->close();
        }
    }

    $orderItems = array();
    $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    if ($itemsStmt) {
        $itemsStmt->bind_param('i', $order['id']);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        while ($itemRow = $itemsResult->fetch_assoc()) {
            $orderItems[] = $itemRow;
        }
        $itemsStmt->close();
    }

    $maxAttempts = 3;
    $currentRetryCount = (int)$order['sms_retry_count'];
    $remainingAttempts = max(0, $maxAttempts - $currentRetryCount);

    if ($remainingAttempts === 0) {
        $db->close();
        sendJSON(['success' => false, 'message' => 'Maximum SMS retry limit reached for this order.', 'smsRetryCount' => $currentRetryCount], 429);
    }

    $message = getOrderConfirmationSMSMessage($orderNumber, $order['total'], $orderItems);
    $smsResult = sendSMSWithRetry($db, $order['id'], $orderNumber, $order['customer_phone'], $message, $remainingAttempts);
    $retryCount = min($maxAttempts, $currentRetryCount + ($smsResult['attempts'] ?? 0));
    $status = $smsResult['success'] ? 'sent' : 'failed';

    updateOrderSmsStatus(
        $db,
        $order['id'],
        $status,
        $retryCount,
        $smsResult['lastAttemptAt'] ?? date('Y-m-d H:i:s'),
        $smsResult['errorMessage'] ?? $smsResult['message']
    );

    $db->close();
    sendJSON([
        'success' => $smsResult['success'],
        'message' => $smsResult['message'],
        'smsStatus' => $status,
        'smsRetryCount' => $retryCount,
    ]);
}

function sendOrderConfirmationEmail($toEmail, $toName, $orderNumber, $subtotal, $gstAmount, $discount, $shippingCost, $total, $items) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = "Your VELORA Receipt - #{$orderNumber}";

        // Generate professional invoice HTML
        $invoiceHTML = generateInvoiceHTML($toName, $orderNumber, $items, $subtotal, $gstAmount, $discount, $shippingCost, $total);

        $mail->Body = $invoiceHTML;

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Email send failed: ' . $mail->ErrorInfo];
    }
}

function generateInvoiceHTML($customerName, $orderNumber, $items, $subtotal, $gstAmount, $discount, $shippingCost, $total) {
    $currentDate = date('d M Y');
    
    $productsHTML = "";
    foreach ($items as $item) {
        $itemName = htmlspecialchars($item['pname'] ?? $item['name'] ?? 'Product');
        $quantity = (int)($item['qty'] ?? $item['quantity'] ?? 1);
        $price = number_format((float)($item['price'] ?? 0), 2);
        $gstRate = isset($item['gstRate']) ? number_format((float)$item['gstRate'], 2) : '12.00';
        $gstAmount = isset($item['gst']) ? number_format((float)$item['gst'], 2) : '0.00';
        $itemTotal = isset($item['iTotal']) ? number_format((float)$item['iTotal'], 2) : number_format($quantity * $price, 2);
        
        $size = isset($item['sz']) && !empty($item['sz']) ? "Size: {$item['sz']}" : '';
        $color = isset($item['clr']) && !empty($item['clr']) ? "Color: {$item['clr']}" : '';
        $metadata = array_filter([$size, $color]);
        $metaHTML = !empty($metadata) ? '<div style="font-size:11px;color:#888;margin-top:4px;">' . implode(' | ', $metadata) . '</div>' : '';
        
        $productsHTML .= "
            <tr style='border-bottom:1px solid #f0f0f0;'>
                <td style='padding:12px 14px;font-size:13px;'>{$itemName}{$metaHTML}</td>
                <td style='padding:12px 14px;font-size:13px;text-align:center;'>{$quantity}</td>
                <td style='padding:12px 14px;font-size:13px;text-align:right;'>₹{$price}</td>
                <td style='padding:12px 14px;font-size:13px;text-align:right;'>{$gstRate}%</td>
                <td style='padding:12px 14px;font-size:13px;text-align:right;font-weight:600;'>₹{$itemTotal}</td>
            </tr>
        ";
    }

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap');
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family:'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f9f7f5; color:#1a1a1a; font-size:14px; }
            .invoice-container { max-width:700px; margin:0 auto; background:#fff; }
            .invoice-wrap { padding:40px; border:1px solid #e0e0e0; }
            
            /* Header */
            .inv-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:40px; padding-bottom:24px; border-bottom:2px solid #1a1a1a; }
            .inv-logo { font-family:'DM Serif Display', serif; font-size:28px; letter-spacing:3px; color:#1a1a1a; font-weight:600; }
            .inv-logo-subtitle { font-family:'DM Sans', sans-serif; font-size:10px; letter-spacing:2px; color:#888; font-weight:500; margin-top:4px; }
            .inv-title-block { text-align:right; }
            .inv-title-block h1 { font-size:18px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; }
            .inv-number { font-size:16px; font-weight:600; color:#8b6f4c; }
            
            /* Meta Grid */
            .inv-meta { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:32px; padding:20px; background:#f9f7f5; border-radius:6px; }
            .inv-meta-block { }
            .inv-meta-label { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#888; font-weight:600; margin-bottom:6px; }
            .inv-meta-value { font-size:13px; font-weight:500; color:#1a1a1a; line-height:1.6; }
            
            /* Items Table */
            .inv-table { width:100%; border-collapse:collapse; margin:32px 0; }
            .inv-table th { background:#1a1a1a; color:#fff; padding:10px 14px; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:600; text-align:left; }
            .inv-table th:nth-child(2), .inv-table th:nth-child(3), .inv-table th:nth-child(4), .inv-table th:nth-child(5) { text-align:right; }
            .inv-table td { padding:12px 14px; font-size:13px; }
            .inv-table td:nth-child(2), .inv-table td:nth-child(3), .inv-table td:nth-child(4), .inv-table td:nth-child(5) { text-align:right; }
            
            /* Totals Section */
            .inv-totals-section { margin:32px 0; text-align:right; }
            .inv-totals-block { max-width:300px; margin-left:auto; }
            .inv-total-row { display:flex; justify-content:space-between; padding:8px 0; font-size:13px; color:#555; border-bottom:1px solid #eee; }
            .inv-total-row.subtotal { }
            .inv-total-row.gst { color:#666; }
            .inv-total-row.discount { color:#2e7d32; font-weight:600; }
            .inv-total-row.shipping { }
            .inv-total-row.grand { font-size:16px; font-weight:700; color:#1a1a1a; border-top:2px solid #1a1a1a; border-bottom:none; padding:12px 0; margin-top:8px; }
            
            /* Footer */
            .inv-footer { margin-top:40px; padding-top:20px; border-top:1px solid #e0e0e0; text-align:center; }
            .inv-footer-note { font-size:12px; color:#888; line-height:1.8; }
            .inv-footer-note strong { color:#1a1a1a; }
        </style>
    </head>
    <body>
        <div class='invoice-container'>
            <div class='invoice-wrap'>
                
                <!-- Header -->
                <div class='inv-header'>
                    <div>
                        <div class='inv-logo'>VELORA</div>
                        <div class='inv-logo-subtitle'>FASHION & LIFESTYLE</div>
                    </div>
                    <div class='inv-title-block'>
                        <h1>Receipt</h1>
                        <div class='inv-number'>{$orderNumber}</div>
                    </div>
                </div>
                
                <!-- Meta Information -->
                <div class='inv-meta'>
                    <div class='inv-meta-block'>
                        <div class='inv-meta-label'>Bill To</div>
                        <div class='inv-meta-value'>{$customerName}</div>
                    </div>
                    <div class='inv-meta-block'>
                        <div class='inv-meta-label'>Order Date</div>
                        <div class='inv-meta-value'>{$currentDate}</div>
                    </div>
                </div>
                
                <!-- Items Table -->
                <table class='inv-table'>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Tax %</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$productsHTML}
                    </tbody>
                </table>
                
                <!-- Totals -->
                <div class='inv-totals-section'>
                    <div class='inv-totals-block'>
                        <div class='inv-total-row subtotal'>
                            <span>Subtotal</span>
                            <span>₹" . number_format($subtotal, 2) . "</span>
                        </div>
                        <div class='inv-total-row gst'>
                            <span>GST (Incl.)</span>
                            <span>₹" . number_format($gstAmount, 2) . "</span>
                        </div>";
                        
    if ($discount > 0) {
        $html .= "
                        <div class='inv-total-row discount'>
                            <span>Discount</span>
                            <span>-₹" . number_format($discount, 2) . "</span>
                        </div>";
    }
    
    $html .= "
                        <div class='inv-total-row shipping'>
                            <span>Shipping</span>
                            <span>₹" . number_format($shippingCost, 2) . "</span>
                        </div>
                        <div class='inv-total-row grand'>
                            <span>Total Amount</span>
                            <span>₹" . number_format($total, 2) . "</span>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class='inv-footer'>
                    <div class='inv-footer-note'>
                        <strong>Thank you for your purchase!</strong><br>
                        We will notify you once your order ships.<br>
                        For order inquiries, please contact <strong>support@velora.com</strong>
                    </div>
                </div>
                
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

// ─────────────────────────────────────────────────────────────────────
// SEND TRACKING UPDATE EMAIL
// ─────────────────────────────────────────────────────────────────────
function sendTrackingEmail($toEmail, $toName, $orderNumber, $status, $trackingNote = '') {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = "Order Update: #{$orderNumber} - " . ucfirst($status);

        // Generate tracking HTML
        $trackingHTML = generateTrackingHTML($toName, $orderNumber, $status, $trackingNote);

        $mail->Body = $trackingHTML;

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Email send failed: ' . $mail->ErrorInfo];
    }
}

function generateTrackingHTML($customerName, $orderNumber, $status, $trackingNote = '') {
    // Status messages and styles
    $statusMessages = array(
        'pending' => array(
            'title' => 'Order Confirmed',
            'message' => 'Your order has been received and is being processed.',
            'color' => '#FFA500',
            'bgColor' => '#FFF3E0',
            'icon' => '⏳'
        ),
        'processing' => array(
            'title' => 'Processing Your Order',
            'message' => 'We are carefully packing and preparing your items for shipment.',
            'color' => '#2196F3',
            'bgColor' => '#E3F2FD',
            'icon' => '📦'
        ),
        'shipped' => array(
            'title' => 'Order Shipped',
            'message' => 'Your order is on its way to you! You can track your package below.',
            'color' => '#4CAF50',
            'bgColor' => '#E8F5E9',
            'icon' => '🚚'
        ),
        'delivered' => array(
            'title' => 'Order Delivered',
            'message' => 'Your package has been delivered successfully. We hope you enjoy your purchase!',
            'color' => '#28A745',
            'bgColor' => '#D4EDDA',
            'icon' => '✓'
        ),
        'cancelled' => array(
            'title' => 'Order Cancelled',
            'message' => 'Your order has been cancelled. Please contact our support team if you have any questions.',
            'color' => '#E53935',
            'bgColor' => '#FFEBEE',
            'icon' => '✕'
        )
    );

    $statusInfo = isset($statusMessages[$status]) ? $statusMessages[$status] : $statusMessages['pending'];
    
    $currentDate = date('d M Y, H:i A');
    
    $trackingNoteHTML = '';
    if (!empty($trackingNote)) {
        $trackingNoteHTML = "
            <div style='margin-top:20px; padding:16px; background:#f5f5f5; border-left:4px solid {$statusInfo['color']}; border-radius:4px;'>
                <div style='font-weight:600; color:#333; margin-bottom:8px;'>📍 Tracking Update:</div>
                <div style='color:#666; font-size:14px; line-height:1.6;'>" . nl2br(htmlspecialchars($trackingNote)) . "</div>
            </div>
        ";
    }

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap');
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family:'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f9f7f5; color:#1a1a1a; font-size:14px; }
            .tracking-container { max-width:600px; margin:0 auto; background:#fff; }
            .tracking-wrap { padding:40px 30px; }
            
            /* Header */
            .track-header { text-align:center; margin-bottom:40px; padding-bottom:24px; border-bottom:2px solid #e0e0e0; }
            .track-logo { font-family:'DM Serif Display', serif; font-size:28px; letter-spacing:3px; color:#1a1a1a; font-weight:600; }
            .track-order-num { font-size:12px; color:#888; letter-spacing:1px; text-transform:uppercase; margin-top:12px; }
            
            /* Status Badge */
            .status-badge { display:inline-block; margin-top:20px; padding:12px 28px; background:{$statusInfo['bgColor']}; border:1px solid {$statusInfo['color']}; border-radius:20px; }
            .status-icon { font-size:24px; margin-right:8px; vertical-align:middle; }
            .status-text { font-size:16px; font-weight:600; color:{$statusInfo['color']}; vertical-align:middle; }
            
            /* Content */
            .track-content { margin:32px 0; text-align:center; }
            .track-title { font-size:22px; font-weight:600; color:#1a1a1a; margin-bottom:12px; }
            .track-message { font-size:14px; color:#666; line-height:1.8; margin-bottom:24px; }
            
            /* Details Box */
            .track-details { background:#f9f7f5; padding:20px; border-radius:8px; margin:24px 0; text-align:left; }
            .track-detail-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #eee; }
            .track-detail-row:last-child { border-bottom:none; }
            .track-detail-label { font-weight:600; color:#666; }
            .track-detail-value { color:#1a1a1a; font-weight:500; }
            
            /* Footer */
            .track-footer { margin-top:40px; padding-top:24px; border-top:1px solid #e0e0e0; text-align:center; }
            .track-footer-text { font-size:12px; color:#888; line-height:1.8; }
            .track-footer-text a { color:{$statusInfo['color']}; text-decoration:none; font-weight:600; }
            .track-footer-text strong { color:#1a1a1a; }
        </style>
    </head>
    <body>
        <div class='tracking-container'>
            <div class='tracking-wrap'>
                
                <!-- Header -->
                <div class='track-header'>
                    <div class='track-logo'>VELORA</div>
                    <div class='track-order-num'>Order #{$orderNumber}</div>
                </div>
                
                <!-- Status Badge -->
                <div style='text-align:center;'>
                    <div class='status-badge'>
                        <span class='status-icon'>{$statusInfo['icon']}</span>
                        <span class='status-text'>" . strtoupper($statusInfo['title']) . "</span>
                    </div>
                </div>
                
                <!-- Content -->
                <div class='track-content'>
                    <div class='track-title'>{$statusInfo['title']}</div>
                    <div class='track-message'>{$statusInfo['message']}</div>
                </div>
                
                <!-- Details -->
                <div class='track-details'>
                    <div class='track-detail-row'>
                        <span class='track-detail-label'>Status:</span>
                        <span class='track-detail-value'>" . ucfirst($status) . "</span>
                    </div>
                    <div class='track-detail-row'>
                        <span class='track-detail-label'>Last Updated:</span>
                        <span class='track-detail-value'>{$currentDate}</span>
                    </div>
                </div>
                
                {$trackingNoteHTML}
                
                <!-- Call to Action -->
                <div style='text-align:center; margin:32px 0;'>
                    <a href='" . RESET_BASE_URL . "/order-tracking.php?order={$orderNumber}' style='display:inline-block; padding:12px 32px; background:#1a1a1a; color:#fff; text-decoration:none; border-radius:4px; font-weight:600; font-size:14px;'>
                        Track Your Order
                    </a>
                </div>
                
                <!-- Footer -->
                <div class='track-footer'>
                    <div class='track-footer-text'>
                        <strong>Need Help?</strong><br>
                        Contact us at <strong>support@velora.com</strong> or call us for assistance.
                    </div>
                </div>
                
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

// ─────────────────────────────────────────────────────────────────────
// LIST ORDERS
// ─────────────────────────────────────────────────────────────────────
function listOrders() {
    if (!isset($_SESSION['user_id'])) {
        sendJSON(['success' => false, 'message' => 'Please log in to view orders.'], 401);
    }

    $db = getDB();
    migrate($db);

    $isAdmin = !empty($_SESSION['is_admin']);
    $filter  = isset($_GET['status']) ? $_GET['status'] : 'all';

    if ($isAdmin) {
        $sql    = "SELECT o.*, COUNT(oi.id) AS item_count
                   FROM orders o
                   LEFT JOIN order_items oi ON o.id = oi.order_id";
        $where  = array();
        $params = array();
        $types  = '';
        if ($filter !== 'all') {
            $where[]  = 'o.status = ?';
            $params[] = $filter;
            $types   .= 's';
        }
        if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' GROUP BY o.id ORDER BY o.created_at DESC';
        $stmt = $db->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
    } else {
        $uid  = (int)$_SESSION['user_id'];
        $stmt = $db->prepare(
            "SELECT o.*, COUNT(oi.id) AS item_count
             FROM orders o
             LEFT JOIN order_items oi ON o.id = oi.order_id
             WHERE o.user_id = ?
             GROUP BY o.id
             ORDER BY o.created_at DESC"
        );
        $stmt->bind_param('i', $uid);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $orders = array();

    while ($row = $result->fetch_assoc()) {
        $orders[] = array(
            'id'           => (int)$row['id'],
            'orderNumber'  => $row['order_number'],
            'customer'     => array(
                'name'  => $row['customer_name'],
                'email' => $row['customer_email'],
                'phone' => isset($row['customer_phone']) ? $row['customer_phone'] : '',
            ),
            'shipping'     => array(
                'address' => $row['shipping_address'],
                'city'    => $row['shipping_city'],
                'state'   => $row['shipping_state'],
                'pincode' => isset($row['shipping_pincode']) ? $row['shipping_pincode'] : '',
                'country' => $row['shipping_country'],
            ),
            'subtotal'     => (float)$row['subtotal'],
            'gstAmount'    => (float)(isset($row['gst_amount'])   ? $row['gst_amount']   : 0),
            'discount'     => (float)$row['discount'],
            'shippingCost' => (float)$row['shipping_cost'],
            'total'        => (float)$row['total'],
            'status'       => $row['status'],
            'smsStatus'    => isset($row['sms_status']) ? $row['sms_status'] : 'pending',
            'smsRetryCount'=> (int)(isset($row['sms_retry_count']) ? $row['sms_retry_count'] : 0),
            'smsLastAttempt' => isset($row['sms_last_attempt_at']) ? $row['sms_last_attempt_at'] : '',
            'trackingNote' => isset($row['tracking_note']) ? $row['tracking_note'] : '',
            'itemCount'    => (int)$row['item_count'],
            'date'         => date('d M Y', strtotime($row['created_at'])),
            'createdAt'    => $row['created_at'],
        );
    }

    $stmt->close();
    $db->close();
    sendJSON(array('success' => true, 'orders' => $orders));
}

// ─────────────────────────────────────────────────────────────────────
// GET SINGLE ORDER
// ─────────────────────────────────────────────────────────────────────
function getOrder() {
    $db  = getDB();
    migrate($db);

    $orderNumber = trim(isset($_GET['order_number']) ? $_GET['order_number'] : '');
    if (empty($orderNumber)) {
        sendJSON(['success' => false, 'message' => 'Order number is required.'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->bind_param('s', $orderNumber);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order === null) {
        $db->close();
        sendJSON(['success' => false, 'message' => 'Order not found.'], 404);
    }

    // Access: admin can see all, logged-in owner can see own, guests can view by number
    $isAdmin = !empty($_SESSION['is_admin']);
    $isOwner = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$order['user_id'];
    if (!$isAdmin && !$isOwner && isset($_SESSION['user_id']) && $order['user_id'] !== null) {
        $db->close();
        sendJSON(['success' => false, 'message' => 'Access denied.'], 403);
    }

    $stmt2 = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt2->bind_param('i', $order['id']);
    $stmt2->execute();
    $itemsResult = $stmt2->get_result();
    $items       = array();

    while ($row = $itemsResult->fetch_assoc()) {
        $items[] = array(
            'name'      => $row['product_name'],
            'price'     => (float)$row['price'],
            'gstRate'   => (float)(isset($row['gst_rate'])   ? $row['gst_rate']   : 0),
            'gstAmount' => (float)(isset($row['gst_amount']) ? $row['gst_amount'] : 0),
            'quantity'  => (int)$row['quantity'],
            'size'      => isset($row['size'])  ? $row['size']  : '',
            'color'     => isset($row['color']) ? $row['color'] : '',
            'total'     => (float)$row['item_total'],
        );
    }
    $stmt2->close();
    $db->close();

    // Tracking timeline
    $allSteps   = array('pending', 'processing', 'shipped', 'delivered');
    $currentIdx = array_search($order['status'], $allSteps);
    if ($currentIdx === false) $currentIdx = 0;

    $trackingSteps = array(
        array('label' => 'Order Placed', 'done' => $currentIdx >= 0, 'icon' => '📋', 'desc' => 'Your order has been received'),
        array('label' => 'Processing',   'done' => $currentIdx >= 1, 'icon' => '⚙️',  'desc' => 'We are preparing your items'),
        array('label' => 'Shipped',      'done' => $currentIdx >= 2, 'icon' => '🚚', 'desc' => 'Your order is on the way'),
        array('label' => 'Delivered',    'done' => $currentIdx >= 3, 'icon' => '✅', 'desc' => 'Order delivered successfully'),
    );

    $pincode = isset($order['shipping_pincode']) ? $order['shipping_pincode']
             : (isset($order['shipping_zip']) ? $order['shipping_zip'] : '');

    sendJSON(array(
        'success' => true,
        'order'   => array(
            'orderNumber'     => $order['order_number'],
            'date'            => date('d M Y', strtotime($order['created_at'])),
            'status'          => $order['status'],
            'smsStatus'       => isset($order['sms_status']) ? $order['sms_status'] : 'pending',
            'smsRetryCount'   => (int)(isset($order['sms_retry_count']) ? $order['sms_retry_count'] : 0),
            'smsLastAttempt'  => isset($order['sms_last_attempt_at']) ? $order['sms_last_attempt_at'] : '',
            'smsError'        => isset($order['sms_error']) ? $order['sms_error'] : '',
            'trackingNote'    => isset($order['tracking_note'])        ? $order['tracking_note']        : '',
            'trackingUpdated' => !empty($order['tracking_updated_at']) ? date('d M Y, g:i A', strtotime($order['tracking_updated_at'])) : '',
            'items'           => $items,
            'subtotal'        => (float)$order['subtotal'],
            'gstAmount'       => (float)(isset($order['gst_amount'])   ? $order['gst_amount']   : 0),
            'discount'        => (float)$order['discount'],
            'shipping'        => (float)$order['shipping_cost'],
            'total'           => (float)$order['total'],
            'shippingAddress' => array(
                'address' => $order['shipping_address'],
                'city'    => $order['shipping_city'],
                'state'   => $order['shipping_state'],
                'pincode' => $pincode,
                'country' => $order['shipping_country'],
            ),
            'trackingSteps'   => $trackingSteps,
        ),
    ));
}

// ─────────────────────────────────────────────────────────────────────
// UPDATE ORDER STATUS  (admin only)
// ─────────────────────────────────────────────────────────────────────
function updateOrderStatus() {
    requireAdmin();
    $body   = getRequestBody();
    $num    = isset($body['orderNumber']) ? $body['orderNumber'] : '';
    $status = isset($body['status'])      ? $body['status']      : '';
    $valid  = array('pending', 'processing', 'shipped', 'delivered', 'cancelled');

    if (empty($num) || !in_array($status, $valid)) {
        sendJSON(['success' => false, 'message' => 'Valid order number and status are required.'], 400);
    }

    $db   = getDB();
    migrate($db);
    
    // Fetch order details
    $orderStmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
    $orderStmt->bind_param('s', $num);
    $orderStmt->execute();
    $orderData = $orderStmt->get_result()->fetch_assoc();
    $orderStmt->close();
    
    // Update status
    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE order_number = ?");
    $stmt->bind_param('ss', $status, $num);
    $stmt->execute();
    $stmt->close();
    
    // Send tracking email if order found
    if ($orderData) {
        sendTrackingEmail(
            $orderData['customer_email'],
            $orderData['customer_name'],
            $num,
            $status,
            isset($orderData['tracking_note']) ? $orderData['tracking_note'] : ''
        );
    }
    
    $db->close();
    sendJSON(array('success' => true, 'message' => 'Order status updated and customer notified.'));
}

// ─────────────────────────────────────────────────────────────────────
// UPDATE TRACKING  (admin only — status + note)
// ─────────────────────────────────────────────────────────────────────
function updateTracking() {
    requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'message' => 'POST required.'], 405);
    }

    $body   = getRequestBody();
    $num    = isset($body['orderNumber'])  ? $body['orderNumber']  : '';
    $status = isset($body['status'])       ? $body['status']       : '';
    $note   = trim(isset($body['trackingNote']) ? $body['trackingNote'] : '');
    $valid  = array('pending', 'processing', 'shipped', 'delivered', 'cancelled');

    if (empty($num) || !in_array($status, $valid)) {
        sendJSON(['success' => false, 'message' => 'Valid order number and status are required.'], 400);
    }

    $db  = getDB();
    migrate($db);
    $now = date('Y-m-d H:i:s');
    
    // Fetch order details
    $orderStmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
    $orderStmt->bind_param('s', $num);
    $orderStmt->execute();
    $orderData = $orderStmt->get_result()->fetch_assoc();
    $orderStmt->close();

    $stmt = $db->prepare("UPDATE orders SET status = ?, tracking_note = ?, tracking_updated_at = ? WHERE order_number = ?");
    $stmt->bind_param('ssss', $status, $note, $now, $num);
    $stmt->execute();
    $stmt->close();
    
    // Send tracking email if order found
    if ($orderData) {
        sendTrackingEmail(
            $orderData['customer_email'],
            $orderData['customer_name'],
            $num,
            $status,
            $note
        );
    }
    
    $db->close();
    sendJSON(array('success' => true, 'message' => 'Tracking updated successfully and customer notified.'));
}
?>
