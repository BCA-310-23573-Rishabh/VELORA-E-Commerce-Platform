<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Shop';
$pageDescription = 'VELORA | Shop';
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
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="shop.css">
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
  <a href="shop.php" class="nav-item active">Shop All</a>
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
  


  <main class="shop-main">
    <section class="shop-hero">
      <div>
        <p class="eyebrow">Curated essentials</p>
        <h1>Shop all</h1>
        <p>Find elevated denim, polished layers, and versatile staples in one refined edit.</p>
      </div>
      <div class="shop-hero-meta">
        <span class="pill">Free delivery above ₹2999</span>
        <span class="pill">Easy returns</span>
      </div>
    </section>

    <div class="shop-toolbar">
      <div class="shop-header">
        <h1>shop all <span class="product-count" id="productCount">0 products</span></h1>
        <div class="shop-controls">
          <div class="filter-toggle mobile-only" id="filterToggle">☰ Filters</div>
          <select class="sort-select" id="sortSelect">
            <option value="featured">Featured</option>
            <option value="newest">Newest</option>
            <option value="price-low">Price: Low to High</option>
            <option value="price-high">Price: High to Low</option>
            <option value="name-asc">Name: A to Z</option>
          </select>
        </div>
      </div>
    </div>

    <div class="shop-container">
      <!-- Sidebar Filters -->
      <aside class="filter-sidebar" id="filterSidebar">
        <div class="filter-header">
          <h3>Filters</h3>
          <button class="clear-filters" id="clearFilters">Clear all</button>
          <button class="close-filters mobile-only" id="closeFilters">✕</button>
        </div>

        <!-- Category Filter -->
        <div class="filter-section">
          <h4>Category</h4>
          <div class="filter-options">
            <label class="filter-label">
              <input type="checkbox" class="filter-checkbox" data-filter="category" value="essential"> Essential Wear
            </label>
            <label class="filter-label">
              <input type="checkbox" class="filter-checkbox" data-filter="category" value="denim"> Denim Collection
            </label>
            <label class="filter-label">
              <input type="checkbox" class="filter-checkbox" data-filter="category" value="outerwear"> Outerwear
            </label>
            <label class="filter-label">
              <input type="checkbox" class="filter-checkbox" data-filter="category" value="linen"> Summer Linens
            </label>
            <label class="filter-label">
              <input type="checkbox" class="filter-checkbox" data-filter="category" value="accessories"> Accessories
            </label>
          </div>
        </div>

        <!-- Price Range Filter -->
        <div class="filter-section">
          <h4>Price Range</h4>
          <div class="price-range">
            <input type="range" id="priceMin" class="price-slider" min="0" max="10000" value="0" step="100">
            <input type="range" id="priceMax" class="price-slider" min="0" max="10000" value="10000" step="100">
            <div class="price-inputs">
              <span>₹<span id="minPriceDisplay">0</span></span>
              <span>-</span>
              <span>₹<span id="maxPriceDisplay">10000</span></span>
            </div>
          </div>
        </div>

        <!-- Size Filter -->
        <div class="filter-section">
          <h4>Size</h4>
          <div class="size-options">
            <button class="size-btn" data-size="XS">XS</button>
            <button class="size-btn" data-size="S">S</button>
            <button class="size-btn" data-size="M">M</button>
            <button class="size-btn" data-size="L">L</button>
            <button class="size-btn" data-size="XL">XL</button>
            <button class="size-btn" data-size="XXL">XXL</button>
          </div>
        </div>

        <!-- Color Filter -->
        <div class="filter-section">
          <h4>Color</h4>
          <div class="color-options">
            <button class="color-btn" data-color="black" style="background: #1e1e1e;" title="Black"></button>
            <button class="color-btn" data-color="white" style="background: #ffffff; border: 1px solid #ddd;" title="White"></button>
            <button class="color-btn" data-color="gray" style="background: #808080;" title="Gray"></button>
            <button class="color-btn" data-color="brown" style="background: #8b6f4c;" title="Brown"></button>
            <button class="color-btn" data-color="blue" style="background: #2f5a8c;" title="Blue"></button>
            <button class="color-btn" data-color="green" style="background: #4a6f4a;" title="Green"></button>
          </div>
        </div>
      </aside>

      <!-- Products Grid -->
      <div class="products-wrapper">
        <div class="products-grid" id="shopProductsGrid"></div>
        <div class="load-more-container">
          <button class="load-more-btn" id="loadMoreBtn">Load More Products</button>
        </div>
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

  <script src="session.js"></script>
  <script src="theme.js"></script>
  <script src="nav.js"></script>
  <script src="script.js"></script>
  <script src="shop.js"></script>
</body>
</html>