<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer/PHPMailer.php';
require_once __DIR__ . '/../mailer/SMTP.php';
require_once __DIR__ . '/../mailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

setJSONHeaders();
startSession();

// Require POST with 'to' email
$body = getRequestBody();
$to = trim($body['to'] ?? '');
if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    sendJSON(['success' => false, 'message' => 'Provide a valid `to` email in POST JSON: {"to":"you@domain.com"}'], 400);
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    // Capture SMTP debug output for diagnostics
    $debugLog = [];
    $mail->SMTPDebug = 2; // verbose
    $mail->Debugoutput = function($str, $level) use (&$debugLog) {
        $debugLog[] = trim($str);
    };
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = 'Velora SMTP Test';
    $mail->Body    = '<p>This is a test email from Velora at ' . date('c') . '</p>';

    $sent = $mail->send();
    if ($sent) {
        sendJSON(['success' => true, 'message' => 'Test email sent (check inbox/spam).', 'debug' => $debugLog]);
    } else {
        sendJSON(['success' => false, 'message' => 'Mailer returned false', 'error' => $mail->ErrorInfo, 'debug' => $debugLog]);
    }
} catch (Exception $e) {
    sendJSON(['success' => false, 'message' => 'PHPMailer exception: ' . $mail->ErrorInfo, 'exception' => $e->getMessage(), 'debug' => $debugLog ?? []]);
}
