<?php
// api/password-reset.php
require_once '../config.php';
require_once __DIR__ . '/../mailer/PHPMailer.php';
require_once __DIR__ . '/../mailer/SMTP.php';
require_once __DIR__ . '/../mailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', 0);
set_error_handler(function($no, $str, $file, $line) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Error: $str (line $line)"]);
    exit;
});
setJSONHeaders();

function ensureResetTable($db) {
    $db->query("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        token      VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used       TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
}

function getBaseUrl() {
    if (defined('RESET_BASE_URL') && RESET_BASE_URL) {
        return rtrim(RESET_BASE_URL, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $path = dirname(dirname($_SERVER['SCRIPT_NAME']));
    $path = $path === '/' || $path === '\\' ? '' : rtrim($path, '/\\');
    return $scheme . '://' . $host . $path;
}

function getResetLink($token) {
    return getBaseUrl() . '/reset-password.php?token=' . urlencode($token);
}

function sendPasswordResetEmail($email, $firstName, $resetLink) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $firstName ?: 'Customer');
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body    = '<div style="font-family: Arial, sans-serif; padding: 20px;">'
            . '<h2>Password Reset Request</h2>'
            . '<p>Hello ' . htmlspecialchars($firstName ?: 'Customer') . ',</p>'
            . '<p>We received a request to reset your password. Click the link below to choose a new password:</p>'
            . '<p><a href="' . htmlspecialchars($resetLink) . '" target="_blank">Reset your password</a></p>'
            . '<p>If you did not request a password reset, you can ignore this email.</p>'
            . '<p>Thanks,<br>The Velora Team</p>'
            . '</div>';
        $mail->AltBody = 'Hello ' . ($firstName ?: 'Customer') . ",\n\n"
            . 'Reset your password using the link below:' . "\n"
            . $resetLink . "\n\n"
            . 'If you did not request this, ignore this message.';
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Password reset email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'request': requestReset();  break;
    case 'verify':  verifyToken();   break;
    case 'reset':   resetPassword(); break;
    default: sendJSON(['success' => false, 'message' => 'Invalid action'], 400);
}

// ── REQUEST PASSWORD RESET ────────────────────────────────────────────
function requestReset() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        sendJSON(['success' => false, 'message' => 'POST required'], 405);

    $body  = getRequestBody();
    $email = trim(strtolower($body['email'] ?? ''));

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
        sendJSON(['success' => false, 'message' => 'Please enter a valid email address.'], 400);

    $db   = getDB();
    ensureResetTable($db);

    $stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Always return success (don't reveal if email exists)
    if (!$user) {
        $db->close();
        sendJSON(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);
    }

    // Delete old tokens for this user
    $del = $db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
    $del->bind_param('i', $user['id']); $del->execute(); $del->close();

    // Generate secure token
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    $ins = $db->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $ins->bind_param('iss', $user['id'], $token, $expiresAt);
    $ins->execute(); $ins->close();
    $db->close();

    $resetLink = getResetLink($token);
    $emailSent = sendPasswordResetEmail($email, $user['first_name'] ?? '', $resetLink);

    sendJSON([
        'success'   => true,
        'message'   => 'If that email exists, a reset link has been sent to the provided address.',
        'emailSent' => $emailSent
    ]);
}

// ── VERIFY TOKEN (check if valid before showing the form) ─────────────
function verifyToken() {
    $token = trim($_GET['token'] ?? '');
    if (!$token) sendJSON(['success' => false, 'message' => 'Token required'], 400);

    $db   = getDB();
    ensureResetTable($db);

    $now  = date('Y-m-d H:i:s');
    $stmt = $db->prepare(
        "SELECT prt.*, u.email FROM password_reset_tokens prt
         JOIN users u ON prt.user_id = u.id
         WHERE prt.token = ? AND prt.expires_at > ? AND prt.used = 0"
    );
    $stmt->bind_param('ss', $token, $now);
    $stmt->execute();
    $row  = $stmt->get_result()->fetch_assoc();
    $stmt->close(); $db->close();

    if (!$row) sendJSON(['success' => false, 'message' => 'This reset link is invalid or has expired.'], 400);
    sendJSON(['success' => true, 'email' => $row['email']]);
}

// ── RESET PASSWORD ────────────────────────────────────────────────────
function resetPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        sendJSON(['success' => false, 'message' => 'POST required'], 405);

    $body     = getRequestBody();
    $token    = trim($body['token']       ?? '');
    $password = $body['newPassword']      ?? '';
    $confirm  = $body['confirmPassword']  ?? '';

    if (!$token)               sendJSON(['success' => false, 'message' => 'Reset token required.'], 400);
    if (!$password)            sendJSON(['success' => false, 'message' => 'New password required.'], 400);
    if (strlen($password) < 6) sendJSON(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
    if ($password !== $confirm) sendJSON(['success' => false, 'message' => 'Passwords do not match.'], 400);

    $db  = getDB();
    ensureResetTable($db);
    $now = date('Y-m-d H:i:s');

    $stmt = $db->prepare(
        "SELECT user_id FROM password_reset_tokens
         WHERE token = ? AND expires_at > ? AND used = 0"
    );
    $stmt->bind_param('ss', $token, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $db->close();
        sendJSON(['success' => false, 'message' => 'This reset link is invalid or has expired. Please request a new one.'], 400);
    }

    $userId  = $row['user_id'];
    $hashed  = password_hash($password, PASSWORD_BCRYPT);

    // Update password
    $upd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd->bind_param('si', $hashed, $userId);
    $upd->execute(); $upd->close();

    // Mark token as used
    $mrk = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
    $mrk->bind_param('s', $token);
    $mrk->execute(); $mrk->close();
    $db->close();

    sendJSON(['success' => true, 'message' => 'Password reset successfully! You can now log in.']);
}
?>
