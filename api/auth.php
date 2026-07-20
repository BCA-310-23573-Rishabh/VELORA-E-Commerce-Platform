<?php
// api/auth.php - Authentication endpoints
require_once '../config.php';

setJSONHeaders();
startSession();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':    handleLogin();    break;
    case 'register': handleRegister(); break;
    case 'logout':   handleLogout();   break;
    case 'check':    checkSession();   break;
    default: sendJSON(['success' => false, 'message' => 'Invalid action'], 400);
}

// ── Validation helpers ────────────────────────────────────────────────
function validateName($name) {
    // Only letters, spaces, hyphens, apostrophes — no numbers
    return preg_match('/^[a-zA-Z\s\'\-]{2,50}$/', trim($name));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 255;
}

function validateIndianPhone($phone) {
    // Indian mobile: exactly 10 digits, starts with 6–9
    return preg_match('/^[6-9][0-9]{9}$/', $phone);
}

// ── LOGIN ─────────────────────────────────────────────────────────────
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        sendJSON(['success' => false, 'message' => 'POST required'], 405);

    $body     = getRequestBody();
    $email    = trim(strtolower($body['email'] ?? ''));
    $password = $body['password'] ?? '';

    if (!$email || !$password)
        sendJSON(['success' => false, 'message' => 'Email and password are required'], 400);

    if (!validateEmail($email))
        sendJSON(['success' => false, 'message' => 'Please enter a valid email address'], 400);

    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT id, first_name, last_name, email, password, is_admin FROM users WHERE email = ?"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$user || !password_verify($password, $user['password']))
        sendJSON(['success' => false, 'message' => 'Incorrect email or password'], 401);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name']  = $user['last_name'];
    $_SESSION['user_name']  = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['email']      = $user['email'];
    $_SESSION['is_admin']   = (bool)$user['is_admin'];

    sendJSON([
        'success' => true,
        'message' => 'Login successful',
        'user'    => [
            'id'        => $user['id'],
            'firstName' => $user['first_name'],
            'lastName'  => $user['last_name'],
            'email'     => $user['email'],
            'isAdmin'   => (bool)$user['is_admin']
        ]
    ]);
}

// ── REGISTER ──────────────────────────────────────────────────────────
function handleRegister() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        sendJSON(['success' => false, 'message' => 'POST required'], 405);

    $body      = getRequestBody();
    $firstName = trim($body['firstName'] ?? '');
    $lastName  = trim($body['lastName']  ?? '');
    $email     = trim(strtolower($body['email'] ?? ''));
    $phone     = trim($body['phone'] ?? '');
    $password  = $body['password'] ?? '';

    $errors = [];

    if (!$firstName)
        $errors[] = 'First name is required';
    elseif (!validateName($firstName))
        $errors[] = 'First name can only contain letters (no numbers or special characters)';

    if (!$lastName)
        $errors[] = 'Last name is required';
    elseif (!validateName($lastName))
        $errors[] = 'Last name can only contain letters (no numbers or special characters)';

    if (!$email)
        $errors[] = 'Email address is required';
    elseif (!validateEmail($email))
        $errors[] = 'Please enter a valid email address';

    if ($phone !== '' && !validateIndianPhone($phone))
        $errors[] = 'Mobile number must be 10 digits and start with 6, 7, 8 or 9';

    if (!$password)
        $errors[] = 'Password is required';
    elseif (strlen($password) < 6)
        $errors[] = 'Password must be at least 6 characters';

    if (!empty($errors))
        sendJSON(['success' => false, 'message' => implode('. ', $errors)], 400);

    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close(); $db->close();
        sendJSON(['success' => false, 'message' => 'An account with this email already exists'], 409);
    }
    $stmt->close();

    $hashed = password_hash($password, PASSWORD_BCRYPT);

    // Handle optional phone column gracefully
    $cols = $db->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if ($cols && $cols->num_rows > 0) {
        $stmt = $db->prepare(
            "INSERT INTO users (first_name, last_name, email, phone, password, is_admin) VALUES (?,?,?,?,?,0)"
        );
        $stmt->bind_param('sssss', $firstName, $lastName, $email, $phone, $hashed);
    } else {
        $stmt = $db->prepare(
            "INSERT INTO users (first_name, last_name, email, password, is_admin) VALUES (?,?,?,?,0)"
        );
        $stmt->bind_param('ssss', $firstName, $lastName, $email, $hashed);
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close(); $db->close();
        sendJSON(['success' => false, 'message' => 'Registration failed. Please try again. (' . $err . ')'], 500);
    }

    $newId = $stmt->insert_id;
    $stmt->close();
    $db->close();

    $_SESSION['user_id']    = $newId;
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name']  = $lastName;
    $_SESSION['user_name']  = trim($firstName . ' ' . $lastName);
    $_SESSION['email']      = $email;
    $_SESSION['is_admin']   = false;

    sendJSON([
        'success' => true,
        'message' => 'Account created successfully',
        'user'    => [
            'id'        => $newId,
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'email'     => $email,
            'isAdmin'   => false
        ]
    ]);
}

// ── LOGOUT ────────────────────────────────────────────────────────────
function handleLogout() {
    session_destroy();
    sendJSON(['success' => true, 'message' => 'Logged out successfully']);
}

// ── CHECK SESSION ─────────────────────────────────────────────────────
function checkSession() {
    if (isset($_SESSION['user_id'])) {
        sendJSON([
            'success'  => true,
            'loggedIn' => true,
            'user'     => [
                'id'        => $_SESSION['user_id'],
                'firstName' => $_SESSION['first_name'],
                'lastName'  => $_SESSION['last_name'],
                'email'     => $_SESSION['email'],
                'isAdmin'   => (bool)$_SESSION['is_admin']
            ]
        ]);
    } else {
        sendJSON(['success' => true, 'loggedIn' => false]);
    }
}
?>
