<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Cart';
$pageDescription = 'VELORA | Cart';
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
  <link rel="stylesheet" href="cart.css">
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
  </header>

  <main class="cart-main">
    <!-- Cart Header -->
    <div class="cart-header">
      <h1>your shopping cart</h1>
      <p class="cart-info" id="cartInfo">loading...</p>
    </div>

    <!-- Cart Content -->
    <div class="cart-container">
      <!-- Cart Items Section -->
      <div class="cart-items-section">
        <div class="cart-items-header">
          <div class="product-col">product</div>
          <div class="price-col">price</div>
          <div class="quantity-col">quantity</div>
          <div class="total-col">total</div>
          <div class="remove-col"></div>
        </div>
        
        <div class="cart-items" id="cartItems">
          <!-- Cart items will be injected via JS -->
        </div>

        <!-- Empty Cart Message -->
        <div class="empty-cart" id="emptyCart" style="display: none;">
          <div class="empty-cart-icon">🛒</div>
          <h2>your cart is empty</h2>
          <p>Looks like you haven't added anything to your cart yet.</p>
          <a href="shop.php" class="continue-shopping-btn">continue shopping</a>
        </div>

        <!-- Cart Actions -->
        <div class="cart-actions">
          <div class="cart-actions-left">
            <button class="update-cart-btn" id="updateCartBtn">update cart</button>
            <button class="clear-cart-btn" id="clearCartBtn">🗑 clear cart</button>
          </div>
          <button class="continue-shopping-link" onclick="window.location.href='shop.php'">← continue shopping</button>
        </div>
      </div>

      <!-- Order Summary Section -->
      <div class="order-summary-section">
        <h2>order summary</h2>
        
        <div class="summary-details">
          <div class="summary-row">
            <span>subtotal <span style="font-size:0.75rem;color:#999;">(excl. GST)</span></span>
            <span id="subtotal">₹0.00</span>
          </div>
          <div class="summary-row">
            <span>GST (incl.) <span style="font-size:0.75rem;color:#999;cursor:help;" title="GST is calculated based on each product's applicable tax rate">ⓘ</span></span>
            <span id="gstAmount" style="color:#666;">₹0.00</span>
          </div>
          <div id="gstBreakdownNote" style="display:none;font-size:0.75rem;color:#999;padding:0.3rem 0 0.5rem;line-height:1.5;border-bottom:1px dashed #eee;margin-bottom:0.5rem;"></div>
          <div class="summary-row">
            <span>shipping</span>
            <span id="shipping">calculated at checkout</span>
          </div>

          <div class="summary-row discount-row" id="discountRow" style="display: none;">
            <span>discount</span>
            <span id="discountAmount">-₹0.00</span>
          </div>
          <div class="summary-row total-row">
            <span>estimated total</span>
            <span id="total">₹0.00</span>
          </div>
        </div>

        <!-- Promo Code -->
        <div class="promo-code">
          <h3>have a promo code?</h3>
          <div class="promo-input-group">
            <input type="text" id="promoInput" placeholder="enter code">
            <button id="applyPromoBtn">apply</button>
          </div>
          <div class="promo-message" id="promoMessage"></div>
          <div class="valid-promos">
            <p>Try: <strong>WELCOME10</strong> (10% off) or <strong>FREESHIP</strong> (free shipping)</p>
          </div>
        </div>

        <!-- Checkout Button -->
        <button class="checkout-btn" id="checkoutBtn">proceed to checkout</button>

        <!-- Payment Methods -->
        <div class="payment-methods">
          <p>we accept:</p>
          <div class="payment-icons">
            <span class="payment-icon">UPI</span>
            <span class="payment-icon">Visa</span>
            <span class="payment-icon">Mastercard</span>
            <span class="payment-icon">RuPay</span>
            <span class="payment-icon">Net Banking</span>
            <span class="payment-icon">COD</span>
          </div>
        </div>

        <!-- Guarantee -->
        <div class="guarantee">
          <div class="guarantee-item">
            <span class="guarantee-icon">🔒</span>
            <span>secure checkout</span>
          </div>
          <div class="guarantee-item">
            <span class="guarantee-icon">🔄</span>
            <span>free returns within 30 days</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Recently Viewed / You May Also Like -->
    <section class="recommendations">
      <h2 class="section-title">you may also like</h2>
      <div class="recommendations-grid" id="recommendationsGrid">
        <!-- Recommendations will be injected via JS -->
      </div>
    </section>

    <!-- Shipping & Returns Info -->
    <section class="shipping-info">
      <div class="info-grid">
        <div class="info-card">
          <h3>🚚 free shipping</h3>
          <p>on orders over $150</p>
        </div>
        <div class="info-card">
          <h3>🔄 easy returns</h3>
          <p>30-day return policy</p>
        </div>
        <div class="info-card">
          <h3>💬 24/7 support</h3>
          <p>live chat available</p>
        </div>
        <div class="info-card">
          <h3>🌱 sustainable</h3>
          <p>eco-friendly packaging</p>
        </div>
      </div>
    </section>
  </main>

  <!-- Checkout Modal -->
  <div class="modal" id="checkoutModal">
    <div class="modal-content">
      <span class="close-modal" id="closeModal">&times;</span>
      <h2>checkout</h2>
      <p class="modal-subtitle">complete your purchase</p>
      
      <form id="checkoutForm" class="checkout-form">
        <div class="form-section">
          <h3>contact information</h3>
          <div class="form-row">
            <input type="email" id="checkoutEmail" placeholder="Email address" required>
          </div>
          <div class="form-row">
            
<div style="display:flex;gap:8px;align-items:center;">
<select class="country-code" style="width:110px;padding:10px;border-radius:8px;">
<option value="+91">🇮🇳 +91</option>
<option value="+1">🇺🇸 +1</option>
<option value="+44">🇬🇧 +44</option>
<option value="+61">🇦🇺 +61</option>
<option value="+971">🇦🇪 +971</option>
</select>
<input type="tel" id="checkoutPhone" placeholder="Enter mobile number" maxlength="15"></div>
          </div>
        </div>

        <div class="form-section">
          <h3>shipping address</h3>
          <!-- Saved addresses panel (shown only when logged in) -->
          <div id="savedAddressesPanel" style="display:none;margin-bottom:1.2rem;">
            <div style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;color:#666;font-weight:600;margin-bottom:0.6rem;">
              Saved Addresses
            </div>
            <div id="savedAddressList" style="display:flex;flex-direction:column;gap:0.5rem;"></div>
            <div style="font-size:0.78rem;color:#999;margin-top:0.4rem;">
              or fill in a new address below
            </div>
            <hr style="border:none;border-top:1px solid #eee;margin:1rem 0;">
          </div>
          <div class="form-row half">
            <input type="text" id="checkoutFirstName" placeholder="First name" required>
            <input type="text" id="checkoutLastName" placeholder="Last name" required>
          </div>
          <div class="form-row">
            <input type="text" id="checkoutAddress" placeholder="House / Flat No., Street, Area" required>
          </div>
          <div class="form-row">
            <input type="text" id="checkoutAddress2" placeholder="Landmark (optional)">
          </div>
          <div class="form-row half">
            <input type="text" id="checkoutCity" placeholder="City" required>
            <input type="text" placeholder="State" required id="checkoutState">
          </div>
          <div class="form-row half">
            <input type="text" placeholder="PIN code" maxlength="6" id="checkoutPincode" required>
            <select required>
              <option value="" disabled selected>country</option>
              <option value="IN" selected>India</option>
            </select>
          </div>
        </div>

        <div class="form-section">
          <h3>payment method</h3>
          <div class="payment-options">
            <label class="payment-option">
              <input type="radio" name="payment" value="upi" checked>
              <span>UPI</span>
            </label>
            <label class="payment-option">
              <input type="radio" name="payment" value="card">
              <span>debit / credit card</span>
            </label>
            <label class="payment-option">
              <input type="radio" name="payment" value="netbanking">
              <span>net banking</span>
            </label>
            <label class="payment-option">
              <input type="radio" name="payment" value="cod">
              <span>cash on delivery</span>
            </label>
          </div>

          <div class="credit-card-form" id="upiForm">
            <div class="form-row">
              <input type="text" placeholder="UPI ID (e.g. rahul@upi)" id="upiId">
            </div>
          </div>

          <div class="credit-card-form" id="cardForm" style="display:none;">
            <div class="form-row">
              <input type="text" placeholder="card number (16 digits)" maxlength="19" id="cardNumber">
            </div>
            <div class="form-row half">
              <input type="text" placeholder="MM/YY" maxlength="5" id="cardExpiry">
              <input type="text" placeholder="CVV" maxlength="3" id="cardCvv">
            </div>
            <div class="form-row">
              <input type="text" placeholder="Name on card">
            </div>
          </div>

          <div class="credit-card-form" id="netbankingForm" style="display:none;">
            <div class="form-row">
              <select id="bankSelect">
                <option value="" disabled selected>Select your bank</option>
                <option value="sbi">State Bank of India</option>
                <option value="hdfc">HDFC Bank</option>
                <option value="icici">ICICI Bank</option>
                <option value="axis">Axis Bank</option>
                <option value="kotak">Kotak Mahindra Bank</option>
                <option value="pnb">Punjab National Bank</option>
                <option value="bob">Bank of Baroda</option>
                <option value="other">Other Bank</option>
              </select>
            </div>
          </div>

          <div class="credit-card-form" id="codForm" style="display:none;">
            <p class="cod-message">
  📦 Pay cash or UPI at the time of delivery. ₹50 COD charge applies.
</p>
          </div>
        </div>

        <div class="form-section">
          <label class="checkbox-label">
            <input type="checkbox" required>
            I agree to the <a href="#">terms and conditions</a> and <a href="#">privacy policy</a>
          </label>
        </div>

        <div class="checkout-feedback" id="checkoutFeedback" role="status" aria-live="polite"></div>
        <button type="submit" class="complete-order-btn">complete order</button>
        <p class="order-summary-total" id="modalTotal">total: ₹0.00</p>
      </form>
    </div>
  </div>

  <footer class="footer">
    <p>© VELORA — slow fashion for the modern spirit</p>
    <div class="footer-links">
      <a href="#">instagram</a>
      <a href="contact.php">contact</a>
      <a href="#">returns</a>
    </div>
  </footer>

  <script src="session.js"></script>
  <script src="header-scroll.js"></script>
  <script src="theme.js"></script>
  <script src="nav.js"></script>
  <script src="script.js"></script>
  <script src="cart.js"></script>
</body>
</html>