<?php
// api/gst.php - GST management endpoints
require_once '../config.php';

setJSONHeaders();
startSession();

$action = $_GET['action'] ?? 'list_rates';

switch ($action) {
    case 'list_rates':      listGstRates();         break;
    case 'get_product_gst': getProductGst();        break;
    case 'update_product':  updateProductGst();     break;
    case 'bulk_update':     bulkUpdateGst();        break;
    case 'add_rate':        addGstRate();           break;
    case 'delete_rate':     deleteGstRate();        break;
    default: sendJSON(['success' => false, 'message' => 'Invalid action'], 400);
}

// ── LIST GST RATE PRESETS ─────────────────────────────────────────────
function listGstRates() {
    requireAdmin();
    $db     = getDB();
    $result = $db->query("SELECT * FROM gst_rates ORDER BY rate ASC");
    $rates  = [];
    while ($row = $result->fetch_assoc()) {
        $rates[] = ['id' => (int)$row['id'], 'label' => $row['label'], 'rate' => (float)$row['rate'], 'description' => $row['description']];
    }
    $db->close();
    sendJSON(['success' => true, 'rates' => $rates]);
}

// ── GET ALL PRODUCTS WITH THEIR GST ──────────────────────────────────
function getProductGst() {
    requireAdmin();
    $db     = getDB();
    $result = $db->query(
        "SELECT id, name, price, gst_rate, category FROM products WHERE is_active = 1 ORDER BY category, name"
    );
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $price    = (float)$row['price'];
        $gstRate  = (float)$row['gst_rate'];
        $gstAmt   = round($price * ($gstRate / 100), 2);
        $products[] = [
            'id'            => (int)$row['id'],
            'name'          => $row['name'],
            'category'      => $row['category'],
            'basePrice'     => $price,
            'gstRate'       => $gstRate,
            'gstAmount'     => $gstAmt,
            'priceWithGst'  => round($price + $gstAmt, 2)
        ];
    }
    $db->close();
    sendJSON(['success' => true, 'products' => $products]);
}

// ── UPDATE GST FOR ONE PRODUCT ────────────────────────────────────────
function updateProductGst() {
    requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        sendJSON(['success' => false, 'message' => 'POST required'], 405);

    $body    = getRequestBody();
    $id      = (int)($body['id']      ?? 0);
    $gstRate = (float)($body['gstRate'] ?? 0);

    if (!$id) sendJSON(['success' => false, 'message' => 'Product ID required'], 400);
    if ($gstRate < 0 || $gstRate > 100)
        sendJSON(['success' => false, 'message' => 'GST rate must be between 0 and 100'], 400);

    $db   = getDB();
    $stmt = $db->prepare("UPDATE products SET gst_rate = ? WHERE id = ?");
    $stmt->bind_param('di', $gstRate, $id);
    $stmt->execute();
    $stmt->close(); $db->close();

    sendJSON(['success' => true, 'message' => 'GST rate updated successfully']);
}

// ── BULK UPDATE GST BY CATEGORY ───────────────────────────────────────
function bulkUpdateGst() {
    requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        sendJSON(['success' => false, 'message' => 'POST required'], 405);

    $body     = getRequestBody();
    $category = $body['category'] ?? '';
    $gstRate  = (float)($body['gstRate'] ?? 0);

    if ($gstRate < 0 || $gstRate > 100)
        sendJSON(['success' => false, 'message' => 'GST rate must be between 0 and 100'], 400);

    $db = getDB();
    if ($category === 'all') {
        $db->query("UPDATE products SET gst_rate = $gstRate WHERE is_active = 1");
        $affected = $db->affected_rows;
    } else {
        $stmt = $db->prepare("UPDATE products SET gst_rate = ? WHERE category = ? AND is_active = 1");
        $stmt->bind_param('ds', $gstRate, $category);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
    }
    $db->close();

    sendJSON(['success' => true, 'message' => "$affected products updated to $gstRate% GST"]);
}

// ── ADD NEW GST RATE PRESET ───────────────────────────────────────────
function addGstRate() {
    requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        sendJSON(['success' => false, 'message' => 'POST required'], 405);

    $body        = getRequestBody();
    $label       = trim($body['label']       ?? '');
    $rate        = (float)($body['rate']       ?? 0);
    $description = trim($body['description'] ?? '');

    if (!$label || $rate < 0 || $rate > 100)
        sendJSON(['success' => false, 'message' => 'Label and valid rate required'], 400);

    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO gst_rates (label, rate, description) VALUES (?, ?, ?)");
    $stmt->bind_param('sds', $label, $rate, $description);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close(); $db->close();

    sendJSON(['success' => true, 'message' => 'GST rate preset added', 'id' => $newId]);
}

// ── DELETE GST RATE PRESET ────────────────────────────────────────────
function deleteGstRate() {
    requireAdmin();
    $body = getRequestBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) sendJSON(['success' => false, 'message' => 'Rate ID required'], 400);

    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM gst_rates WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close(); $db->close();

    sendJSON(['success' => true, 'message' => 'GST rate preset deleted']);
}
?>
