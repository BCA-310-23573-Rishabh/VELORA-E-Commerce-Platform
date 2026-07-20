<?php
// api/invoice.php — generates a printable/downloadable HTML invoice
require_once '../config.php';
ini_set('display_errors', 0);
startSession();

$action = $_GET['action'] ?? 'view';
$orderNumber = trim($_GET['order'] ?? '');

if (!$orderNumber) {
    die('Order number required. Usage: api/invoice.php?order=VLR-XXXXXXXX');
}

// Fetch order
$db   = getDB();
$stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
$stmt->bind_param('s', $orderNumber);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { $db->close(); die('Order not found.'); }

// Access check: owner, admin, or valid invoice token
$isAdmin = !empty($_SESSION['is_admin']);
$isOwner = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$order['user_id'];
$token = trim($_GET['token'] ?? '');
$validToken = !empty($token) && !empty($order['invoice_token']) && hash_equals($order['invoice_token'], $token);
if (!$isAdmin && !$isOwner && !$validToken) {
    die('Access denied. Please log in or use the secure invoice link sent to your phone.');
}

// Fetch items
$stmt2 = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt2->bind_param('i', $order['id']);
$stmt2->execute();
$itemsResult = $stmt2->get_result();
$items = [];
while ($row = $itemsResult->fetch_assoc()) $items[] = $row;
$stmt2->close();
$db->close();

$pincode = $order['shipping_pincode'] ?? $order['shipping_zip'] ?? '';

// Build the invoice HTML into a string (we'll either output HTML or render PDF via TCPDF)
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>VELORA Invoice — <?= htmlspecialchars($orderNumber) ?></title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap');
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'DM Sans', sans-serif; background:#fff; color:#1a1a1a; font-size:14px; }
  .invoice-wrap { max-width:760px; margin:40px auto; padding:48px; border:1px solid #e0e0e0; border-radius:12px; }

  /* Header */
  .inv-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:40px; padding-bottom:32px; border-bottom:2px solid #1a1a1a; }
  .inv-logo { font-family:'DM Serif Display', serif; font-size:32px; letter-spacing:4px; color:#1a1a1a; }
  .inv-logo span { display:block; font-family:'DM Sans', sans-serif; font-size:11px; letter-spacing:2px; color:#888; font-weight:500; margin-top:4px; }
  .inv-title-block { text-align:right; }
  .inv-title-block h1 { font-size:22px; font-weight:600; text-transform:uppercase; letter-spacing:2px; }
  .inv-title-block p { color:#666; font-size:12px; margin-top:6px; }
  .inv-number { font-size:18px; font-weight:600; color:#8b6f4c; margin-top:4px; }

  /* Meta grid */
  .inv-meta { display:grid; grid-template-columns:1fr 1fr 1fr; gap:24px; margin-bottom:32px; padding:24px; background:#f9f7f5; border-radius:8px; }
  .inv-meta-label { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#888; font-weight:600; margin-bottom:4px; }
  .inv-meta-value { font-size:14px; font-weight:500; color:#1a1a1a; line-height:1.6; }

  /* Items table */
  .inv-table { width:100%; border-collapse:collapse; margin-bottom:24px; }
  .inv-table th { background:#1a1a1a; color:#fff; padding:10px 14px; font-size:11px; text-transform:uppercase; letter-spacing:1px; font-weight:600; text-align:left; }
  .inv-table th:last-child { text-align:right; }
  .inv-table td { padding:12px 14px; border-bottom:1px solid #f0f0f0; font-size:13px; }
  .inv-table td:last-child { text-align:right; font-weight:600; }
  .inv-table tr:nth-child(even) td { background:#fafafa; }
  .item-meta { font-size:11px; color:#888; margin-top:3px; }

  /* Totals */
  .inv-totals { margin-left:auto; width:260px; }
  .inv-total-row { display:flex; justify-content:space-between; padding:6px 0; font-size:13px; color:#555; }
  .inv-total-row.grand { font-size:16px; font-weight:700; color:#1a1a1a; border-top:2px solid #1a1a1a; padding-top:12px; margin-top:6px; }
  .inv-total-row.discount { color:#2e7d32; }
  .inv-total-row.gst { color:#666; }

  /* Footer */
  .inv-footer { margin-top:48px; padding-top:24px; border-top:1px solid #e0e0e0; display:flex; justify-content:space-between; align-items:flex-end; }
  .inv-footer-note { font-size:12px; color:#888; line-height:1.8; }
  .inv-status-badge { padding:6px 16px; border-radius:20px; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
  .status-delivered  { background:#d1e7dd; color:#0f5132; }
  .status-shipped    { background:#cce5ff; color:#004085; }
  .status-processing { background:#fff3cd; color:#856404; }
  .status-pending    { background:#f0f0f0; color:#333; }

  /* Print */
  @media print {
    body { background:#fff; }
    .invoice-wrap { border:none; margin:0; padding:24px; border-radius:0; }
    .no-print { display:none; }
  }

  /* Print button */
  .print-btn { display:block; margin:0 auto 32px; padding:12px 32px; background:#1a1a1a; color:#fff; border:none; border-radius:40px; font-size:14px; font-weight:500; cursor:pointer; letter-spacing:1px; text-transform:uppercase; font-family:'DM Sans',sans-serif; transition:background 0.2s; }
  .print-btn:hover { background:#8b6f4c; }
  .download-btn { display:inline-block; margin:0 auto 32px 12px; padding:12px 24px; background:transparent; color:#1a1a1a; border:1px solid #1a1a1a; border-radius:40px; font-size:13px; font-weight:500; cursor:pointer; letter-spacing:1px; text-transform:uppercase; font-family:'DM Sans',sans-serif; transition:all 0.2s; text-decoration:none; }
  .download-btn:hover { background:#f0f0f0; }
  .btn-row { text-align:center; }
</style>
</head>
<body>

<div class="btn-row no-print" style="padding:24px 0;">
  <button class="print-btn" onclick="window.print()">🖨️ Print Invoice</button>
  <a class="download-btn" href="invoice.php?order=<?= urlencode($orderNumber) ?>&action=download<?= !empty($token) ? '&token=' . urlencode($token) : '' ?>">⬇ Download PDF</a>
  <a class="download-btn" href="javascript:history.back()">← Back</a>
</div>

<div class="invoice-wrap">
  <div class="inv-header">
    <div class="inv-logo">
      VELORA
      <span>slow fashion · est. 2024</span>
    </div>
    <div class="inv-title-block">
      <h1>Invoice</h1>
      <div class="inv-number"><?= htmlspecialchars($orderNumber) ?></div>
      <p>Date: <?= date('d M Y', strtotime($order['created_at'])) ?></p>
      <span class="inv-status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
    </div>
  </div>

  <div class="inv-meta">
    <div>
      <div class="inv-meta-label">Billed To</div>
      <div class="inv-meta-value">
        <?= htmlspecialchars($order['customer_name']) ?><br>
        <?= htmlspecialchars($order['customer_email']) ?><br>
        <?= htmlspecialchars($order['customer_phone'] ?? '') ?>
      </div>
    </div>
    <div>
      <div class="inv-meta-label">Ship To</div>
      <div class="inv-meta-value">
        <?= htmlspecialchars($order['shipping_address']) ?><br>
        <?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state'] ?? '') ?><br>
        <?= htmlspecialchars($pincode) ?>, <?= htmlspecialchars($order['shipping_country']) ?>
      </div>
    </div>
    <div>
      <div class="inv-meta-label">Payment</div>
      <div class="inv-meta-value">
        <?= htmlspecialchars(strtoupper($order['payment_method'] ?? 'N/A')) ?><br>
        <?= !empty($order['promo_code']) ? 'Promo: ' . htmlspecialchars($order['promo_code']) : 'No promo code' ?>
      </div>
    </div>
  </div>

  <table class="inv-table">
    <thead>
      <tr>
        <th>Item</th>
        <th>Size / Color</th>
        <th style="text-align:right;">Qty</th>
        <th style="text-align:right;">Unit Price</th>
        <th style="text-align:right;">GST</th>
        <th style="text-align:right;">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td>
          <?= htmlspecialchars($item['product_name']) ?>
        </td>
        <td>
          <span class="item-meta">
            <?= $item['size']  ? 'Size: '  . htmlspecialchars($item['size'])  : '' ?>
            <?= $item['color'] ? ' · Color: ' . htmlspecialchars($item['color']) : '' ?>
          </span>
        </td>
        <td style="text-align:right;"><?= (int)$item['quantity'] ?></td>
        <td style="text-align:right;">₹<?= number_format((float)$item['price'], 2) ?></td>
        <td style="text-align:right; color:#666;">
          <?php $gstRate = (float)($item['gst_rate'] ?? 0); ?>
          <?= $gstRate > 0 ? $gstRate . '% = ₹' . number_format((float)($item['gst_amount'] ?? 0), 2) : '—' ?>
        </td>
        <td>₹<?= number_format((float)$item['item_total'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="inv-totals">
    <div class="inv-total-row">
      <span>Subtotal (excl. GST)</span>
      <span>₹<?= number_format((float)$order['subtotal'], 2) ?></span>
    </div>
    <?php if ((float)($order['gst_amount'] ?? 0) > 0): ?>
    <div class="inv-total-row gst">
      <span>GST</span>
      <span>₹<?= number_format((float)$order['gst_amount'], 2) ?></span>
    </div>
    <?php endif; ?>
    <?php if ((float)$order['discount'] > 0): ?>
    <div class="inv-total-row discount">
      <span>Discount<?= $order['promo_code'] ? ' (' . htmlspecialchars($order['promo_code']) . ')' : '' ?></span>
      <span>-₹<?= number_format((float)$order['discount'], 2) ?></span>
    </div>
    <?php endif; ?>
    <div class="inv-total-row">
      <span>Shipping</span>
      <span><?= (float)$order['shipping_cost'] === 0.0 ? 'FREE' : '₹' . number_format((float)$order['shipping_cost'], 2) ?></span>
    </div>
    <div class="inv-total-row grand">
      <span>Grand Total</span>
      <span>₹<?= number_format((float)$order['total'], 2) ?></span>
    </div>
  </div>

  <div class="inv-footer">
    <div class="inv-footer-note">
      <strong>VELORA</strong> · hello@velora.in<br>
      This is a computer-generated invoice and does not require a signature.<br>
      For returns &amp; support, visit your account page.
    </div>
    <div style="font-family:'DM Serif Display',serif; font-size:22px; letter-spacing:3px; color:#d0c4b5;">
      VELORA
    </div>
  </div>
</div>

</body>
</html>
<?php
$invoiceHtml = ob_get_clean();

// If download was requested and TCPDF is available, generate a PDF server-side.
// If Composer autoload exists, include it so TCPDF (installed via Composer) is available
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
  require_once $vendorAutoload;
}

if ($action === 'download' && class_exists('TCPDF')) {
  // Try to set up TCPDF and output PDF
  try {
    // Create TCPDF instance
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Velora');
    $pdf->SetAuthor('Velora');
    $pdf->SetTitle('VELORA Invoice - ' . $orderNumber);
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    // Convert relative image paths to absolute if needed
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $invoiceHtmlForPdf = preg_replace_callback('#src=["\']([^"\']+)["\']#i', function ($m) use ($baseUrl) {
      $src = $m[1];
      if (preg_match('#^https?://#i', $src)) return $m[0];
      $src = preg_replace('#^/+#', '', $src);
      return 'src="' . $baseUrl . '/' . $src . '"';
    }, $invoiceHtml);

    // TCPDF can render HTML; write and output as attachment
    $pdf->writeHTML($invoiceHtmlForPdf, true, false, true, false, '');
    $pdf->Output('VELORA-Invoice-' . $orderNumber . '.pdf', 'D');
    exit;
  } catch (Exception $e) {
    // Fall back to HTML download if TCPDF fails
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="VELORA-Invoice-' . $orderNumber . '.html"');
    echo $invoiceHtml;
    exit;
  }
}

// Fallback: serve HTML (view or download as HTML file)
header('Content-Type: text/html; charset=utf-8');
if ($action === 'download') {
    $downloadFilename = 'VELORA-Invoice-' . $orderNumber . (class_exists('TCPDF') ? '.pdf' : '.html');
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
}
echo $invoiceHtml;
exit;
?>
