<?php
// api/report.php - Admin report downloads
require_once '../config.php';

startSession();
requireAdmin();

define('REPORT_DATE', date('Y-m-d'));

typeReport();

function typeReport() {
    $type = strtolower(trim($_GET['type'] ?? ''));
    if (!$type) {
        sendError('Report type is required.', 400);
    }

    $selectedDate = trim($_GET['date'] ?? '');
    if ($selectedDate === '') {
        $selectedDate = REPORT_DATE;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || !validateDate($selectedDate)) {
        sendError('Invalid report date.', 400);
    }

    $format = strtolower(trim($_GET['format'] ?? 'csv'));
    if (!in_array($format, ['csv', 'pdf'], true)) {
        sendError('Invalid report format.', 400);
    }

    $filename = sprintf('velora-%s-report-%s.%s', $type, $selectedDate, $format);
    if ($format === 'pdf') {
        header('Content-Type: application/pdf');
    } else {
        header('Content-Type: text/csv; charset=utf-8');
    }
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        sendError('Unable to initialize report output.', 500);
    }

    $db = getDB();

    switch ($type) {
        case 'products': reportProducts($db, $output, $selectedDate, $format); break;
        case 'orders':   reportOrders($db, $output, $selectedDate, $format); break;
        case 'users':    reportUsers($db, $output, $selectedDate, $format); break;
        default:         sendError('Invalid report type. Use products, orders, or users.', 400);
    }

    $db->close();
    fclose($output);
    exit;
}

function reportProducts($db, $output, $selectedDate, $format) {
    $stmt = $db->prepare("SELECT id, name, category, subcategory, price, gst_rate, stock, color, sizes, badge, is_active, created_at FROM products WHERE DATE(created_at) = ? ORDER BY id ASC");
    $stmt->bind_param('s', $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($format === 'pdf') {
        $headers = ['Product ID', 'Name', 'Category', 'Price (₹)', 'Stock', 'Created At'];
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                $row['id'],
                $row['name'],
                $row['category'],
                $row['price'],
                $row['stock'],
                $row['created_at']
            ];
        }
        $stmt->close();
        fwrite($output, buildSimplePdf('Velora Products Report', 'Date: ' . $selectedDate, $headers, $rows));
        return;
    }

    fputcsv($output, ['Product ID', 'Name', 'Category', 'Subcategory', 'Price (₹)', 'GST Rate (%)', 'Stock', 'Color', 'Sizes', 'Badge', 'Active', 'Created At']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['category'],
            $row['subcategory'],
            $row['price'],
            $row['gst_rate'],
            $row['stock'],
            $row['color'],
            $row['sizes'],
            $row['badge'],
            $row['is_active'] ? 'Yes' : 'No',
            $row['created_at']
        ]);
    }
    $stmt->close();
}

function reportUsers($db, $output, $selectedDate, $format) {
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, phone, is_admin, created_at FROM users WHERE DATE(created_at) = ? ORDER BY id ASC");
    $stmt->bind_param('s', $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($format === 'pdf') {
        $headers = ['User ID', 'Name', 'Email', 'Role', 'Created At'];
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                $row['id'],
                trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                $row['email'],
                $row['is_admin'] ? 'Admin' : 'Customer',
                $row['created_at']
            ];
        }
        $stmt->close();
        fwrite($output, buildSimplePdf('Velora Users Report', 'Date: ' . $selectedDate, $headers, $rows));
        return;
    }

    fputcsv($output, ['User ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Is Admin', 'Created At']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            $row['is_admin'] ? 'Yes' : 'No',
            $row['created_at']
        ]);
    }
    $stmt->close();
}

function reportOrders($db, $output, $selectedDate, $format) {
    $sql = "SELECT o.order_number, o.customer_name, o.customer_email, o.customer_phone, o.status, o.payment_method, o.subtotal, o.gst_amount, o.discount, o.shipping_cost, o.total, o.created_at, o.shipping_address, o.shipping_city, o.shipping_state, o.shipping_pincode, o.shipping_country, oi.product_name, oi.quantity, oi.size, oi.color, oi.price AS item_price, oi.item_total FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id WHERE DATE(o.created_at) = ? ORDER BY o.created_at DESC, o.id ASC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($format === 'pdf') {
        $headers = ['Order #', 'Customer', 'Status', 'Total (₹)', 'Created At'];
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                $row['order_number'],
                $row['customer_name'],
                $row['status'],
                $row['total'],
                $row['created_at']
            ];
        }
        $stmt->close();
        fwrite($output, buildSimplePdf('Velora Orders Report', 'Date: ' . $selectedDate, $headers, $rows));
        return;
    }

    fputcsv($output, [
        'Order #', 'Customer Name', 'Customer Email', 'Customer Phone', 'Status', 'Payment Method',
        'Subtotal (₹)', 'GST Amount (₹)', 'Discount (₹)', 'Shipping (₹)', 'Total (₹)', 'Item Count',
        'Created At', 'Shipping Address', 'Shipping City', 'Shipping State', 'Shipping Pincode', 'Shipping Country',
        'Product Name', 'Quantity', 'Size', 'Color', 'Product Price (₹)', 'Item Total (₹)'
    ]);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['order_number'],
            $row['customer_name'],
            $row['customer_email'],
            $row['customer_phone'],
            $row['status'],
            $row['payment_method'],
            $row['subtotal'],
            $row['gst_amount'],
            $row['discount'],
            $row['shipping_cost'],
            $row['total'],
            $row['quantity'] ?? 0,
            $row['created_at'],
            $row['shipping_address'],
            $row['shipping_city'],
            $row['shipping_state'],
            $row['shipping_pincode'],
            $row['shipping_country'],
            $row['product_name'],
            $row['quantity'],
            $row['size'],
            $row['color'],
            $row['item_price'],
            $row['item_total']
        ]);
    }
    $stmt->close();
}

function buildSimplePdf($title, $subtitle, $headers, $rows) {
    $contentParts = [];
    $pageWidth = 612;
    $pageHeight = 792;
    $left = 50;
    $right = 562;
    $y = 690;

    $contentParts[] = "0.16 0.28 0.46 rg";
    $contentParts[] = "$left 725 512 45 re f";
    $contentParts[] = "0.95 0.97 0.99 rg";
    $contentParts[] = "$left 717 512 6 re f";
    $contentParts[] = "0 0 0 rg";

    $contentParts[] = "BT\n/F2 15 Tf\n" . ($left + 8) . " 748 Td\n(" . escapePdfText($title) . ") Tj\nET";
    $contentParts[] = "BT\n/F1 8.5 Tf\n" . ($left + 8) . " 732 Td\n(" . escapePdfText($subtitle) . ") Tj\nET";
    $contentParts[] = "0.55 0.65 0.78 RG\n$left 717 m\n$right 717 l\nS";

    if (empty($rows)) {
        $contentParts[] = "BT\n/F1 9 Tf\n$left $y Td\n(No records found.) Tj\nET";
    } else {
        foreach ($rows as $rowIndex => $row) {
            $recordTop = $y;
            $contentParts[] = "0.96 0.97 0.99 rg";
            $contentParts[] = "$left $recordTop 512 24 re f";
            $contentParts[] = "0.25 0.35 0.55 rg";
            $contentParts[] = "$left $recordTop 2 24 re f";
            $contentParts[] = "0 0 0 rg";
            $contentParts[] = "BT\n/F2 8 Tf\n" . ($left + 10) . " " . ($recordTop + 14) . " Td\n(" . escapePdfText('Record ' . ($rowIndex + 1)) . ") Tj\nET";
            $y = $recordTop - 24;

            foreach ($headers as $index => $header) {
                $value = isset($row[$index]) ? $row[$index] : '';
                $line = $header . ': ' . trim((string) $value);
                foreach (wrapPdfText($line, 95) as $wrappedLine) {
                    $contentParts[] = "BT\n/F1 7 Tf\n" . ($left + 12) . " $y Td\n(" . escapePdfText($wrappedLine) . ") Tj\nET";
                    $y -= 7;
                }
            }

            $y -= 12;
            if ($y < 50) {
                break;
            }
        }
    }

    $contentParts[] = "BT\n/F1 7 Tf\n$left 30 Td\n(Generated by Velora) Tj\nET";

    $contentStream = implode("\n", $contentParts);
    $contentLength = strlen($contentStream);

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageWidth $pageHeight] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj";
    $objects[] = "6 0 obj\n<< /Length $contentLength >>\nstream\n" . $contentStream . "\nendstream\nendobj";

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $currentOffset = strlen($pdf);

    foreach ($objects as $index => $object) {
        $offsets[$index + 1] = $currentOffset;
        $pdf .= $object . "\n";
        $currentOffset = strlen($pdf);
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 7\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= str_pad($offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

    return $pdf;
}

function wrapPdfText($text, $maxChars) {
    $text = (string) $text;
    if (strlen($text) <= $maxChars) {
        return [$text];
    }

    $words = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }

        if ($current === '') {
            $current = $word;
            continue;
        }

        if (strlen($current . $word) <= $maxChars) {
            $current .= $word;
        } else {
            $lines[] = trim($current);
            $current = $word;
        }
    }

    if ($current !== '') {
        $lines[] = trim($current);
    }

    return $lines;
}

function escapePdfText($text) {
    $text = (string) $text;
    if (function_exists('iconv')) {
        $text = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    } else {
        $text = utf8_decode($text);
    }
    $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    return $text;
}

function validateDate($date) {
    $dateTime = DateTime::createFromFormat('Y-m-d', $date);
    return $dateTime && $dateTime->format('Y-m-d') === $date;
}

function sendError($message, $status = 400) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
