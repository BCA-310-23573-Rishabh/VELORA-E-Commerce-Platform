<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Feedback';
$pageDescription = 'VELORA | Feedback';
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
  <style>
    body { background:#090d11; color:#f4f1e6; margin:0; font-family:'Jost',sans-serif; }
    .top-bar { background:#07090b; padding:.75rem 0; }
    .top-bar .container { display:flex; justify-content:flex-end; }
    .pincode-link { color:#cfc5b1; text-decoration:none; font-size:.9rem; }
    .header { background:#090d11; padding:1rem 0; border-bottom:1px solid rgba(255,255,255,0.05); }
    .header .header-content { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .logo { font-family:'Cormorant Garamond',serif; font-size:1.4rem; letter-spacing:.15em; color:#f4f1e6; }
    .search-bar { display:flex; width:100%; max-width:420px; }
    .search-input { flex:1; padding:.85rem 1rem; border-radius:999px 0 0 999px; border:1px solid rgba(255,255,255,0.12); background:rgba(255,255,255,0.04); color:#f4f1e6; }
    .search-btn { padding:.85rem 1rem; border:none; background:#8b6f4c; color:#0c0c0c; border-radius:0 999px 999px 0; cursor:pointer; }
    .header-right { display:flex; gap:1rem; align-items:center; }
    .header-icon { color:#f4f1e6; text-decoration:none; position:relative; }
    .cart-count { position:absolute; top:-8px; right:-10px; background:#f4f1e6; color:#0c0c0c; border-radius:999px; padding:2px 6px; font-size:.7rem; }
    .navbar { background:#0b1016; padding:.75rem 0; }
    .nav-menu { display:flex; flex-wrap:wrap; gap:.75rem; }
    .nav-item { color:#cfc5b1; text-decoration:none; font-size:.95rem; }
    .nav-item:hover { color:#f4f1e6; }
    .feedback-main { padding:4rem 0; }
    .feedback-container { max-width:760px; margin:0 auto; background:rgba(255,255,255,0.04); padding:2rem; border-radius:24px; box-shadow:0 24px 80px rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.08); }
    .feedback-title { margin-bottom:1rem; font-size:2rem; letter-spacing:-0.03em; }
    .feedback-intro { color:#cfc5b1; margin-bottom:2rem; line-height:1.75; }
    .form-group { margin-bottom:1.25rem; }
    .form-group label { display:block; margin-bottom:.5rem; color:#d9d2c3; font-size:.95rem; text-transform:uppercase; letter-spacing:.04em; }
    .form-input, .form-select, .form-textarea { width:100%; border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:1rem; background:rgba(255,255,255,0.05); color:#f4f1e6; font-size:0.95rem; }
    .form-select { appearance:none; }
    .form-textarea { min-height:180px; resize:vertical; }
    .feedback-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .form-actions { margin-top:1.5rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .submit-btn { padding:1rem 2rem; background:#8b6f4c; color:#0c0c0c; border:none; border-radius:999px; font-weight:700; cursor:pointer; transition:opacity .2s ease; }
    .submit-btn:hover { opacity:.95; }
    .feedback-message { min-height:1.4rem; color:#f0d58a; }
    .feedback-help { color:#cfc5b1; font-size:0.94rem; margin-top:1.5rem; }
    @media (max-width: 740px) { .feedback-container { padding:1.5rem; } .feedback-row { grid-template-columns:1fr; } }
    .footer { padding:2rem 0; text-align:center; color:#9e9684; }
    .footer-links { display:flex; justify-content:center; gap:1rem; flex-wrap:wrap; margin-top:.75rem; }
    .footer-links a { color:#cfc5b1; text-decoration:none; }
  </style>
</head>
<body>
  <div class="top-bar">
    <div class="container">
      <a href="#" class="pincode-link">Enter Pincode - to check delivery</a>
    </div>
  </div>
  <header class="header">
    <div class="container header-content">
      <div class="logo">VELORA</div>
      <div class="search-bar">
        <input type="text" placeholder="Search for products..." class="search-input" id="searchInput">
        <button class="search-btn">🔍</button>
      </div>
      <div class="header-right">
        <a href="login.php" class="header-icon" title="Account">👤</a>
        <a href="cart.php" class="header-icon" title="Cart">🛍️<span class="cart-count" id="cartCount">0</span></a>
      </div>
    </div>
    <nav class="navbar">
      <div class="container nav-menu">
        <a href="index.php" class="nav-item">Home</a>
        <a href="shirts.php" class="nav-item">Shirts</a>
        <a href="tshirts.php" class="nav-item">T-shirts</a>
        <a href="jeans.php" class="nav-item">Jeans</a>
        <a href="trousers.php" class="nav-item">Trousers</a>
        <a href="cargo-pants.php" class="nav-item">Cargo Pants</a>
        <a href="shoes.php" class="nav-item">Shoes</a>
        <a href="overshirt.php" class="nav-item">Overshirt</a>
        <a href="plus-size.php" class="nav-item">Plus-Size</a>
        <a href="shorts.php" class="nav-item">Shorts</a>
      </div>
    </nav>
  </header>
  <main class="feedback-main">
    <div class="container feedback-container">
      <h1 class="feedback-title">Share Your Feedback</h1>
      <p class="feedback-intro">Tell us what you think about your shopping experience, our products, or our service. We’ll review every message and reply to you if you provide an email.</p>
      <form id="feedbackForm" novalidate>
        <div class="feedback-row">
          <div class="form-group">
            <label for="feedbackName">Name</label>
            <input type="text" id="feedbackName" class="form-input" placeholder="Your name" required>
          </div>
          <div class="form-group">
            <label for="feedbackEmail">Email</label>
            <input type="email" id="feedbackEmail" class="form-input" placeholder="Your email" required>
          </div>
        </div>
        <div class="form-group">
          <label for="feedbackSubject">Subject</label>
          <input type="text" id="feedbackSubject" class="form-input" placeholder="Subject (optional)">
        </div>
        <div class="feedback-row">
          <div class="form-group">
            <label for="feedbackType">Type</label>
            <select id="feedbackType" class="form-select">
              <option value="general">General</option>
              <option value="product">Product</option>
              <option value="service">Service</option>
              <option value="complaint">Complaint</option>
              <option value="suggestion">Suggestion</option>
            </select>
          </div>
          <div class="form-group">
            <label for="feedbackRating">Rating</label>
            <select id="feedbackRating" class="form-select">
              <option value="">No rating</option>
              <option value="5">5 stars</option>
              <option value="4">4 stars</option>
              <option value="3">3 stars</option>
              <option value="2">2 stars</option>
              <option value="1">1 star</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label for="feedbackMessageText">Message</label>
          <textarea id="feedbackMessageText" class="form-textarea" placeholder="Write your feedback here" required></textarea>
        </div>
        <div class="form-actions">
          <button type="submit" class="submit-btn">Send Feedback</button>
          <span id="feedbackResult" class="feedback-message"></span>
        </div>
      </form>
      <p class="feedback-help">Logged in? We’ll prefill your name and email automatically if you are signed in.</p>
    </div>
  </main>
  <footer class="footer">
    <p>© VELORA — slow fashion for the modern spirit</p>
    <div class="footer-links">
      <a href="contact.php">contact</a>
      <a href="returns.php">returns</a>
      <a href="shop.php">shop</a>
    </div>
  </footer>
  <script src="../session.js"></script>
  <script src="../theme.js"></script>
  <script src="../nav.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.getElementById('feedbackForm');
      const result = document.getElementById('feedbackResult');
      const nameField = document.getElementById('feedbackName');
      const emailField = document.getElementById('feedbackEmail');
      form.addEventListener('submit', async function (event) {
        event.preventDefault();
        result.style.color = '#f0d58a';
        result.textContent = 'Sending feedback...';
        const payload = {
          name: nameField.value.trim(),
          email: emailField.value.trim(),
          subject: document.getElementById('feedbackSubject').value.trim(),
          type: document.getElementById('feedbackType').value,
          rating: document.getElementById('feedbackRating').value || null,
          message: document.getElementById('feedbackMessageText').value.trim()
        };
        if (!payload.name || !payload.email || !payload.message) {
          result.style.color = '#ffb3b3';
          result.textContent = 'Please provide your name, email, and message.';
          return;
        }
        try {
          const response = await fetch('../api/feedback.php?action=submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const data = await response.json();
          if (!data.success) throw new Error(data.message || 'Unable to submit feedback.');
          result.style.color = '#b8e986';
          result.textContent = data.message || 'Thank you! Your feedback has been sent.';
          form.reset();
        } catch (error) {
          result.style.color = '#ffb3b3';
          result.textContent = error.message || 'There was an error submitting feedback.';
        }
      });
      fetch('../api/auth.php?action=check')
        .then(res => res.json())
        .then(data => {
          if (data.loggedIn && data.user) {
            nameField.value = `${data.user.firstName || ''} ${data.user.lastName || ''}`.trim();
            emailField.value = data.user.email || '';
          }
        })
        .catch(() => {});
    });
  </script>
</body>
</html>
