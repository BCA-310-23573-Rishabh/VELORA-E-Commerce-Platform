<?php
// api/products.php - Products CRUD endpoints
require_once '../config.php';

setJSONHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        listProducts();
        break;
    case 'get':
        getProduct();
        break;
    case 'add':
        addProduct();
        break;
    case 'update':
        updateProduct();
        break;
    case 'delete':
        deleteProduct();
        break;
    case 'upload_image':
        uploadImage();
        break;
    case 'delete_image':
        deleteImage();
        break;
    default:
        sendJSON(['success' => false, 'message' => 'Invalid action'], 400);
}

// =============================================
// CATEGORY FILTER HELPERS
// =============================================
function addCategoryFilterClause($rawCategory, &$where, &$params, &$types) {
    $category = strtolower(trim((string)$rawCategory));
    if ($category === '' || $category === 'all') {
        return;
    }

    switch ($category) {
        case 'shirts':
            $where[] = '(p.subcategory = ? OR p.name LIKE ? OR p.name LIKE ?)';
            $params[] = 'shirts';
            $params[] = '%shirt%';
            $params[] = '%overshirt%';
            $types   .= 'sss';
            break;

        case 'tshirts':
            $where[] = '(p.subcategory = ? OR p.name LIKE ?)';
            $params[] = 'tees';
            $params[] = '%tee%';
            $types   .= 'ss';
            break;

        case 'jeans':
            $where[] = '(p.subcategory = ? OR p.name LIKE ?)';
            $params[] = 'jeans';
            $params[] = '%jean%';
            $types   .= 'ss';
            break;

        case 'trousers':
            $where[] = '(p.subcategory = ? OR p.name LIKE ? OR p.name LIKE ?)';
            $params[] = 'bottoms';
            $params[] = '%pant%';
            $params[] = '%trouser%';
            $types   .= 'sss';
            break;

        case 'cargo-pants':
            $where[] = '(p.name LIKE ? OR p.name LIKE ?)';
            $params[] = '%cargo%';
            $params[] = '%pant%';
            $types   .= 'ss';
            break;

        case 'overshirt':
            $where[] = '(p.name LIKE ? OR p.name LIKE ? OR p.subcategory = ?)';
            $params[] = '%overshirt%';
            $params[] = '%shirt%';
            $params[] = 'shirts';
            $types   .= 'sss';
            break;

        case 'plus-size':
            $where[] = '(p.sizes LIKE ? OR p.sizes LIKE ? OR p.name LIKE ? OR p.name LIKE ?)';
            $params[] = '%XXL%';
            $params[] = '%3XL%';
            $params[] = '%oversized%';
            $params[] = '%relaxed%';
            $types   .= 'ssss';
            break;

        case 'shorts':
            $where[] = '(p.subcategory = ? OR p.name LIKE ?)';
            $params[] = 'bottoms';
            $params[] = '%short%';
            $types   .= 'ss';
            break;

        case 'shoes':
            $where[] = '(p.name LIKE ? OR p.name LIKE ? OR p.name LIKE ? OR p.subcategory = ?)';
            $params[] = '%shoe%';
            $params[] = '%boot%';
            $params[] = '%sandal%';
            $params[] = 'shoes';
            $types   .= 'ssss';
            break;

        default:
            $where[] = 'p.category = ?';
            $params[] = $category;
            $types   .= 's';
            break;
    }
}

function getFallbackProducts() {
    return [[
        'id' => 202,
        'name' => 'Selvedge Straight Fit',
        'price' => 4199.00,
        'gstRate' => 12.00,
        'category' => 'denim',
        'subcategory' => 'jeans',
        'images' => ['/velora/Images/Denim/selvedge straight fit.avif'],
        'image' => 'url("/velora/Images/Denim/selvedge straight fit.avif")',
        'hoverImage' => 'url("/velora/Images/Denim/selvedge straight fit.avif")',
        'badge' => 'premium',
        'stock' => 7,
        'size' => ['30', '32', '34', '36', '38'],
        'color' => 'blue',
        'imageRaw' => '/velora/Images/Denim/selvedge straight fit.avif',
        'hoverImageRaw' => '/velora/Images/Denim/selvedge straight fit.avif',
        'gst_rate' => 12.00
    ]];
}

// =============================================
// LIST PRODUCTS (with optional filters)
// =============================================
function listProducts() {
    $db = getDB(true);
    if (!$db) {
        sendJSON(['success' => true, 'products' => getFallbackProducts(), 'count' => 1]);
    }

    $where    = ['p.is_active = 1'];
    $params   = [];
    $types    = '';

    // Category filter
    addCategoryFilterClause($_GET['category'] ?? '', $where, $params, $types);

    // Price filter
    if (isset($_GET['min_price'])) {
        $where[]  = 'p.price >= ?';
        $params[] = (float)$_GET['min_price'];
        $types   .= 'd';
    }
    if (isset($_GET['max_price'])) {
        $where[]  = 'p.price <= ?';
        $params[] = (float)$_GET['max_price'];
        $types   .= 'd';
    }

    // Color filter
    if (!empty($_GET['color'])) {
        $where[]  = 'p.color = ?';
        $params[] = $_GET['color'];
        $types   .= 's';
    }

    // Size filter (check if size is in the comma-separated sizes field)
    if (!empty($_GET['size'])) {
        $where[]  = 'FIND_IN_SET(?, p.sizes)';
        $params[] = $_GET['size'];
        $types   .= 's';
    }

    // Build ORDER BY
    $orderBy = 'p.id ASC';
    switch ($_GET['sort'] ?? 'featured') {
        case 'low-high':
        case 'price-low':  $orderBy = 'p.price ASC';  break;
        case 'high-low':
        case 'price-high': $orderBy = 'p.price DESC'; break;
        case 'newest':     $orderBy = 'p.created_at DESC'; break;
        case 'name-asc':   $orderBy = 'p.name ASC'; break;
        default:           $orderBy = 'p.id ASC';
    }

    $whereSQL = implode(' AND ', $where);
    $sql      = "SELECT * FROM products p WHERE $whereSQL ORDER BY $orderBy";

    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result   = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = formatProduct($row);
    }

    $stmt->close();
    $db->close();

    sendJSON(['success' => true, 'products' => $products, 'count' => count($products)]);
}

// =============================================
// GET SINGLE PRODUCT
// =============================================
function getProduct() {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) sendJSON(['success' => false, 'message' => 'Product ID required'], 400);

    $db   = getDB(true);
    if (!$db) {
        $fallbackProducts = getFallbackProducts();
        $fallbackProduct = null;
        foreach ($fallbackProducts as $candidate) {
            if ((int)$candidate['id'] === $id) {
                $fallbackProduct = $candidate;
                break;
            }
        }
        if ($fallbackProduct) {
            sendJSON(['success' => true, 'product' => $fallbackProduct]);
        }
        sendJSON(['success' => false, 'message' => 'Product not found'], 404);
    }

    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result  = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$product) sendJSON(['success' => false, 'message' => 'Product not found'], 404);

    sendJSON(['success' => true, 'product' => formatProduct($product)]);
}

// =============================================
// ADD PRODUCT (admin only)
// =============================================
function addProduct() {
    requireAdmin();

    $body = getRequestBody();
    $name        = trim($body['name'] ?? '');
    $price       = (float)($body['price'] ?? 0);
    $category    = $body['category'] ?? '';
    $subcategory = $body['subcategory'] ?? '';
    $gstRate    = (float)($body['gstRate'] ?? 12);
    $images      = $body['images'] ?? [];
    $hoverImages = $body['hoverImages'] ?? [];
    $badge       = $body['badge'] ?? '';
    $stock       = (int)($body['stock'] ?? 10);
    $sizes       = $body['sizes'] ?? '';
    $color       = $body['color'] ?? '';

    if (!$name || !$price || !$category) {
        sendJSON(['success' => false, 'message' => 'Name, price and category are required'], 400);
    }

    // Convert arrays to JSON
    $imagesJSON = !empty($images) ? json_encode($images) : '';
    $hoverImagesJSON = !empty($hoverImages) ? json_encode($hoverImages) : '';

    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO products (name, price, gst_rate, category, subcategory, image, hover_image, badge, stock, sizes, color)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sddssssssss', $name, $price, $gstRate, $category, $subcategory, $imagesJSON, $hoverImagesJSON, $badge, $stock, $sizes, $color);

    if (!$stmt->execute()) {
        $stmt->close();
        $db->close();
        sendJSON(['success' => false, 'message' => 'Failed to add product'], 500);
    }

    $newId = $stmt->insert_id;
    $stmt->close();
    $db->close();

    sendJSON(['success' => true, 'message' => 'Product added successfully', 'id' => $newId]);
}

// =============================================
// UPDATE PRODUCT (admin only)
// =============================================
function updateProduct() {
    requireAdmin();

    $id   = (int)($_GET['id'] ?? 0);
    if (!$id) sendJSON(['success' => false, 'message' => 'Product ID required'], 400);

    $body = getRequestBody();
    $name        = trim($body['name'] ?? '');
    $price       = (float)($body['price'] ?? 0);
    $category    = $body['category'] ?? '';
    $subcategory = $body['subcategory'] ?? '';
    $gstRate    = (float)($body['gstRate'] ?? 12);
    $images      = $body['images'] ?? [];
    $hoverImages = $body['hoverImages'] ?? [];
    $badge       = $body['badge'] ?? '';
    $stock       = (int)($body['stock'] ?? 10);
    $sizes       = $body['sizes'] ?? '';
    $color       = $body['color'] ?? '';

    // Convert arrays to JSON
    $imagesJSON = !empty($images) ? json_encode($images) : '';
    $hoverImagesJSON = !empty($hoverImages) ? json_encode($hoverImages) : '';

    $db   = getDB();
    $stmt = $db->prepare(
        "UPDATE products SET name=?, price=?, gst_rate=?, category=?, subcategory=?, image=?, hover_image=?,
         badge=?, stock=?, sizes=?, color=? WHERE id=?"
    );
    // Types: name(s), price(d), gst_rate(d), category(s), subcategory(s), image(s), hover_image(s), badge(s),
    // stock(i), sizes(s), color(s), id(i)
    $stmt->bind_param('sddsssssissi', $name, $price, $gstRate, $category, $subcategory, $imagesJSON, $hoverImagesJSON, $badge, $stock, $sizes, $color, $id);

    if (!$stmt->execute()) {
        $stmt->close();
        $db->close();
        sendJSON(['success' => false, 'message' => 'Failed to update product'], 500);
    }

    $stmt->close();
    $db->close();
    sendJSON(['success' => true, 'message' => 'Product updated successfully']);
}

// =============================================
// DELETE PRODUCT (admin only — soft delete)
// =============================================
function deleteProduct() {
    requireAdmin();

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) sendJSON(['success' => false, 'message' => 'Product ID required'], 400);

    $db   = getDB();
    $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $db->close();

    sendJSON(['success' => true, 'message' => 'Product deleted successfully']);
}

// =============================================
// UPLOAD IMAGE (admin only)
// =============================================
function uploadImage() {
    requireAdmin();

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        sendJSON(['success' => false, 'message' => 'No image file provided'], 400);
    }

    $file = $_FILES['image'];
    $allowedTypes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        'image/avif', 'image/bmp', 'image/tiff', 'image/heic', 'image/heif',
        'image/svg+xml'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedType = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!in_array($file['type'], $allowedTypes, true) && !in_array($detectedType, $allowedTypes, true)) {
        sendJSON(['success' => false, 'message' => 'Invalid image type. Supported formats: JPG, PNG, GIF, WebP, AVIF, BMP, TIFF, HEIC, HEIF, SVG'], 400);
    }

    $size = @filesize($file['tmp_name']);
    if ($size === false || $size > 5 * 1024 * 1024) {
        sendJSON(['success' => false, 'message' => 'Image must be 5MB or smaller'], 400);
    }

    $category = $_POST['category'] ?? 'misc';
    $category = preg_replace('/[^a-zA-Z0-9-_]/', '', $category);

    $uploadDir = realpath(__DIR__ . '/../Images');
    if ($uploadDir === false) {
        $uploadDir = __DIR__ . '/../Images';
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            sendJSON(['success' => false, 'message' => 'Unable to create upload directory'], 500);
        }
    }
    $uploadDir .= DIRECTORY_SEPARATOR . $category;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        sendJSON(['success' => false, 'message' => 'Unable to create category upload directory'], 500);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeExtension = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'tiff', 'heic', 'heif', 'svg'], true) ? $extension : 'bin';
    $filename = uniqid('product_', true) . '.' . $safeExtension;
    $filepath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relativePath = '/velora/Images/' . $category . '/' . $filename;
        sendJSON(['success' => true, 'imagePath' => $relativePath, 'filename' => $filename]);
    } else {
        sendJSON(['success' => false, 'message' => 'Failed to save image'], 500);
    }
}

// =============================================
// DELETE IMAGE (admin only)
// =============================================
function deleteImage() {
    requireAdmin();

    $imagePath = trim($_POST['imagePath'] ?? '');
    if (!$imagePath) {
        sendJSON(['success' => false, 'message' => 'Image path required'], 400);
    }

    $relativePath = ltrim(str_replace('\\', '/', $imagePath), '/');
    $absolutePath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (file_exists($absolutePath)) {
        if (unlink($absolutePath)) {
            sendJSON(['success' => true, 'message' => 'Image deleted successfully']);
        } else {
            sendJSON(['success' => false, 'message' => 'Failed to delete image file'], 500);
        }
    } else {
        sendJSON(['success' => false, 'message' => 'Image file not found'], 404);
    }
}

// =============================================
// FORMAT PRODUCT for frontend
// =============================================
function formatProduct($row) {
    $normalizeImagePath = function ($path) {
        $path = str_replace('\\', '/', (string)$path);
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }
        $path = ltrim($path, '/');
        if (preg_match('#^Images/#i', $path)) {
            return '/velora/' . $path;
        }
        return '/' . $path;
    };

    // Handle images as JSON array or single string
    $images = [];
    if (!empty($row['image'])) {
        $decoded = json_decode($row['image'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $images = array_map($normalizeImagePath, $decoded);
        } else {
            // Legacy single image
            $images = [$normalizeImagePath($row['image'])];
        }
    }

    $hoverImages = [];
    if (!empty($row['hover_image'])) {
        $decoded = json_decode($row['hover_image'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $hoverImages = array_map($normalizeImagePath, $decoded);
        } else {
            // Legacy single image
            $hoverImages = [$normalizeImagePath($row['hover_image'])];
        }
    }

    $primaryImage = !empty($images) ? $images[0] : '';
    $primaryHover = !empty($hoverImages) ? $hoverImages[0] : $primaryImage;

    return [
        'id'           => (int)$row['id'],
        'name'         => $row['name'],
        'price'        => (float)$row['price'],
        'gstRate'      => (float)($row['gst_rate'] ?? 12),
        'category'     => $row['category'],
        'subcategory'  => $row['subcategory'],
        'images'       => $images,
        'image'        => !empty($primaryImage) ? 'url("' . $primaryImage . '")' : 'none', // For backward compatibility
        'hoverImage'   => !empty($primaryHover) ? 'url("' . $primaryHover . '")' : (!empty($primaryImage) ? 'url("' . $primaryImage . '")' : 'none'),
        'badge'        => $row['badge'],
        'stock'        => (int)$row['stock'],
        'size'         => $row['sizes'] ? explode(',', $row['sizes']) : [],
        'color'        => $row['color'],
        'imageRaw'     => $primaryImage,
        'hoverImageRaw'=> $primaryHover,
        'gst_rate'     => (float)($row['gst_rate'] ?? 12)
    ];
}
?>
