<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Login';
$pageDescription = 'VELORA | Login';
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
      </div>
    </nav>
  </header>
    </div>
  </header>

  <main class="login-main">
    <div class="login-container">

      <!-- Toggle Buttons -->
      <div class="toggle-container">
        <button class="toggle-btn active" id="loginToggle">sign in</button>
        <button class="toggle-btn" id="registerToggle">register</button>
      </div>

      <!-- ── LOGIN FORM ── -->
      <div class="form-container active" id="loginForm">
        <h2>welcome back</h2>
        <p class="form-subtitle">sign in to your account</p>

        <form id="login" novalidate>
          <div class="form-group">
            <label for="loginEmail">email address</label>
            <input type="email" id="loginEmail" placeholder="Enter your email" autocomplete="email">
          </div>

          <div class="form-group">
            <label for="loginPassword">password</label>
            <input type="password" id="loginPassword" placeholder="Enter your password" autocomplete="current-password">
          </div>

          <div class="form-options">
            <label class="checkbox-label">
              <input type="checkbox"> remember me
            </label>
            <a href="#" class="forgot-link">forgot password?</a>
          </div>

          <button type="submit" class="submit-btn">sign in</button>
        </form>
      </div>

      <!-- ── REGISTER FORM ── -->
      <div class="form-container" id="registerForm">
        <h2>create account</h2>
        <p class="form-subtitle">join the VELORA community</p>

        <form id="register" novalidate>

          <div class="form-row">
            <div class="form-group half">
              <label for="firstName">first name</label>
              <input type="text" id="firstName" placeholder="First name" autocomplete="given-name">
            </div>
            <div class="form-group half">
              <label for="lastName">last name</label>
              <input type="text" id="lastName" placeholder="Last name" autocomplete="family-name">
            </div>
          </div>

          <div class="form-group">
            <label for="registerEmail">email address</label>
            <input type="email" id="registerEmail" placeholder="your@email.com" autocomplete="email">
          </div>

          <div class="form-group">
            <label for="registerPhone">mobile number <span style="color:#999;font-weight:400;">(optional)</span></label>
            
<div style="display:flex;gap:8px;align-items:center;">
<select class="country-code" style="width:110px;padding:10px;border-radius:8px;">
<option value="+91">🇮🇳 +91</option>
<option value="+1">🇺🇸 +1</option>
<option value="+44">🇬🇧 +44</option>
<option value="+61">🇦🇺 +61</option>
<option value="+971">🇦🇪 +971</option>
</select>
<input type="tel" id="registerPhone" placeholder="Enter mobile number" maxlength="15" autocomplete="tel"></div>
          </div>

          <div class="form-group">
            <label for="registerPassword">password</label>
            <input type="password" id="registerPassword" placeholder="Minimum 6 characters" autocomplete="new-password">
            <div class="password-strength" id="passwordStrength">
              <div class="strength-bar"></div>
              <div class="strength-bar"></div>
              <div class="strength-bar"></div>
            </div>
          </div>

          <div class="form-group">
            <label for="confirmPassword">confirm password</label>
            <input type="password" id="confirmPassword" placeholder="Re-enter your password" autocomplete="new-password">
            <div class="password-match" id="passwordMatch"></div>
          </div>

          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" required>
              <span>I agree to the <a href="#">terms of service</a> and <a href="#">privacy policy</a></span>
            </label>
          </div>

          <button type="submit" class="submit-btn">create account</button>
        </form>
      </div>

    </div>
  </main>

  <footer class="footer">
    <p>© VELORA — slow fashion for the modern spirit</p>
    <div class="footer-links">
      <a href="#">instagram</a>
      <a href="contact.php">contact</a>
      <a href="#">returns</a>
    </div>
  </footer>

  <script src="../session.js"></script>
  <script src="header-scroll.js"></script>
  <script src="../theme.js"></script>
  <script src="../nav.js"></script>
  <script src="../script.js"></script>
  <script src="login.js"></script>
</body>
</html>