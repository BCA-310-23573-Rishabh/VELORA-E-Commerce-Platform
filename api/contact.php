<?php
// api/contact.php - Contact form and newsletter endpoints
require_once '../config.php';

setJSONHeaders();

$action = $_GET['action'] ?? 'contact';

switch ($action) {
    case 'contact':
        handleContact();
        break;
    case 'newsletter':
        handleNewsletter();
        break;
    default:
        sendJSON(['success' => false, 'message' => 'Invalid action'], 400);
}

// =============================================
// CONTACT FORM
// =============================================
function handleContact() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'message' => 'POST required'], 405);
    }

    $body    = getRequestBody();
    $name    = trim($body['name'] ?? '');
    $email   = trim($body['email'] ?? '');
    $topic   = trim($body['topic'] ?? '');
    $message = trim($body['message'] ?? '');

    if (!$name || !$email || !$message) {
        sendJSON(['success' => false, 'message' => 'Name, email, and message are required'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJSON(['success' => false, 'message' => 'Invalid email address'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO contact_messages (name, email, topic, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $name, $email, $topic, $message);

    if (!$stmt->execute()) {
        $stmt->close();
        $db->close();
        sendJSON(['success' => false, 'message' => 'Failed to save message'], 500);
    }

    $stmt->close();
    $db->close();

    sendJSON(['success' => true, 'message' => "Thanks $name! We'll get back to you within 24-48 hours."]);
}

// =============================================
// NEWSLETTER SUBSCRIBE
// =============================================
function handleNewsletter() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'message' => 'POST required'], 405);
    }

    $body  = getRequestBody();
    $email = trim($body['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJSON(['success' => false, 'message' => 'Valid email required'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    $db->close();

    if ($affected === 0) {
        sendJSON(['success' => true, 'message' => 'You are already subscribed!']);
    } else {
        sendJSON(['success' => true, 'message' => 'Successfully subscribed to the journal!']);
    }
}
?>
