<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Reset Password';
$pageDescription = 'VELORA | Reset Password';
$adminDisplayName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="login.css">
  <style>
    .reset-card {
      max-width: 480px;
      margin: 5rem auto;
      background: var(--bg-elevated);
      border-radius: 24px;
      padding: 3rem;
      box-shadow: 0 20px 48px -12px rgba(0,0,0,0.5);
    }
    html[data-theme="light"] .reset-card {
      background: white;
      box-shadow: 0 20px 48px -12px rgba(0,0,0,0.12);
    }
    html:not([data-theme="light"]) .reset-card {
      background: var(--bg-elevated);
      box-shadow: 0 20px 48px -12px rgba(0,0,0,0.5);
    }
    .reset-card h1 { font-size:1.8rem; font-weight:400; text-transform:uppercase; letter-spacing:2px; margin-bottom:0.5rem; }
    .reset-card p  { color:#666; margin-bottom:2rem; font-size:0.95rem; }
    .reset-field   { margin-bottom:1.2rem; }
    .reset-field label { display:block; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; color:#666; font-weight:600; margin-bottom:0.4rem; }
    .reset-field input { width:100%; padding:0.85rem 1rem; border:1.5px solid #ddd; border-radius:10px; font-size:0.95rem; outline:none; transition:border 0.2s; }
    .reset-field input:focus { border-color:#8b6f4c; }
    .reset-btn { width:100%; padding:1rem; background:#1e1e1e; color:white; border:none; border-radius:40px; font-size:0.95rem; font-weight:500; text-transform:uppercase; letter-spacing:1px; cursor:pointer; margin-top:0.5rem; transition:background 0.2s; }
    .reset-btn:hover { background:#8b6f4c; }
    .reset-btn:disabled { opacity:0.6; cursor:not-allowed; }
    .reset-feedback { margin-top:1rem; padding:1rem; border-radius:10px; font-size:0.9rem; display:none; }
    .reset-feedback.success { background:#d1e7dd; color:#0f5132; display:block; }
    .reset-feedback.error   { background:#f8d7da; color:#842029; display:block; }
    .reset-link-box { background:#f9f7f5; border:1px solid #e0d8ce; border-radius:10px; padding:1rem; margin-top:1rem; font-size:0.85rem; word-break:break-all; display:none; }
    .reset-link-box a { color:#8b6f4c; }
    .back-link { text-align:center; margin-top:1.5rem; font-size:0.9rem; }
    .back-link a { color:#8b6f4c; text-decoration:none; }
    .step { display:none; }
    .step.active { display:block; }
    .strength-hints { font-size:0.78rem; color:#999; margin-top:0.4rem; }
  </style>
</head>
<body>
  <!-- Top Bar with Pincode -->
  <div class="top-bar">
    <div class="container">
      <a href="#" class="pincode-link">Enter Pincode - to check delivery</a>
    </div>
  </div>

  <!-- Header -->
  <header class="header">
    <div class="container header-content">
      <!-- Logo -->
      <div class="header-left">
        <div class="logo">VELORA</div>
      </div>

      <!-- Search Bar -->
      <div class="header-center">
        <div class="search-bar">
          <input type="text" placeholder="Search for products..." class="search-input" id="searchInput">
          <button class="search-btn">🔍</button>
        </div>
      </div>

      <!-- Header Right: Account & Cart -->
      <div class="header-right">
        <a href="login.php" class="header-icon" title="Account">
          <span class="icon">👤</span>
        </a>
        <a href="cart.php" class="header-icon" title="Shopping Cart">
          <span class="icon">🛍️</span>
          <span class="cart-count" id="cartCount">0</span>
        </a>
      </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="navbar">
      <div class="container">
        <div class="nav-menu">
          <a href="index.php" class="nav-item">Home</a>
          <a href="login.php" class="nav-item">Sign In</a>
          <a href="shop.php" class="nav-item">Shop</a>
        </div>
      </div>
    </nav>
  </header>

  <main style="padding:2rem 5%;">
    <div class="reset-card">

      <!-- Step 1: Request reset -->
      <div class="step active" id="stepRequest">
        <h1>forgot password</h1>
        <p>Enter your email address and we'll generate a reset link for you.</p>
        <div class="reset-field">
          <label>email address</label>
          <input type="email" id="resetEmail" placeholder="your@email.com">
        </div>
        <button class="reset-btn" id="requestBtn">Send Reset Link</button>
        <div class="reset-feedback" id="requestFeedback"></div>
        <div class="back-link"><a href="login.php">← back to sign in</a></div>
      </div>

      <!-- Step 2: Set new password -->
      <div class="step" id="stepReset">
        <h1>new password</h1>
        <p>Enter and confirm your new password below.</p>
        <div class="reset-field">
          <label>new password</label>
          <input type="password" id="newPassword" placeholder="Minimum 6 characters">
          <div class="strength-hints" id="strengthHints"></div>
        </div>
        <div class="reset-field">
          <label>confirm password</label>
          <input type="password" id="confirmPassword" placeholder="Re-enter password">
        </div>
        <button class="reset-btn" id="resetBtn">Reset Password</button>
        <div class="reset-feedback" id="resetFeedback"></div>
        <div class="back-link"><a href="login.php">← back to sign in</a></div>
      </div>

      <!-- Step 3: Success -->
      <div class="step" id="stepSuccess">
        <div style="text-align:center;padding:1rem 0;">
          <div style="font-size:3rem;margin-bottom:1rem;">✅</div>
          <h1 style="margin-bottom:1rem;">password reset!</h1>
          <p style="margin-bottom:2rem;">Your password has been updated successfully. You can now sign in with your new password.</p>
          <a href="login.php" class="reset-btn" style="display:block;text-align:center;text-decoration:none;">Go to Sign In</a>
        </div>
      </div>

    </div>
  </main>

  <script src="../session.js"></script>
  <script src="../theme.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    let resetToken = '';

    // Check if arrived with token in URL
    const params = new URLSearchParams(window.location.search);
    const urlToken = params.get('token');
    if (urlToken) {
      verifyAndShowReset(urlToken);
    }

    // ── Step 1: Request reset ─────────────────────────────────────
    document.getElementById('requestBtn').addEventListener('click', async function () {
      const email = document.getElementById('resetEmail').value.trim();
      const fb    = document.getElementById('requestFeedback');

      fb.className  = 'reset-feedback';

      if (!email) { showFeedback(fb, 'Please enter your email address.', false); return; }

      this.textContent = 'Sending...'; this.disabled = true;

      const data = await api('../api/password-reset.php?action=request', { method:'POST', body: JSON.stringify({ email }) });

      this.textContent = 'Send Reset Link'; this.disabled = false;

      if (data.success) {
        showFeedback(fb, 'If that email exists, a password reset link has been sent.', true);
      } else {
        showFeedback(fb, data.message || 'Something went wrong.', false);
      }
    });

    // ── Verify token from URL ─────────────────────────────────────
    async function verifyAndShowReset(token) {
      const data = await api('../api/password-reset.php?action=verify&token=' + encodeURIComponent(token));
      if (data.success) {
        resetToken = token;
        showStep('stepReset');
      } else {
        showStep('stepRequest');
        showFeedback(document.getElementById('requestFeedback'), data.message || 'Invalid or expired link.', false);
      }
    }

    // ── Password strength ─────────────────────────────────────────
    document.getElementById('newPassword').addEventListener('input', function () {
      const p = this.value;
      const hints = [];
      if (p.length < 6)              hints.push('At least 6 characters');
      if (!/[A-Z]/.test(p))         hints.push('Add an uppercase letter');
      if (!/[0-9]/.test(p))         hints.push('Add a number');
      document.getElementById('strengthHints').textContent = hints.join(' · ');
    });

    // ── Step 2: Reset password ────────────────────────────────────
    document.getElementById('resetBtn').addEventListener('click', async function () {
      const newPass  = document.getElementById('newPassword').value;
      const confirm  = document.getElementById('confirmPassword').value;
      const fb       = document.getElementById('resetFeedback');

      if (!newPass)               { showFeedback(fb, 'Password is required.', false); return; }
      if (newPass.length < 6)     { showFeedback(fb, 'Password must be at least 6 characters.', false); return; }
      if (newPass !== confirm)    { showFeedback(fb, 'Passwords do not match.', false); return; }
      if (!resetToken)            { showFeedback(fb, 'No reset token. Please request a new link.', false); return; }

      this.textContent = 'Resetting...'; this.disabled = true;

      const data = await api('../api/password-reset.php?action=reset', {
        method: 'POST',
        body: JSON.stringify({ token: resetToken, newPassword: newPass, confirmPassword: confirm })
      });

      this.textContent = 'Reset Password'; this.disabled = false;

      if (data.success) {
        showStep('stepSuccess');
      } else {
        showFeedback(fb, data.message || 'Reset failed. Please try again.', false);
      }
    });

    // ── Helpers ───────────────────────────────────────────────────
    function showStep(id) {
      document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
      document.getElementById(id).classList.add('active');
    }

    function showFeedback(el, msg, success) {
      el.textContent = msg;
      el.className   = 'reset-feedback ' + (success ? 'success' : 'error');
    }

    async function api(url, options) {
      options = options || {};
      options.headers = Object.assign({ 'Content-Type': 'application/json' }, options.headers || {});
      try {
        const res  = await fetch(url, options);
        const text = await res.text();
        return JSON.parse(text);
      } catch (e) {
        return { success: false, message: 'Connection error. Please ensure XAMPP is running.' };
      }
    }
  });
  </script>
</body>
</html>
