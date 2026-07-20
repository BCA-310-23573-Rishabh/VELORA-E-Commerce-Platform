<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Plus Size';
$pageDescription = 'VELORA | Plus Size';
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
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="../shop.css">
</head>
<body>
  <div class="top-bar"><div class="container"><a href="#" class="pincode-link">Enter Pincode - to check delivery</a></div></div>
  <header class="header"><div class="container header-content"><div class="header-left"><a href="index.php" class="logo">VELORA</a></div><div class="header-center"><div class="search-bar"><input type="text" placeholder="Search for products..." class="search-input" id="searchInput"><button class="search-btn" aria-label="Search">🔍</button></div></div><div class="header-right"><a href="login.php" class="header-icon" title="Account"><span class="icon">👤</span></a><a href="cart.php" class="header-icon" title="Shopping Cart"><span class="icon">🛍️</span><span class="cart-count" id="cartCount">0</span></a></div></div><nav class="navbar"><div class="container"><div class="nav-menu"><a href="index.php" class="nav-item">Home</a><a href="shop.php" class="nav-item">Shop All</a><a href="shirts.php" class="nav-item">Shirts</a><a href="tshirts.php" class="nav-item">T-shirts</a><a href="jeans.php" class="nav-item">Jeans</a><a href="trousers.php" class="nav-item">Trousers</a><a href="cargo-pants.php" class="nav-item">Cargo Pants</a><a href="shoes.php" class="nav-item">Shoes</a><a href="overshirt.php" class="nav-item">Overshirt</a><a href="plus-size.php" class="nav-item active">Plus-Size</a><a href="shorts.php" class="nav-item">Shorts</a></div></div></nav></header>
  <main class="shop-main container"><section class="shop-hero"><h1>Plus Size</h1><p>Relaxed and inclusive essentials created for comfort and confidence.</p></section><div class="shop-layout"><aside class="filter-sidebar" id="filterSidebar"><h3>Filter</h3><div class="filter-group"><h4>Categories</h4><label><input type="checkbox" class="filter-checkbox" value="plus-size" checked> Plus Size</label></div></aside><section class="shop-content"><div class="shop-toolbar"><div class="product-count" id="productCount">0 products</div><select id="sortSelect"><option value="featured">Featured</option><option value="low-high">Price: Low to High</option><option value="high-low">Price: High to Low</option></select></div><div class="shop-products-grid" id="shopProductsGrid"></div><button class="load-more-btn" id="loadMoreBtn" style="display:none;">Load More</button></section></div></main>
  <script src="session.js"></script><script src="theme.js"></script><script src="nav.js"></script><script src="script.js"></script><script src="shop.js"></script>
</body>
</html>