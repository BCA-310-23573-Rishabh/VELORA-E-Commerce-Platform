<?php
require_once __DIR__ . '/../config.php';
session_start();

$pageTitle = 'VELORA | Admin Panel';
$pageDescription = 'VELORA | Admin Panel';
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
  <link rel="stylesheet" href="admin.css">
  <style>
    /* GST tab extra styles - Dark Mode */
    .gst-summary-bar {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .gst-summary-card {
      background: #1a1a1a;
      border-radius: 12px;
      padding: 1rem 1.2rem;
      box-shadow: 0 4px 12px -4px rgba(0,0,0,0.4);
      cursor: pointer;
      border: 2px solid #333;
      transition: all 0.2s;
      text-align: center;
    }
    .gst-summary-card:hover { border-color: #c9a96e; transform: translateY(-2px); background: #252525; }
    .gst-rate-pill {
      display: inline-block;
      background: #c9a96e;
      color: #0c0c0c;
      font-size: 1.3rem;
      font-weight: 700;
      padding: 0.3rem 0.8rem;
      border-radius: 20px;
      margin-bottom: 0.4rem;
    }
    .gst-card-label { font-size: 0.75rem; color: #b8a89a; text-transform: uppercase; letter-spacing: 0.5px; }
    .gst-card-count { font-size: 0.85rem; color: #8b7b6b; margin-top: 0.2rem; }
    .gst-bulk-section {
      background: #1a1a1a;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 4px 12px -4px rgba(0,0,0,0.4);
      margin-bottom: 2rem;
      display: flex;
      align-items: flex-end;
      gap: 1rem;
      flex-wrap: wrap;
      border: 1px solid #333;
    }
    .gst-bulk-section h3 { width: 100%; margin-bottom: 0.5rem; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; color: #c9a96e; }
    .gst-bulk-section select,
    .gst-bulk-section input { padding: 0.7rem 1rem; border: 1px solid #444; border-radius: 8px; font-size: 0.95rem; background: #222; color: #f0ebe3; }
    .gst-bulk-section input::placeholder { color: #b8a89a; }
    .gst-bulk-section input { width: 100px; }
    .gst-apply-btn { padding: 0.7rem 1.5rem; background: #c9a96e; color: #0c0c0c; border: none; border-radius: 40px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
    .gst-apply-btn:hover { background: #b8945f; }
    .gst-products-table { background: #1a1a1a; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px -4px rgba(0,0,0,0.4); border: 1px solid #333; }
    .gst-table-header {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr 1fr 120px;
      padding: 1rem 1.5rem;
      background: #222;
      font-weight: 600;
      font-size: 0.8rem;
      color: #c9a96e;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 1px solid #333;
    }
    .gst-table-row {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr 1fr 120px;
      padding: 1rem 1.5rem;
      border-bottom: 1px solid #2a2a2a;
      align-items: center;
      font-size: 0.9rem;
      color: #f0ebe3;
    }
    .gst-table-row:hover { background: #252525; }
    .gst-input-inline {
      width: 70px;
      padding: 0.3rem 0.5rem;
      border: 1px solid #444;
      border-radius: 6px;
      font-size: 0.85rem;
      text-align: center;
      background: #222;
      color: #f0ebe3;
    }
    .gst-save-row-btn { padding: 0.3rem 0.8rem; background: #c9a96e; color: #0c0c0c; border: none; border-radius: 20px; font-size: 0.78rem; cursor: pointer; font-weight: 500; }
    .gst-save-row-btn:hover { background: #b8945f; }
    .category-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: 500; text-transform: capitalize; background: #2a2420; color: #c9a96e; border: 1px solid #444; }
    .gst-presets { background: #1a1a1a; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 12px -4px rgba(0,0,0,0.4); margin-top: 2rem; border: 1px solid #333; }
    .gst-presets h3 { font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; color: #c9a96e; margin-bottom: 1rem; }
    .preset-list { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; }
    .preset-chip { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; background: #2a2a2a; border-radius: 20px; font-size: 0.85rem; color: #f0ebe3; border: 1px solid #444; }
    .preset-chip button { background: none; border: none; color: #ff6b6b; cursor: pointer; font-size: 1rem; line-height: 1; }
    .preset-add-form { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .preset-add-form input { padding: 0.5rem 0.8rem; border: 1px solid #444; border-radius: 8px; font-size: 0.85rem; background: #222; color: #f0ebe3; }
    .preset-add-form input::placeholder { color: #b8a89a; }
    .preset-add-form input:first-child { flex: 2; }
    .preset-add-form input:nth-child(2) { width: 80px; }
    .preset-add-form input:nth-child(3) { flex: 3; }
    .preset-add-form button { padding: 0.5rem 1rem; background: #c9a96e; color: #0c0c0c; border: none; border-radius: 20px; cursor: pointer; font-size: 0.85rem; font-weight: 500; }
    .preset-add-form button:hover { background: #b8945f; }
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

  <main class="admin-main">

    <div class="admin-header">
      <div>
        <h1>admin panel</h1>
        <p class="admin-welcome" id="adminWelcome">Welcome, <?= htmlspecialchars($adminDisplayName) ?></p>
      </div>
      <div style="display:flex;align-items:center;gap:1rem;">
        <div class="admin-avatar" id="adminAvatar">A</div>
        <div class="admin-badge">administrator access</div>
      </div>
    </div>

    <div class="admin-nav">
      <button class="admin-nav-btn active" data-tab="dashboard">📊 dashboard</button>
      <button class="admin-nav-btn" data-tab="products">📦 products</button>
      <button class="admin-nav-btn" data-tab="orders">📋 orders</button>
      <button class="admin-nav-btn" data-tab="users">👥 users</button>
      <button class="admin-nav-btn" data-tab="gst">🧾 GST</button>
      <button class="admin-nav-btn" data-tab="feedback">💬 Feedback</button>
      <button class="admin-nav-btn" data-tab="returns">↩ Returns</button>
      <button class="admin-nav-btn" data-tab="settings">⚙️ settings</button>
    </div>

    <!-- ── DASHBOARD TAB ── -->
    <div class="admin-tab active" id="dashboardTab">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">📦</div>
          <div class="stat-info">
            <span class="stat-value" id="totalProducts">—</span>
            <span class="stat-label">total products</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">📋</div>
          <div class="stat-info">
            <span class="stat-value" id="totalOrders">—</span>
            <span class="stat-label">total orders</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">💰</div>
          <div class="stat-info">
            <span class="stat-value" id="totalRevenue">—</span>
            <span class="stat-label">total revenue (incl. GST)</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">👥</div>
          <div class="stat-info">
            <span class="stat-value" id="totalUsers">—</span>
            <span class="stat-label">registered users</span>
          </div>
        </div>
      </div>

      <div class="dashboard-grid">
        <div class="dashboard-card">
          <h3>recent orders</h3>
          <div class="recent-orders-list" id="recentOrdersList">
            <p style="color:#b8a89a;padding:1rem;">Loading...</p>
          </div>
          <button class="view-all-btn" onclick="document.querySelector('[data-tab=orders]').click()">view all orders →</button>
        </div>
        <div class="dashboard-card">
          <h3>low stock products</h3>
          <div class="low-stock-list" id="lowStockList">
            <p style="color:#b8a89a;padding:1rem;">Loading...</p>
          </div>
          <button class="view-all-btn" onclick="document.querySelector('[data-tab=products]').click()">manage products →</button>
        </div>
      </div>
    </div>

    <!-- ── PRODUCTS TAB ── -->
    <div class="admin-tab" id="productsTab">
      <div class="tab-header">
        <h2>manage products</h2>
        <button class="add-btn" id="addProductBtn">+ add new product</button>
        <input type="date" id="exportProductsDate" class="search-input" style="max-width:180px;">
        <select id="exportProductsFormat" class="filter-select" style="max-width:140px;">
          <option value="csv">CSV</option>
          <option value="pdf">PDF</option>
        </select>
        <button class="add-btn" id="exportProductsBtn">download products report</button>
      </div>
      <div class="filters-bar">
        <input type="text" class="search-input" id="productSearch" placeholder="Search products...">
        <select class="filter-select" id="categoryFilter">
          <option value="all">all categories</option>
          <option value="essential">essential</option>
          <option value="denim">denim</option>
          <option value="outerwear">outerwear</option>
          <option value="linen">linen</option>
          <option value="accessories">accessories</option>
        </select>
      </div>
      <div class="products-table">
        <div class="table-header">
          <div>ID</div><div>Image</div><div>Name</div>
          <div>Category</div><div>Price (excl. GST)</div><div>GST %</div><div>Stock</div><div>Actions</div>
        </div>
        <div class="table-body" id="productsTableBody"></div>
      </div>
    </div>

    <!-- ── ORDERS TAB ── -->
    <div class="admin-tab" id="ordersTab">
      <div class="tab-header">
        <h2>manage orders</h2>
        <input type="date" id="exportOrdersDate" class="search-input" style="max-width:180px;">
        <select id="exportOrdersFormat" class="filter-select" style="max-width:140px;">
          <option value="csv">CSV</option>
          <option value="pdf">PDF</option>
        </select>
        <button class="add-btn" id="exportOrdersBtn">download orders report</button>
        <select class="status-filter" id="orderStatusFilter">
          <option value="all">all orders</option>
          <option value="pending">pending</option>
          <option value="processing">processing</option>
          <option value="shipped">shipped</option>
          <option value="delivered">delivered</option>
          <option value="cancelled">cancelled</option>
        </select>
      </div>
      <div class="orders-table">
        <div class="table-header">
          <div>Order #</div><div>Customer</div><div>Date</div>
          <div>Total (incl. GST)</div><div>Status</div><div>Actions</div>
        </div>
        <div class="table-body" id="ordersTableBody"></div>
      </div>
    </div>

    <!-- ── USERS TAB ── -->
    <div class="admin-tab" id="usersTab">
      <div class="tab-header">
        <h2>manage users</h2>
        <input type="date" id="exportUsersDate" class="search-input" style="max-width:180px;">
        <select id="exportUsersFormat" class="filter-select" style="max-width:140px;">
          <option value="csv">CSV</option>
          <option value="pdf">PDF</option>
        </select>
        <button class="add-btn" id="exportUsersBtn">download users report</button>
      </div>
      <div class="users-table">
        <div class="table-header">
          <div>ID</div><div>Name</div><div>Email</div>
          <div>Role</div><div>Joined</div><div>Actions</div>
        </div>
        <div class="table-body" id="usersTableBody"></div>
      </div>
    </div>

    <!-- ── GST TAB ── -->
    <div class="admin-tab" id="gstTab">
      <div class="tab-header">
        <h2>GST management</h2>
        <span style="font-size:0.85rem;color:#b8a89a;">Manage GST rates for all products. Prices shown are exclusive of GST.</span>
      </div>

      <!-- GST Summary by rate -->
      <div class="gst-summary-bar" id="gstSummaryBar">
        <p style="color:#b8a89a;">Loading...</p>
      </div>

      <!-- Bulk update -->
      <div class="gst-bulk-section">
        <h3>bulk update GST rate</h3>
        <select id="bulkGstCategory">
          <option value="all">all categories</option>
          <option value="essential">essential wear</option>
          <option value="denim">denim collection</option>
          <option value="outerwear">outerwear</option>
          <option value="linen">summer linens</option>
          <option value="accessories">accessories</option>
        </select>
        <div style="display:flex;align-items:center;gap:0.5rem;">
          <input type="number" id="bulkGstRate" min="0" max="100" step="0.01" placeholder="Rate %" style="width:100px;">
          <span style="color:#b8a89a;font-size:0.9rem;">%</span>
        </div>
        <button class="gst-apply-btn" id="applyBulkGstBtn">Apply to All</button>
      </div>

      <!-- Per-product GST table -->
      <div class="gst-products-table">
        <div class="gst-table-header">
          <div>Product</div>
          <div>Category</div>
          <div>Base Price (₹)</div>
          <div>GST %</div>
          <div>GST Amount (₹)</div>
          <div>Final Price (₹)</div>
          <div>Update</div>
        </div>
        <div id="gstProductsBody"></div>
      </div>

      <!-- GST rate presets -->
      <div class="gst-presets">
        <h3>GST rate presets</h3>
        <div class="preset-list" id="presetList"></div>
        <div class="preset-add-form">
          <input type="text" id="newPresetLabel" placeholder="Label (e.g. Luxury 28%)">
          <input type="number" id="newPresetRate" min="0" max="100" step="0.01" placeholder="Rate">
          <input type="text" id="newPresetDesc" placeholder="Description (optional)">
          <button id="addPresetBtn">+ add preset</button>
        </div>
      </div>
    </div>

    <!-- ── FEEDBACK TAB ── -->
    <div class="admin-tab" id="feedbackTab">
      <div class="tab-header">
        <h2>customer feedback</h2>
        <select id="feedbackStatusFilter" style="padding:0.5rem 1rem;border:1px solid #444;border-radius:8px;font-size:0.9rem;background:#222;color:#f0ebe3;">
          <option value="all">all feedback</option>
          <option value="new">new</option>
          <option value="read">read</option>
          <option value="replied">replied</option>
          <option value="resolved">resolved</option>
        </select>
      </div>
      <div id="feedbackTableBody"></div>
    </div>

    <!-- ── RETURNS TAB ── -->
    <div class="admin-tab" id="returnsTab">
      <div class="tab-header">
        <h2>return requests</h2>
        <select id="returnsStatusFilter" style="padding:0.5rem 1rem;border:1px solid #444;border-radius:8px;font-size:0.9rem;background:#222;color:#f0ebe3;">
          <option value="all">all returns</option>
          <option value="requested">requested</option>
          <option value="approved">approved</option>
          <option value="rejected">rejected</option>
          <option value="pickup_scheduled">pickup scheduled</option>
          <option value="received">received</option>
          <option value="refunded">refunded</option>
        </select>
      </div>
      <div id="returnsTableBody"></div>
    </div>

        <!-- ── SETTINGS TAB ── -->
    <div class="admin-tab" id="settingsTab">
      <h2>settings</h2>
      <div class="settings-grid">
        <div class="settings-card">
          <h3>general settings</h3>
          <div class="setting-item">
            <label>store name</label>
            <input type="text" id="storeName" value="VELORA">
          </div>
          <div class="setting-item">
            <label>store email</label>
            <input type="email" id="storeEmail" value="hello@velora.in">
          </div>
          <div class="setting-item">
            <label>currency</label>
            <select id="currency">
              <option value="INR" selected>INR (₹)</option>
            </select>
          </div>
          <button type="button" class="save-settings-btn" id="saveGeneralSettingsBtn">save settings</button>
        </div>

        <div class="settings-card">
          <h3>shipping settings</h3>
          <div class="setting-item">
            <label>free shipping above (₹)</label>
            <input type="number" id="freeShippingThreshold" value="5000">
          </div>
          <div class="setting-item">
            <label>standard shipping cost (₹)</label>
            <input type="number" id="shippingCostSetting" value="99">
          </div>
          <div class="setting-item">
            <label>COD extra charge (₹)</label>
            <input type="number" id="codCharge" value="50">
          </div>
          <button type="button" class="save-settings-btn" id="saveShippingSettingsBtn">save settings</button>
        </div>

        <div class="settings-card">
          <h3>delivery radius</h3>
          <div class="setting-item">
            <label>delivery scope</label>
            <select id="deliveryRadius">
              <option value="same-city">Same City Only</option>
              <option value="nearby-states">Nearby States Only</option>
              <option value="select-states">Select Specific States</option>
              <option value="all-india">All India</option>
              <option value="custom-pincodes">Custom PIN Codes</option>
            </select>
          </div>
          <div class="setting-item" id="selectStatesWrapper" style="display:none;">
            <label>select states for delivery</label>
            <div id="statesCheckboxList" style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;max-height:320px;overflow-y:auto;padding:0.75rem;background:#222;border-radius:8px;border:1px solid #444;">
              <!-- States will be populated by JS -->
            </div>
          </div>
          <div class="setting-item" id="customPincodesWrapper" style="display:none;">
            <label>serviceable PIN codes (comma-separated)</label>
            <textarea id="customPincodes" placeholder="e.g., 110001, 560001, 400001" style="width:100%;height:120px;padding:0.75rem;border:1px solid #444;border-radius:8px;background:#222;color:#f0ebe3;font-size:0.9rem;"></textarea>
          </div>
          <button class="save-settings-btn" onclick="window.saveDeliverySettings()">save delivery settings</button>
        </div>

        <div class="settings-card">
          <h3>admin users</h3>
          <p style="color:#b8a89a;font-size:0.9rem;margin-bottom:1rem;">
            To grant admin access to a user, run this in phpMyAdmin:
          </p>
          <code style="display:block;background:#222;padding:0.8rem;border-radius:8px;font-size:0.8rem;word-break:break-all;color:#c9a96e;border:1px solid #444;">
            UPDATE users SET is_admin = 1 WHERE email = 'user@email.com';
          </code>
        </div>
      </div>
    </div>

  </main>

  <!-- ── PRODUCT MODAL ── -->
  <div class="modal" id="productModal">
    <div class="modal-content large">
      <span class="close-modal" id="closeProductModal">&times;</span>
      <h2 id="productModalTitle">add new product</h2>
      <form id="productForm">
        <div class="form-row">
          <div class="form-group half">
            <label>product name</label>
            <input type="text" id="productName" required>
          </div>
          <div class="form-group half">
            <label>base price (₹, excl. GST)</label>
            <input type="number" id="productPrice" step="0.01" min="0" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group half">
            <label>category</label>
            <select id="productCategory" required>
              <option value="essential">essential</option>
              <option value="denim">denim</option>
              <option value="outerwear">outerwear</option>
              <option value="linen">linen</option>
              <option value="accessories">accessories</option>
            </select>
          </div>
          <div class="form-group half">
            <label>GST rate (%)</label>
            <input type="number" id="productGstRate" step="0.01" min="0" max="100" value="12" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group half">
            <label>subcategory</label>
            <input type="text" id="productSubcategory" placeholder="e.g. tees, jeans, coats">
          </div>
          <div class="form-group half">
            <label>badge (optional)</label>
            <input type="text" id="productBadge" placeholder="new, sale, best seller, premium">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group half">
            <label>image path</label>
            <input type="text" id="productImage" placeholder="/Images/Essential/product.jpg">
          </div>
          <div class="form-group half">
            <label>hover image path</label>
            <input type="text" id="productHoverImage" placeholder="/Images/Essential/product-hover.jpg">
          </div>
        </div>
        <div class="form-group">
          <label>product images</label>
          <div id="productImagesContainer" style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">
            <p style="color:#b8a89a;margin:1rem 0;">No images uploaded yet.</p>
          </div>
          <button type="button" id="uploadImageBtn" style="padding:0.5rem 1rem;background:#c9a96e;color:#0c0c0c;border:none;border-radius:20px;cursor:pointer;font-size:0.9rem;">+ upload images</button>
          <input type="file" id="imageUploadInput" multiple accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/avif,image/bmp,image/tiff,image/heic,image/heif,image/svg+xml" style="display:none;">
          <div id="productImagePreview" style="display:none;flex-wrap:wrap;gap:0.75rem;margin-top:1rem;"></div>
        </div>
        <div class="form-row">
          <div class="form-group half">
            <label>stock quantity</label>
            <input type="number" id="productStock" value="10" min="0">
          </div>
          <div class="form-group half">
            <label>color</label>
            <input type="text" id="productColor" placeholder="black, blue, gray">
          </div>
        </div>
        <div class="form-group">
          <label>sizes (comma separated)</label>
          <input type="text" id="productSizes" placeholder="XS, S, M, L, XL">
        </div>
        <button type="submit" class="submit-btn">save product</button>
      </form>
    </div>
  </div>

  <!-- ── ORDER DETAIL MODAL ── -->
  <div class="modal" id="orderModal">
    <div class="modal-content">
      <span class="close-modal" id="closeOrderModal">&times;</span>
      <h2>order details</h2>
      <div id="orderDetails"></div>
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

  <script src="../session.js"></script>
  <script src="../theme.js"></script>
  <script src="../nav.js"></script>
  <script src="admin.js"></script>
</body>
</html>