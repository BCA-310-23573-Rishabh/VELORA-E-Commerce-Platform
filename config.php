<?php
// config.php

function envValue($name, $default = '') {
    $value = getenv($name);
    if ($value === false || $value === null || $value === '') {
        $value = $_ENV[$name] ?? $default;
    }
    return $value === false ? $default : $value;
}

define('DB_HOST', envValue('DB_HOST', 'localhost'));
define('DB_USER', envValue('DB_USER', 'root'));
define('DB_PASS', envValue('DB_PASS', ''));
define('DB_NAME', envValue('DB_NAME', 'velora_db'));

// SMTP mailer settings for PHPMailer
define('SMTP_HOST', envValue('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) envValue('SMTP_PORT', '587'));
define('SMTP_SECURE', envValue('SMTP_SECURE', 'tls'));
define('SMTP_USER', envValue('SMTP_USER', 'rishabhc2004@gmail.com'));
define('SMTP_PASS', envValue('SMTP_PASS', 'pzsx rrlw rfxv ptur'));
define('SMTP_FROM_EMAIL', envValue('SMTP_FROM_EMAIL', 'no-reply@velora.com'));
define('SMTP_FROM_NAME', envValue('SMTP_FROM_NAME', 'Velora'));

// Twilio SMS configuration. Fill these values with your Twilio account settings.
define('TWILIO_ACCOUNT_SID', envValue('TWILIO_ACCOUNT_SID', ''));
define('TWILIO_AUTH_TOKEN', envValue('TWILIO_AUTH_TOKEN', ''));
define('TWILIO_FROM_NUMBER', envValue('TWILIO_FROM_NUMBER', ''));
define('TWILIO_TIMEOUT', 30);
define('TWILIO_CONNECT_TIMEOUT', 10);
define('SMS_COUNTRY_CODE', '91');

// Toggle to enable/disable Twilio SMS sending (use env var ENABLE_TWILIO=1 to enable)
define('ENABLE_TWILIO', filter_var(envValue('ENABLE_TWILIO', '0'), FILTER_VALIDATE_BOOLEAN));

// Base URL used for password reset links.
// Set this to your live application URL in production, e.g. https://www.yourdomain.com/velora
// When empty, the code will auto-detect the current host and path.
define('RESET_BASE_URL', envValue('RESET_BASE_URL', 'http://localhost/velora'));

function getDB($allowFallback = false) {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    } catch (Throwable $e) {
        if ($allowFallback) {
            return null;
        }
        http_response_code(500);
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ]));
    }

    if ($conn->connect_error) {
        if ($allowFallback) {
            return null;
        }
        http_response_code(500);
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function setJSONHeaders() {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

function sendJSON($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

function getRequestBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function buildInvoiceUrl($orderNumber, $token) {
    $base = rtrim(RESET_BASE_URL, '/');
    return $base . '/api/invoice.php?order=' . urlencode($orderNumber) . '&token=' . urlencode($token);
}

function normalizePhoneNumber($mobile) {
    $mobile = trim((string)$mobile);
    if ($mobile === '') {
        return false;
    }

    // Remove spaces, dashes and punctuation but keep leading + if present.
    $mobile = preg_replace('/[^0-9\+]/', '', $mobile);

    if (strpos($mobile, '00') === 0) {
        $mobile = '+' . substr($mobile, 2);
    }

    if (strpos($mobile, '+') === 0) {
        $formatted = '+' . preg_replace('/\D/', '', substr($mobile, 1));
    } else {
        $digits = preg_replace('/\D/', '', $mobile);

        if (preg_match('/^0+([6-9][0-9]{9})$/', $digits, $matches)) {
            $digits = $matches[1];
        }

        if (strlen($digits) === 10 && preg_match('/^[6-9][0-9]{9}$/', $digits)) {
            $formatted = '+' . SMS_COUNTRY_CODE . $digits;
        } elseif (strlen($digits) >= 8 && strlen($digits) <= 15 && $digits[0] !== '0') {
            $formatted = '+' . $digits;
        } else {
            return false;
        }
    }

    return preg_match('/^\+[1-9][0-9]{7,14}$/', $formatted) ? $formatted : false;
}

function parseHttpStatusCode($headers) {
    if (!is_array($headers) || empty($headers)) {
        return null;
    }
    if (preg_match('#HTTP/\d\.\d\s+(\d{3})#', $headers[0], $matches)) {
        return (int)$matches[1];
    }
    return null;
}

function sendSMS($mobile, $message) {
    // Short-circuit when Twilio is disabled to avoid outbound calls during debugging
    if (!ENABLE_TWILIO) {
        return [
            'success' => false,
            'message' => 'Twilio integration is currently disabled',
            'http_status' => 200,
            'request_payload' => null,
            'raw' => null,
            'retryable' => false,
        ];
    }

    $formattedMobile = validateIndianMobileNumber($mobile);
    if ($formattedMobile === false) {
        return [
            'success' => false,
            'message' => 'Invalid Indian mobile number',
            'http_status' => 400,
            'request_payload' => null,
            'raw' => null,
            'retryable' => false,
        ];
    }
    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN) || empty(TWILIO_FROM_NUMBER)) {
        return [
            'success' => false,
            'message' => 'Twilio configuration is missing',
            'http_status' => 401,
            'request_payload' => null,
            'raw' => null,
            'retryable' => false,
        ];
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . urlencode(TWILIO_ACCOUNT_SID) . '/Messages.json';
    $payloadArray = [
        'To'   => '+' . SMS_COUNTRY_CODE . $formattedMobile,
        'From' => TWILIO_FROM_NUMBER,
        'Body' => $message,
    ];
    $payload = http_build_query($payloadArray);
    $requestPayload = json_encode($payloadArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $headers = [
        'Authorization: Basic ' . base64_encode(TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN),
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ];

    $response = null;
    $status = null;
    if (function_exists('curl_version')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, TWILIO_TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TWILIO_CONNECT_TIMEOUT);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            return [
                'success' => false,
                'message' => 'Twilio curl error: ' . $curlError,
                'http_status' => $status,
                'request_payload' => $requestPayload,
                'raw' => null,
                'retryable' => true,
            ];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers) . "\r\n",
                'content' => $payload,
                'timeout' => TWILIO_TIMEOUT,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = parseHttpStatusCode($http_response_header ?? []);

        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Unable to reach Twilio API',
                'http_status' => $status,
                'request_payload' => $requestPayload,
                'raw' => null,
                'retryable' => true,
            ];
        }
    }

    $result = json_decode($response, true);
    if (!is_array($result)) {
        return [
            'success' => false,
            'message' => 'Invalid Twilio response',
            'http_status' => $status,
            'request_payload' => $requestPayload,
            'raw' => $response,
            'retryable' => $status >= 500 || $status === 429,
        ];
    }

    $success = isset($result['sid']) && !empty($result['sid']);
    if ($success && ($status === null || ($status >= 200 && $status < 300))) {
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'http_status' => $status,
            'request_payload' => $requestPayload,
            'raw' => $result,
            'retryable' => false,
        ];
    }

    $errorMessage = $result['message'] ?? $result['error_message'] ?? json_encode($result);
    if (!empty($status)) {
        $errorMessage = "HTTP {$status}: " . $errorMessage;
    }

    $retryable = !in_array($status, [400, 401, 403, 422], true);
    return [
        'success' => false,
        'message' => 'Twilio SMS failed: ' . $errorMessage,
        'http_status' => $status,
        'request_payload' => $requestPayload,
        'raw' => $result,
        'retryable' => $retryable,
    ];
}

// Simple, reliable session — works across all pages and tabs
function startSession() {
    if (session_status() !== PHP_SESSION_NONE) return;
    session_set_cookie_params([
        'lifetime' => 86400,   // 24 hours
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function requireLogin() {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
    }
}

function requireAdmin() {
    startSession();
    if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
        sendJSON(['success' => false, 'message' => 'Admin access required'], 403);
    }
}
?>
