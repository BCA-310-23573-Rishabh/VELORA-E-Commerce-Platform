<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | My Account';
$pageDescription = 'VELORA | My Account';
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
  <link rel="stylesheet" href="account.css">
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

  <main class="account-main">

    <!-- Account Hero -->
    <div class="account-hero">
      <div class="account-hero-content">
        <div class="account-avatar" id="accountAvatar">VL</div>
        <div class="account-hero-text">
          <h1 id="accountName">Loading...</h1>
          <p id="accountEmail">—</p>
          <span class="member-since" id="memberSince"></span>
        </div>
      </div>
    </div>

    <div class="account-layout">

      <!-- Sidebar -->
      <aside class="account-sidebar">
        <nav class="account-nav">
          <button class="account-nav-btn active" data-tab="overview">
            <span class="nav-icon">📊</span> overview
          </button>
          <button class="account-nav-btn" data-tab="orders">
            <span class="nav-icon">📦</span> my orders
          </button>
          <button class="account-nav-btn" data-tab="addresses">
            <span class="nav-icon">📍</span> addresses
          </button>
          <button class="account-nav-btn" data-tab="profile">
            <span class="nav-icon">👤</span> edit profile
          </button>
          <button class="account-nav-btn" data-tab="password">
            <span class="nav-icon">🔒</span> change password
          </button>
          <button class="account-nav-btn" data-tab="returns">
            <span class="nav-icon">&#8617;</span> my returns
          </button>
          <button class="account-nav-btn" data-tab="myfeedback">
            <span class="nav-icon">&#128172;</span> my feedback
          </button>
          <button class="account-nav-btn logout-btn" id="logoutBtn">
            <span class="nav-icon">🚪</span> logout
          </button>
        </nav>
      </aside>

      <!-- Main Content -->
      <div class="account-content">

        <!-- ── OVERVIEW ── -->
        <div class="account-tab active" id="overviewTab">
          <h2 class="tab-title">overview</h2>
          <div class="overview-stats">
            <div class="overview-stat-card">
              <div class="stat-big" id="totalOrdersCount">0</div>
              <div class="stat-small">total orders</div>
            </div>
            <div class="overview-stat-card">
              <div class="stat-big" id="totalSpentAmount">₹0</div>
              <div class="stat-small">total spent</div>
            </div>
            <div class="overview-stat-card">
              <div class="stat-big" id="pendingOrdersCount">0</div>
              <div class="stat-small">active orders</div>
            </div>
          </div>
          <div class="overview-grid">
            <div class="overview-card">
              <div class="overview-card-header">
                <h3>recent orders</h3>
                <button class="text-btn" onclick="switchTab('orders')">view all →</button>
              </div>
              <div id="recentOrdersOverview"><p class="empty-msg">Loading...</p></div>
            </div>
            <div class="overview-card">
              <div class="overview-card-header">
                <h3>saved addresses</h3>
                <button class="text-btn" onclick="switchTab('addresses')">manage →</button>
              </div>
              <div id="addressesOverview"><p class="empty-msg">Loading...</p></div>
            </div>
          </div>
        </div>

        <!-- ── ORDERS ── -->
        <div class="account-tab" id="ordersTab">
          <h2 class="tab-title">my orders</h2>
          <div class="orders-toolbar" style="display:flex;align-items:center;justify-content:flex-end;gap:0.75rem;margin-bottom:1rem;">
            <label for="ordersSortSelect" style="font-size:0.9rem;color:#555;margin:0;">Sort by</label>
            <select id="ordersSortSelect" style="padding:0.9rem 1rem;border:1px solid #ddd;border-radius:999px;background:#fff;color:#333;">
              <option value="newest">Newest first</option>
              <option value="oldest">Oldest first</option>
              <option value="total-desc">Highest amount</option>
              <option value="total-asc">Lowest amount</option>
            </select>
          </div>
          <div id="ordersList"><p class="empty-msg">Loading...</p></div>
        </div>

        <!-- ── ORDER DETAIL ── -->
        <div class="account-tab" id="orderDetailTab">
          <button class="back-btn" onclick="switchTab('orders')">← back to orders</button>
          <h2 class="tab-title" id="orderDetailTitle">order details</h2>
          <div id="orderDetailContent"></div>
        </div>

        <!-- ── ADDRESSES ── -->
        <div class="account-tab" id="addressesTab">
          <div class="tab-header-row">
            <h2 class="tab-title">saved addresses</h2>
            <button class="add-btn" id="addAddressBtn">+ add new address</button>
          </div>
          <div id="addressesList"><p class="empty-msg">Loading...</p></div>

          <!-- Address Form -->
          <div class="address-form-wrap" id="addressFormWrap" style="display:none;">
            <h3 id="addressFormTitle">add new address</h3>
            <form id="addressForm" novalidate>
              <input type="hidden" id="addressId" value="">
              <div class="form-grid">

                <div class="form-group">
                  <label>address label</label>
                  <input type="text" id="addrLabel" placeholder="e.g. Home, Office, Other" value="Home">
                </div>

                <div class="form-group">
                  <label>full name</label>
                  <input type="text" id="addrFullName" placeholder="Full name of recipient" required>
                </div>

                <div class="form-group full">
                  <label>address line 1</label>
                  <input type="text" id="addrAddress" placeholder="Flat / House No., Building, Street" required>
                </div>

                <div class="form-group full">
                  <label>address line 2 <span style="color:#999;font-weight:400;">(optional)</span></label>
                  <input type="text" id="addrAddress2" placeholder="Area, Colony, Landmark">
                </div>

                <div class="form-group">
                  <label>city</label>
                  <input type="text" id="addrCity" placeholder="City" required>
                </div>

                <div class="form-group">
                  <label>state</label>
                  <select id="addrState">
                    <option value="">-- select state --</option>
                    <option>Andhra Pradesh</option>
                    <option>Arunachal Pradesh</option>
                    <option>Assam</option>
                    <option>Bihar</option>
                    <option>Chhattisgarh</option>
                    <option>Goa</option>
                    <option>Gujarat</option>
                    <option>Haryana</option>
                    <option>Himachal Pradesh</option>
                    <option>Jharkhand</option>
                    <option>Karnataka</option>
                    <option>Kerala</option>
                    <option>Madhya Pradesh</option>
                    <option>Maharashtra</option>
                    <option>Manipur</option>
                    <option>Meghalaya</option>
                    <option>Mizoram</option>
                    <option>Nagaland</option>
                    <option>Odisha</option>
                    <option>Punjab</option>
                    <option>Rajasthan</option>
                    <option>Sikkim</option>
                    <option>Tamil Nadu</option>
                    <option>Telangana</option>
                    <option>Tripura</option>
                    <option>Uttar Pradesh</option>
                    <option>Uttarakhand</option>
                    <option>West Bengal</option>
                    <option>Delhi (NCT)</option>
                    <option>Jammu & Kashmir</option>
                    <option>Ladakh</option>
                    <option>Chandigarh</option>
                    <option>Puducherry</option>
                    <option>Andaman & Nicobar Islands</option>
                    <option>Dadra & Nagar Haveli and Daman & Diu</option>
                    <option>Lakshadweep</option>
                  </select>
                </div>

                <div class="form-group">
                  <label>PIN code</label>
                  <input type="text" id="addrPincode" placeholder="6-digit PIN code" maxlength="6">
                </div>

                <div class="form-group">
                  <label>mobile number</label>
                  
<div style="display:flex;gap:8px;align-items:center;">
<select class="country-code" style="width:110px;padding:10px;border-radius:8px;">
<option value="+91">🇮🇳 +91</option>
<option value="+1">🇺🇸 +1</option>
<option value="+44">🇬🇧 +44</option>
<option value="+61">🇦🇺 +61</option>
<option value="+971">🇦🇪 +971</option>
</select>
<input type="tel" id="addrPhone" placeholder="Enter mobile number" maxlength="15"></div>
                </div>

                <div class="form-group">
                  <label>country</label>
                  <input type="text" id="addrCountry" value="India" readonly
                    style="background:#f5f5f5;cursor:not-allowed;">
                </div>

                <div class="form-group full">
                  <label class="checkbox-label">
                    <input type="checkbox" id="addrDefault">
                    <span>set as default address</span>
                  </label>
                </div>

              </div>
              <div class="form-actions">
                <button type="submit" class="save-btn">save address</button>
                <button type="button" class="cancel-btn" id="cancelAddressBtn">cancel</button>
              </div>
              <div class="form-feedback" id="addressFeedback"></div>
            </form>
          </div>
        </div>

        <!-- ── PROFILE ── -->
        <div class="account-tab" id="profileTab">
          <h2 class="tab-title">edit profile</h2>
          <form id="profileForm" class="settings-form" novalidate>
            <div class="form-grid">
              <div class="form-group">
                <label>first name</label>
                <input type="text" id="profileFirstName" placeholder="First name" required>
              </div>
              <div class="form-group">
                <label>last name</label>
                <input type="text" id="profileLastName" placeholder="Last name" required>
              </div>
              <div class="form-group full">
                <label>email address</label>
                <input type="email" id="profileEmail" placeholder="your@email.com" required>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="save-btn">save changes</button>
            </div>
            <div class="form-feedback" id="profileFeedback"></div>
          </form>
        </div>

        <!-- ── CHANGE PASSWORD ── -->
        <div class="account-tab" id="passwordTab">
          <h2 class="tab-title">change password</h2>
          <form id="passwordForm" class="settings-form" novalidate>
            <div class="form-grid single">
              <div class="form-group full">
                <label>current password</label>
                <input type="password" id="currentPassword" placeholder="Enter current password">
              </div>
              <div class="form-group full">
                <label>new password</label>
                <input type="password" id="newPassword" placeholder="Minimum 6 characters">
              </div>
              <div class="form-group full">
                <label>confirm new password</label>
                <input type="password" id="confirmNewPassword" placeholder="Re-enter new password">
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="save-btn">update password</button>
            </div>
            <div class="form-feedback" id="passwordFeedback"></div>
          </form>
        </div>


        <!-- ── MY RETURNS ── -->
        <div class="account-tab" id="returnsTab">
          <h2 class="tab-title">my returns</h2>
          <div id="myReturnsList"><p class="empty-msg">Loading...</p></div>
        </div>

        <!-- ── MY FEEDBACK ── -->
        <div class="account-tab" id="myfeedbackTab">
          <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
            <h2 class="tab-title" style="margin:0;">my feedback</h2>
            <a href="feedback.php" style="font-size:0.85rem;color:var(--gold);text-decoration:none;padding:0.5rem 1.2rem;border:1px solid rgba(201,169,110,0.4);border-radius:20px;font-weight:500;">+ submit new feedback</a>
          </div>
          <div id="myFeedbackList"><p class="empty-msg">Loading...</p></div>
        </div>

      </div><!-- end account-content -->
    </div><!-- end account-layout -->
  </main>

  <footer class="footer">
    <p>© VELORA — slow fashion for the modern spirit</p>
    <div class="footer-links">
      <a href="#">instagram</a>
      <a href="contact.php">contact</a>
      <a href="returns.php">returns</a>
    </div>
  </footer>

  <!-- nav.js must come before account.js -->
  <script src="../session.js"></script>
  <script src="header-scroll.js"></script>
  <script src="../theme.js"></script>
  <script src="../nav.js"></script>
  <script src="account.js"></script>
</body>
</html>