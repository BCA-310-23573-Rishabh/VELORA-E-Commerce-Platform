<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Home';
$pageDescription = 'VELORA | Home';
$adminDisplayName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
  <style>
    body { margin: 0; background: #f7f2eb; color: #1f1f1f; }
    .home-main { padding: 2rem 1rem 4rem; }
    .hero { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 2rem; align-items: center; background: linear-gradient(135deg, #111111, #2b2218); color: #fff; padding: 2.2rem; border-radius: 24px; box-shadow: 0 18px 45px rgba(0,0,0,.18); }
    .hero h1 { font-size: clamp(2rem, 3vw, 3rem); margin: 0 0 1rem; font-family: 'Cormorant Garamond', serif; }
    .hero p { color: rgba(255,255,255,0.82); line-height: 1.7; }
    .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.4rem; }
    .hero-actions a { display: inline-flex; padding: 0.8rem 1.2rem; border-radius: 999px; text-decoration: none; font-weight: 600; }
    .hero-actions .primary { background: #c9a96e; color: #111; }
    .hero-actions .secondary { border: 1px solid rgba(255,255,255,0.25); color: #fff; }
    .hero-card { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 20px; padding: 1.1rem; }
    .hero-card h3 { margin-top: 0; font-size: 1.15rem; }
    .hero-card ul { padding-left: 1rem; color: rgba(255,255,255,0.9); }
    .section-block { margin-top: 2.2rem; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .section-header h2 { margin: 0; font-size: 1.4rem; }
    .section-header a { color: #8b6f4c; text-decoration: none; font-weight: 600; }
    .category-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
    .category-card { background: #fff; border-radius: 16px; padding: 1rem; text-decoration: none; color: inherit; box-shadow: 0 10px 28px rgba(0,0,0,0.06); }
    .category-card h3 { margin: 0 0 0.3rem; font-size: 1rem; }
    .category-card p { margin: 0; color: #6b6b6b; font-size: 0.95rem; }
    .product-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.4rem; }
    .product-grid .product-card { min-height: 100%; }
    .footer { text-align: center; padding: 2rem 1rem 3rem; color: #6b6b6b; }
    @media (max-width: 900px) { .hero, .category-grid, .product-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 640px) { .hero, .category-grid, .product-grid { grid-template-columns: 1fr; } .hero { padding: 1.3rem; } }
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
      <div class="header-left">
        <a href="index.php" class="logo">VELORA</a>
      </div>
      <div class="header-center">
        <div class="search-bar">
          <input type="text" placeholder="Search for products..." class="search-input" id="searchInput">
          <button class="search-btn" aria-label="Search">🔍</button>
        </div>
      </div>
      <div class="header-right">
        <a href="login.php" class="header-icon" title="Account"><span class="icon">👤</span></a>
        <a href="cart.php" class="header-icon" title="Shopping Cart"><span class="icon">🛍️</span><span class="cart-count" id="cartCount">0</span></a>
      </div>
    </div>

    <nav class="navbar">
      <div class="container">
        <div class="nav-menu">
          <a href="index.php" class="nav-item active">Home</a>
          <a href="shop.php" class="nav-item">Shop All</a>
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

  <main class="home-main container">
    <section class="hero">
      <div>
        <p class="eyebrow" style="text-transform:uppercase;letter-spacing:0.24em;color:#c9a96e;font-weight:600;">New season staples</p>
        <h1>Elevated essentials for modern dressing.</h1>
        <p>Browse sharp shirts, tailored trousers, durable outerwear, and versatile denim curated for daily wear.</p>
        <div class="hero-actions">
          <a href="shop.php" class="primary">Shop now</a>
          <a href="contact.php" class="secondary">Contact us</a>
        </div>
      </div>
      <div class="hero-card">
        <h3>Why Velora?</h3>
        <ul>
          <li>Modern layering pieces</li>
          <li>Clean silhouettes and premium fabric</li>
          <li>Fast checkout and easy tracking</li>
        </ul>
      </div>
    </section>

    <section class="section-block">
      <div class="section-header">
        <h2>Shop by collection</h2>
        <a href="shop.php">Browse all products</a>
      </div>
      <div class="category-grid">
        <a class="category-card" href="shirts.php"><h3>Shirts</h3><p>Refined button-down and linen styles.</p></a>
        <a class="category-card" href="tshirts.php"><h3>T-shirts</h3><p>Everyday comfort with a polished finish.</p></a>
        <a class="category-card" href="jeans.php"><h3>Jeans</h3><p>Classic fits and elevated denim details.</p></a>
        <a class="category-card" href="overshirt.php"><h3>Overshirts</h3><p>Layering pieces for every season.</p></a>
      </div>
    </section>

    <section class="section-block">
      <div class="section-header">
        <h2>Popular picks</h2>
        <a href="shop.php">View all</a>
      </div>
      <div class="product-grid" id="essentialGrid"></div>
      <div class="product-grid" id="denimGrid"></div>
      <div class="product-grid" id="outerwearGrid"></div>
      <div class="product-grid" id="linenGrid"></div>
    </section>
  </main>

  <footer class="footer">
    <p>© 2026 Velora. Modern essentials for everyday style.</p>
  </footer>

  <script src="session.js"></script>
  <script src="theme.js"></script>
  <script src="nav.js"></script>
  <script src="script.js"></script>
</body>
</html>