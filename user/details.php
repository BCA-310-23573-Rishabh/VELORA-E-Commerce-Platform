<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Product Details';
$pageDescription = 'VELORA | Product Details';
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
  <link rel="stylesheet" href="../shop.css">
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
          <button class="search-btn">🔍</button>
        </div>
      </div>
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

  <main class="details-main container">
    <section class="product-summary">
      <div class="product-summary-left">
        <div class="product-image-block">
          <img id="productImage" src="" alt="Product image">
          <div class="product-image-thumbnails" id="productThumbnails"></div>
        </div>
      </div>
      <div class="product-summary-right">
        <div class="product-meta">
          <div class="product-badge" id="productBadge"></div>
          <h1 id="productName">Product name</h1>
          <p class="product-category" id="productCategory">Category</p>
          <div class="product-price" id="productPrice">₹0</div>
          <div class="product-rating" id="productRatingLabel">4.5 / 5 · 0 reviews</div>
        </div>

        <div class="product-description" id="productDescription">
          <p>Loading product details…</p>
        </div>

        <div class="product-options">
          <div class="option-group" id="sizeWrapper">
            <div class="option-label">Choose size</div>
            <div class="option-list" id="sizeOptions"></div>
          </div>

          <div class="option-group" id="colorWrapper">
            <div class="option-label">Choose colour</div>
            <div class="option-list" id="colorOptions"></div>
          </div>

          <div class="option-group quantity-group">
            <div class="option-label">Quantity</div>
            <div class="quantity-control">
              <button type="button" id="qtyMinus">−</button>
              <input id="quantityInput" type="number" min="1" value="1">
              <button type="button" id="qtyPlus">+</button>
            </div>
            <div class="stock-info" id="stockInfo"></div>
          </div>
        </div>

        <button id="detailsAddToCart" class="add-to-cart detail-add-btn">add to cart</button>
        <div class="detail-note">Add product notes, choose options, and submit your review below.</div>
      </div>
    </section>

    <section class="product-reviews">
      <div class="reviews-panel">
        <div class="reviews-summary-block">
          <h2>Customer reviews</h2>
          <div id="reviewsSummary"></div>
        </div>
        <div class="reviews-list" id="reviewsList"></div>
      </div>

      <div class="review-form-panel">
        <h2>Share your review</h2>
        <div class="review-inputs">
          <div class="form-row half">
            <input type="text" id="reviewerName" placeholder="Your name">
            <div class="review-rating-wrapper">
              <div class="review-rating-label">Your rating</div>
              <div id="reviewStarPicker" class="review-star-picker" aria-label="Select rating"></div>
              <input id="reviewRating" type="hidden" value="">
            </div>
          </div>
          <div class="form-row">
            <textarea id="reviewText" rows="5" placeholder="Write your review"></textarea>
          </div>
          <button id="submitReview" class="add-to-cart detail-add-btn">submit review</button>
          <p class="review-help">Reviews help others understand fit, feel and finish.</p>
          <p class="review-message" id="reviewMessage"></p>
        </div>
      </div>
    </section>
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
  <script src="details.js"></script>
</body>
</html>