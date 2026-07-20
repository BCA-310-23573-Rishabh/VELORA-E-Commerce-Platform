// admin.js - Admin Panel

document.addEventListener('DOMContentLoaded', function () {

  // ── Safe fetch: never throws, always returns a data object ───────────
  async function apiCall(url, options = {}) {
    try {
      const res  = await fetch(url, { ...options, credentials: 'same-origin' });
      const text = await res.text();
      try {
        return JSON.parse(text);
      } catch (_) {
        console.error('Non-JSON from ' + url + ':', text.substring(0, 400));
        return { success: false, message: 'Server error. Visit http://localhost/velora/test.php to diagnose.' };
      }
    } catch (_) {
      return { success: false, message: 'Cannot connect. Ensure Apache and MySQL are running in XAMPP.' };
    }
  }

  // ── Check admin access ───────────────────────────────────────────────
  async function checkAdminAccess() {
    const data = await apiCall('../api/auth.php?action=check');

    if (!data.success && data.message.includes('connect')) {
      // Show a helpful error instead of silent redirect
      document.querySelector('.admin-main').innerHTML = `
        <div style="padding:3rem;text-align:center;">
          <h2 style="color:#cc0000;margin-bottom:1rem;">Cannot connect to server</h2>
          <p style="color:#666;margin-bottom:1rem;">Please ensure <strong>Apache</strong> and <strong>MySQL</strong> are both running (green) in XAMPP Control Panel.</p>
          <p style="color:#666;">Then open <a href="http://localhost/velora/test.php" target="_blank" style="color:#8b6f4c;">test.php</a> to verify your setup.</p>
          <br>
          <a href="index.php" style="padding:0.8rem 2rem;background:#1e1e1e;color:white;border-radius:40px;text-decoration:none;">go to homepage</a>
        </div>`;
      return false;
    }

    if (!data.loggedIn || !data.user || !data.user.isAdmin) {
      alert('Admin access required. Please log in with an admin account.');
      window.location.href = 'login.php';
      return false;
    }

    const el = document.getElementById('adminWelcome');
    if (el) el.textContent = `Welcome, ${data.user.firstName} ${data.user.lastName}`;
    
    const avatarEl = document.getElementById('adminAvatar');
    if (avatarEl && data.user) {
      const firstName = (data.user.firstName || 'A').charAt(0).toUpperCase();
      const lastName = (data.user.lastName || 'A').charAt(0).toUpperCase();
      const initials = firstName + lastName;
      avatarEl.textContent = initials;
      
      const nameHash = (firstName + lastName).split('').reduce((acc, char) => {
        return acc + char.charCodeAt(0);
      }, 0);
      
      const colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8',
        '#F7DC6F', '#BB8FCE', '#85C1E2', '#F8B88B', '#A8E6CF',
        '#FFD3B6', '#FFAAA5', '#FF8B94', '#A8D8EA', '#C9A96E'
      ];
      const color = colors[nameHash % colors.length];
      avatarEl.style.background = color;
    }
    return true;
  }

  let pendingImageFiles = [];

  // ── Tab navigation ───────────────────────────────────────────────────
  function setupTabs() {
    document.querySelectorAll('.admin-nav-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.admin-nav-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab + 'Tab')?.classList.add('active');

        switch (btn.dataset.tab) {
          case 'dashboard': loadDashboard(); break;
          case 'products':  loadProducts();  break;
          case 'orders':    loadOrders();    break;
          case 'users':     loadUsers();     break;
          case 'gst':       loadGst();       break;
          case 'feedback':  loadFeedback();  break;
          case 'returns':   loadReturns();   break;
        }
      });
    });
  }

  // ════════════════════════════════════════════════
  // DASHBOARD
  // ════════════════════════════════════════════════
  async function loadDashboard() {
    const data = await apiCall('../api/admin.php?action=stats');
    if (!data.success) { console.error(data.message); return; }

    document.getElementById('totalProducts').textContent = data.totalProducts;
    document.getElementById('totalOrders').textContent   = data.totalOrders;
    document.getElementById('totalRevenue').textContent  = `₹${parseFloat(data.totalRevenue).toLocaleString('en-IN', {minimumFractionDigits:2})}`;
    document.getElementById('totalUsers').textContent    = data.totalUsers;

    const recentList = document.getElementById('recentOrdersList');
    recentList.innerHTML = data.recentOrders.length
      ? data.recentOrders.map(o => `
          <div class="recent-order-item">
            <div class="order-info">
              <span class="order-number">${o.orderNumber}</span>
              <span class="order-customer">${o.customer?.name || 'Guest'}</span>
            </div>
            <div>
              <span class="order-total">₹${parseFloat(o.total).toFixed(2)}</span>
              <span class="order-status status-${o.status}">${o.status}</span>
            </div>
          </div>`).join('')
      : '<p style="color:#999;padding:1rem;">No orders yet.</p>';

    const lowStockList = document.getElementById('lowStockList');
    lowStockList.innerHTML = data.lowStock.length
      ? data.lowStock.map(p => `
          <div class="low-stock-item">
            <span class="product-name">${p.name}</span>
            <span class="product-stock">${p.stock} left</span>
          </div>`).join('')
      : '<p style="color:#999;padding:1rem;">All products are well-stocked.</p>';
  }

  // ════════════════════════════════════════════════
  // PRODUCTS
  // ════════════════════════════════════════════════
  async function loadProducts() {
    const search   = document.getElementById('productSearch')?.value.toLowerCase() || '';
    const category = document.getElementById('categoryFilter')?.value || 'all';
    let   url      = 'api/products.php?action=list';
    if (category !== 'all') url += `&category=${category}`;

    const data = await apiCall(url);
    if (!data.success) return;

    let products = data.products;
    if (search) products = products.filter(p => p.name.toLowerCase().includes(search));

    const tbody = document.getElementById('productsTableBody');
    if (!tbody) return;

    // Products table has 8 columns now (includes GST)
    tbody.innerHTML = products.length
      ? products.map(p => `
          <div class="table-row" style="grid-template-columns:80px 100px 2fr 1fr 1fr 80px 100px 120px;">
            <div>${p.id}</div>
            <div><div class="product-thumb" style="background-image:${p.image};"></div></div>
            <div>${p.name}</div>
            <div>${p.category}</div>
            <div>₹${parseFloat(p.price).toFixed(2)}</div>
            <div>${parseFloat(p.gstRate || 12).toFixed(0)}%</div>
            <div>${p.stock}</div>
            <div>
              <button class="action-btn edit-btn" onclick="editProduct(${p.id})">edit</button>
              <button class="action-btn delete-btn" onclick="deleteProduct(${p.id})">delete</button>
            </div>
          </div>`).join('')
      : '<p style="padding:1rem;color:#999;">No products found.</p>';

    // Update products table header to match 8 columns
    const thead = document.querySelector('#productsTab .table-header');
    if (thead) thead.style.gridTemplateColumns = '80px 100px 2fr 1fr 1fr 80px 100px 120px';
  }

  window.editProduct = async function (id) {
    const data = await apiCall(`api/products.php?action=get&id=${id}`);
    if (!data.success) return;
    const p = data.product;

    document.getElementById('productModalTitle').textContent = 'edit product';
    document.getElementById('productName').value        = p.name;
    document.getElementById('productPrice').value       = p.price;
    document.getElementById('productCategory').value   = p.category;
    document.getElementById('productGstRate').value     = p.gstRate || 12;
    document.getElementById('productSubcategory').value = p.subcategory || '';
    document.getElementById('productBadge').value       = p.badge || '';
    document.getElementById('productStock').value       = p.stock;
    document.getElementById('productSizes').value       = Array.isArray(p.size) ? p.size.join(', ') : '';
    document.getElementById('productColor').value       = p.color || '';

    // Handle images
    const images = p.images || [];
    displayProductImages(images);

    document.getElementById('productForm').dataset.productId = id;
    document.getElementById('productModal').style.display    = 'block';
  };

  window.deleteProduct = async function (id) {
    if (!confirm('Delete this product? This cannot be undone.')) return;
    const data = await apiCall(`api/products.php?action=delete&id=${id}`);
    if (data.success) { loadProducts(); loadDashboard(); }
    else alert(data.message || 'Delete failed.');
  };

  function setupProductModal() {
    const modal    = document.getElementById('productModal');
    const closeBtn = document.getElementById('closeProductModal');
    const addBtn   = document.getElementById('addProductBtn');
    const form     = document.getElementById('productForm');

    addBtn?.addEventListener('click', () => {
      document.getElementById('productModalTitle').textContent = 'add new product';
      form.reset();
      document.getElementById('productGstRate').value = '12';
      document.getElementById('productStock').value   = '10';
      delete form.dataset.productId;
      displayProductImages([]); // Clear images
      clearImagePreview();
      modal.style.display = 'block';
    });

    closeBtn?.addEventListener('click', () => { modal.style.display = 'none'; });
    window.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

    // Setup image upload
    setupImageUpload();

    form?.addEventListener('submit', async function (e) {
      e.preventDefault();
      const gstRate = parseFloat(document.getElementById('productGstRate').value);
      if (isNaN(gstRate) || gstRate < 0 || gstRate > 100) {
        alert('GST rate must be between 0 and 100.');
        return;
      }

      // Get images from the image manager
      const images = getProductImages();

      const payload = {
        name:        document.getElementById('productName').value.trim(),
        price:       document.getElementById('productPrice').value,
        gstRate:     gstRate,
        category:    document.getElementById('productCategory').value,
        subcategory: document.getElementById('productSubcategory').value.trim(),
        images:      images,
        hoverImages: images, // For now, use same images for hover
        badge:       document.getElementById('productBadge').value.trim(),
        stock:       document.getElementById('productStock').value,
        sizes:       document.getElementById('productSizes').value.trim(),
        color:       document.getElementById('productColor').value.trim()
      };

      console.log('Payload being sent:', payload);

      if (!payload.name || !payload.price) { alert('Product name and price are required.'); return; }

      const isEdit = !!form.dataset.productId;
      const url    = isEdit
        ? `api/products.php?action=update&id=${form.dataset.productId}`
        : 'api/products.php?action=add';

      const data = await apiCall(url, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
      });

      if (data.success) {
        alert('Product saved successfully.');
        modal.style.display = 'none';
        loadProducts(); loadDashboard();
      } else {
        alert(data.message || 'Save failed.');
      }
    });
  }

  // ── Image Management Functions ──────────────────────────────────────
  function setupImageUpload() {
    const uploadBtn = document.getElementById('uploadImageBtn');
    const imageInput = document.getElementById('imageUploadInput');

    function handleImageInputChange(e) {
      const files = Array.from(e.target.files || []);
      if (files.length === 0) return;
      pendingImageFiles = files;
      renderImagePreview(files);
    }

    uploadBtn?.addEventListener('click', () => {
      imageInput?.click();
    });

    if (imageInput) {
      imageInput.addEventListener('change', handleImageInputChange);
    }
  }

  function renderImagePreview(files) {
    const previewContainer = document.getElementById('productImagePreview');
    if (!previewContainer) return;

    const category = document.getElementById('productCategory')?.value || 'misc';

    previewContainer.style.display = 'flex';
    previewContainer.innerHTML = `
      <div style="display:flex;flex-direction:column;gap:0.75rem;width:100%;background:#121212;border:1px solid #333;padding:0.75rem;border-radius:12px;">
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;">
          ${files.map(file => `
            <div style="width:80px;height:80px;border:1px solid #333;border-radius:8px;overflow:hidden;background:#000;">
              <img src="${URL.createObjectURL(file)}" alt="${file.name}" style="width:100%;height:100%;object-fit:cover;">
            </div>
          `).join('')}
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
          <div style="flex:1;min-width:180px;">
            <strong style="display:block;margin-bottom:0.4rem;color:#fff;">Preview selected ${files.length === 1 ? 'image' : `${files.length} images`}</strong>
            <span style="display:block;color:#b8a89a;font-size:0.9rem;">${files.map(file => file.name).join(', ')}</span>
            <span style="display:block;color:#b8a89a;font-size:0.9rem;margin-top:0.25rem;">Category: ${category}</span>
          </div>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <button type="button" id="confirmUploadBtn" style="padding:0.55rem 0.95rem;background:#c9a96e;color:#0c0c0c;border:none;border-radius:20px;cursor:pointer;font-size:0.9rem;">Upload</button>
            <button type="button" id="cancelUploadBtn" style="padding:0.55rem 0.95rem;background:#333;color:#fff;border:none;border-radius:20px;cursor:pointer;font-size:0.9rem;">Cancel</button>
          </div>
        </div>
      </div>`;

    document.getElementById('confirmUploadBtn')?.addEventListener('click', uploadSelectedImage);
    document.getElementById('cancelUploadBtn')?.addEventListener('click', clearImagePreview);
  }

  function clearImagePreview() {
    pendingImageFiles = [];
    const previewContainer = document.getElementById('productImagePreview');
    if (previewContainer) {
      previewContainer.style.display = 'none';
      previewContainer.innerHTML = '';
    }
    const imageInput = document.getElementById('imageUploadInput');
    if (imageInput) {
      imageInput.value = '';
    }
  }

  async function uploadSelectedImage() {
    if (!pendingImageFiles.length) return;

    const uploadButton = document.getElementById('confirmUploadBtn');
    if (uploadButton) {
      uploadButton.textContent = 'Uploading...';
      uploadButton.disabled = true;
    }

    const category = document.getElementById('productCategory')?.value || 'misc';
    const errors = [];

    for (const file of pendingImageFiles) {
      const formData = new FormData();
      formData.append('image', file);
      formData.append('category', category);

      try {
        const response = await fetch('../api/products.php?action=upload_image', {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });
        const data = await response.json();

        if (data.success) {
          addProductImage(data.imagePath);
        } else {
          errors.push(`${file.name}: ${data.message || 'Upload failed'}`);
        }
      } catch (error) {
        console.error('Upload error:', error);
        errors.push(`${file.name}: ${error.message || 'Unknown error'}`);
      }
    }

    if (uploadButton) {
      uploadButton.disabled = false;
      uploadButton.textContent = 'Upload';
    }

    if (errors.length) {
      alert('Some files failed to upload:\n' + errors.join('\n'));
    }

    clearImagePreview();
  }

  function displayProductImages(images) {

  const container = document.getElementById('productImagesContainer');

  if (!container) return;

  container.innerHTML = '';

  if (images.length === 0) {

    container.innerHTML =
      '<p style="color:#b8a89a;margin:1rem 0;">No images uploaded yet.</p>';

    return;
  }

  images.forEach((imagePath, index) => {

    const imageItem = document.createElement('div');

    imageItem.className = 'product-image-item';

    imageItem.innerHTML = `
      <img 
        src="${imagePath}" 
        data-path="${imagePath}"
        alt="Product image ${index + 1}" 
        style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #333;"
      >

      <button 
        type="button" 
        class="remove-image-btn"
        onclick="removeProductImage('${imagePath}')"
        style="background:#ff4444;color:white;border:none;border-radius:50%;width:20px;height:20px;font-size:12px;cursor:pointer;margin-left:0.5rem;"
      >
        ×
      </button>
    `;

    container.appendChild(imageItem);
  });
}

  function addProductImage(imagePath) {

  const currentImages = getProductImages();

  currentImages.push(imagePath);

  displayProductImages(currentImages);
}

  window.removeProductImage = function(imagePath) {
    if (!confirm('Delete this image? This will permanently remove the file.')) return;

    // Delete from server
    fetch('../api/products.php?action=delete_image', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `imagePath=${encodeURIComponent(imagePath)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Remove from display
        const currentImages = getProductImages();
        const filtered = currentImages.filter(img => img !== imagePath);
        displayProductImages(filtered);
      } else {
        alert('Failed to delete image: ' + data.message);
      }
    })
    .catch(error => {
      alert('Delete error: ' + error.message);
    });
  };

  function getProductImages() {

  const container = document.getElementById('productImagesContainer');

  if (!container) return [];

  const images = [];

  container.querySelectorAll('.product-image-item img').forEach(img => {

    const imagePath = img.getAttribute('data-path');

    if (imagePath) {
      images.push(imagePath);
    }
  });

  return images;
}

  // ════════════════════════════════════════════════
  // ORDERS
  // ════════════════════════════════════════════════
  async function loadOrders() {
    const filter = document.getElementById('orderStatusFilter')?.value || 'all';
    const url    = filter === 'all' ? '../api/orders.php?action=list' : `../api/orders.php?action=list&status=${filter}`;
    const data   = await apiCall(url);
    if (!data.success) return;

    const tbody = document.getElementById('ordersTableBody');
    if (!tbody) return;

    tbody.innerHTML = data.orders.length
      ? data.orders.map(o => `
          <div class="table-row">
            <div>${o.orderNumber}</div>
            <div>${o.customer?.name || 'Guest'}</div>
            <div>${o.date}</div>
            <div>₹${parseFloat(o.total).toFixed(2)}</div>
            <div><span class="status-badge status-${o.status}">${o.status}</span></div>
            <div>
              <button class="action-btn view-btn" onclick="viewOrder('${o.orderNumber}')">view</button>
              <select style="padding:0.3rem;border-radius:8px;border:1px solid #ddd;font-size:0.8rem;"
                      onchange="updateOrderStatus('${o.orderNumber}', this.value)">
                ${['pending','processing','shipped','delivered','cancelled']
                    .map(s => `<option value="${s}" ${o.status===s?'selected':''}>${s}</option>`).join('')}
              </select>
            </div>
          </div>`).join('')
      : '<p style="padding:1rem;color:#999;">No orders found.</p>';
  }

  window.viewOrder = async function (orderNumber) {
    const data = await apiCall(`../api/orders.php?action=get&order_number=${orderNumber}`);
    if (!data.success) { alert('Order not found.'); return; }
    const o = data.order;

    document.getElementById('orderDetails').innerHTML = `
      <div style="line-height:1.8;">
        <p><strong>Order:</strong> ${o.orderNumber}</p>
        <p><strong>Date:</strong> ${o.date} &nbsp;|&nbsp; <strong>Status:</strong> ${o.status}</p>
        <p><strong>Customer:</strong> ${o.customer?.name} | ${o.customer?.email} | ${o.customer?.phone || '—'}</p>
        <p><strong>Address:</strong> ${o.shipping?.address}, ${o.shipping?.city}, ${o.shipping?.state} ${o.shipping?.pincode}, ${o.shipping?.country}</p>
        <hr style="margin:1rem 0;border-color:#f0f0f0;">
        <h4 style="margin-bottom:0.5rem;">Items</h4>
        ${o.items?.map(i => `
          <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid #f5f5f5;">
            <span>${i.name} × ${i.quantity}${i.size ? ' (' + i.size + ')' : ''}</span>
            <span>₹${parseFloat(i.total).toFixed(2)}</span>
          </div>`).join('') || '—'}
        <div style="margin-top:1rem;text-align:right;">
          <p style="color:#666;">Subtotal: ₹${parseFloat(o.subtotal).toFixed(2)}</p>
          ${o.gstAmount > 0 ? `<p style="color:#666;">GST: ₹${parseFloat(o.gstAmount).toFixed(2)}</p>` : ''}
          ${o.discount  > 0 ? `<p style="color:#27ae60;">Discount: -₹${parseFloat(o.discount).toFixed(2)}</p>` : ''}
          <p style="color:#666;">Shipping: ${o.shipping === 0 ? 'FREE' : '₹' + parseFloat(o.shipping).toFixed(2)}</p>
          <p style="font-weight:700;font-size:1.1rem;margin-top:0.5rem;">Total: ₹${parseFloat(o.total).toFixed(2)}</p>
        </div>
      </div>`;

    document.getElementById('orderModal').style.display = 'block';
  };

  window.updateOrderStatus = async function (orderNumber, status) {
    const data = await apiCall('../api/orders.php?action=update_status', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orderNumber, status })
    });
    if (!data.success) alert(data.message || 'Status update failed.');
    else loadDashboard();
  };

  // ════════════════════════════════════════════════
  // USERS
  // ════════════════════════════════════════════════
  async function loadUsers() {
    const data = await apiCall('../api/admin.php?action=users');
    if (!data.success) return;

    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;

    tbody.innerHTML = data.users.map(u => `
      <div class="table-row">
        <div>${u.id}</div>
        <div>${u.firstName} ${u.lastName}</div>
        <div>${u.email}</div>
        <div><span class="status-badge" style="background:${u.isAdmin?'#d4edda':'#f0f0f0'};color:${u.isAdmin?'#155724':'#333'};">${u.isAdmin ? 'Admin' : 'Customer'}</span></div>
        <div>${new Date(u.createdAt).toLocaleDateString('en-IN')}</div>
        <div>
          ${!u.isAdmin ? `<button class="action-btn edit-btn" onclick="makeAdmin(${u.id})">make admin</button>` : ''}
          <button class="action-btn delete-btn" onclick="deleteUser(${u.id})">delete</button>
        </div>
      </div>`).join('');
  }

  window.makeAdmin = async function (userId) {
    if (!confirm('Grant admin access to this user?')) return;
    const data = await apiCall('../api/admin.php?action=make_admin', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ userId })
    });
    if (data.success) loadUsers();
    else alert(data.message || 'Failed.');
  };

  window.deleteUser = async function (userId) {
    if (!confirm('Permanently delete this user?')) return;
    const data = await apiCall('../api/admin.php?action=delete_user', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ userId })
    });
    if (data.success) { loadUsers(); loadDashboard(); }
    else alert(data.message || 'Failed.');
  };

  // ════════════════════════════════════════════════
  // GST MANAGEMENT
  // ════════════════════════════════════════════════
  async function loadGst() {
    await Promise.all([loadGstProducts(), loadGstPresets()]);
  }

  async function loadGstProducts() {
    const data = await apiCall('../api/gst.php?action=get_product_gst');
    if (!data.success) { console.error(data.message); return; }

    const products = data.products;

    // Build summary bar grouped by GST rate
    const grouped = {};
    products.forEach(p => {
      const rate = parseFloat(p.gstRate).toFixed(0) + '%';
      if (!grouped[rate]) grouped[rate] = 0;
      grouped[rate]++;
    });

    const summaryBar = document.getElementById('gstSummaryBar');
    summaryBar.innerHTML = Object.entries(grouped).map(([rate, count]) => `
      <div class="gst-summary-card" onclick="filterGstByRate('${rate}')">
        <div class="gst-rate-pill">${rate}</div>
        <div class="gst-card-label">GST Rate</div>
        <div class="gst-card-count">${count} product${count > 1 ? 's' : ''}</div>
      </div>`).join('');

    // Build products table
    const body = document.getElementById('gstProductsBody');
    body.innerHTML = products.map(p => `
      <div class="gst-table-row" data-gst-rate="${p.gstRate}">
        <div><strong>${p.name}</strong></div>
        <div><span class="category-badge">${p.category}</span></div>
        <div>₹${parseFloat(p.basePrice).toFixed(2)}</div>
        <div>
          <input class="gst-input-inline" type="number" min="0" max="100" step="0.01"
                 value="${parseFloat(p.gstRate).toFixed(2)}" id="gst-input-${p.id}">%
        </div>
        <div id="gst-amt-${p.id}">₹${parseFloat(p.gstAmount).toFixed(2)}</div>
        <div id="gst-final-${p.id}">₹${parseFloat(p.priceWithGst).toFixed(2)}</div>
        <div>
          <button class="gst-save-row-btn" onclick="saveProductGst(${p.id}, ${p.basePrice})">save</button>
        </div>
      </div>`).join('');

    // Live preview as user types
    products.forEach(p => {
      const input = document.getElementById(`gst-input-${p.id}`);
      if (!input) return;
      input.addEventListener('input', function () {
        const rate  = parseFloat(this.value) || 0;
        const base  = p.basePrice;
        const gstAmt = (base * rate / 100).toFixed(2);
        const final  = (base + parseFloat(gstAmt)).toFixed(2);
        document.getElementById(`gst-amt-${p.id}`).textContent   = `₹${gstAmt}`;
        document.getElementById(`gst-final-${p.id}`).textContent = `₹${final}`;
      });
    });
  }

  window.filterGstByRate = function (rate) {
    const rows = document.querySelectorAll('.gst-table-row');
    const rateNum = parseFloat(rate);
    rows.forEach(row => {
      const rowRate = parseFloat(row.dataset.gstRate);
      row.style.display = (Math.round(rowRate) === Math.round(rateNum)) ? '' : 'none';
    });

    // Add "show all" button
    let showAllBtn = document.getElementById('gstShowAllBtn');
    if (!showAllBtn) {
      showAllBtn = document.createElement('button');
      showAllBtn.id = 'gstShowAllBtn';
      showAllBtn.textContent = 'Show all products';
      showAllBtn.style.cssText = 'margin:1rem 1.5rem;padding:0.5rem 1rem;background:transparent;border:1px solid #ddd;border-radius:20px;cursor:pointer;';
      showAllBtn.onclick = () => {
        rows.forEach(r => r.style.display = '');
        showAllBtn.remove();
      };
      document.getElementById('gstProductsBody').before(showAllBtn);
    }
  };

  window.saveProductGst = async function (productId, basePrice) {
    const input   = document.getElementById(`gst-input-${productId}`);
    const gstRate = parseFloat(input?.value);
    if (isNaN(gstRate) || gstRate < 0 || gstRate > 100) {
      alert('GST rate must be between 0 and 100.'); return;
    }

    const data = await apiCall('../api/gst.php?action=update_product', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: productId, gstRate })
    });

    if (data.success) {
      // Flash green on the row
      const row = input?.closest('.gst-table-row');
      if (row) { row.style.background = '#d1e7dd'; setTimeout(() => { row.style.background = ''; }, 1200); }
    } else {
      alert(data.message || 'Failed to update GST rate.');
    }
  };

  // Bulk update
  document.getElementById('applyBulkGstBtn')?.addEventListener('click', async () => {
    const category = document.getElementById('bulkGstCategory').value;
    const rate     = parseFloat(document.getElementById('bulkGstRate').value);
    if (isNaN(rate) || rate < 0 || rate > 100) { alert('Please enter a valid GST rate (0–100).'); return; }

    const catLabel = category === 'all' ? 'ALL products' : category;
    if (!confirm(`Apply ${rate}% GST to ${catLabel}?`)) return;

    const data = await apiCall('../api/gst.php?action=bulk_update', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ category, gstRate: rate })
    });

    alert(data.message || (data.success ? 'Updated.' : 'Failed.'));
    if (data.success) loadGstProducts();
  });

  // GST presets
  async function loadGstPresets() {
    const data = await apiCall('../api/gst.php?action=list_rates');
    if (!data.success) return;

    const list = document.getElementById('presetList');
    list.innerHTML = data.rates.map(r => `
      <span class="preset-chip">
        <strong>${r.rate}%</strong> — ${r.label}
        <button onclick="deletePreset(${r.id})" title="Delete preset">×</button>
      </span>`).join('');
  }

  window.deletePreset = async function (id) {
    if (!confirm('Delete this GST preset?')) return;
    const data = await apiCall('../api/gst.php?action=delete_rate', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
    });
    if (data.success) loadGstPresets();
  };

  document.getElementById('addPresetBtn')?.addEventListener('click', async () => {
    const label = document.getElementById('newPresetLabel').value.trim();
    const rate  = parseFloat(document.getElementById('newPresetRate').value);
    const desc  = document.getElementById('newPresetDesc').value.trim();

    if (!label || isNaN(rate) || rate < 0 || rate > 100) {
      alert('Please enter a label and a valid rate (0–100).'); return;
    }

    const data = await apiCall('../api/gst.php?action=add_rate', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ label, rate, description: desc })
    });

    if (data.success) {
      document.getElementById('newPresetLabel').value = '';
      document.getElementById('newPresetRate').value  = '';
      document.getElementById('newPresetDesc').value  = '';
      loadGstPresets();
    } else {
      alert(data.message || 'Failed to add preset.');
    }
  });

  // ════════════════════════════════════════════════
  // MODALS SETUP
  // ════════════════════════════════════════════════
  function setupModals() {
    const orderModal  = document.getElementById('orderModal');
    document.getElementById('closeOrderModal')?.addEventListener('click', () => {
      orderModal.style.display = 'none';
    });
    window.addEventListener('click', e => {
      if (e.target === orderModal) orderModal.style.display = 'none';
    });
  }

  // ════════════════════════════════════════════════
  // FILTERS
  // ════════════════════════════════════════════════
  function setupFilters() {
    document.getElementById('productSearch')?.addEventListener('input', loadProducts);
    document.getElementById('categoryFilter')?.addEventListener('change', loadProducts);
    document.getElementById('orderStatusFilter')?.addEventListener('change', loadOrders);
    document.getElementById('feedbackStatusFilter')?.addEventListener('change', loadFeedback);
    document.getElementById('returnsStatusFilter')?.addEventListener('change', loadReturns);
  }

  function setupReportButtons() {
    const reportButtons = [
      { id: 'exportProductsBtn', type: 'products' },
      { id: 'exportOrdersBtn', type: 'orders' },
      { id: 'exportUsersBtn', type: 'users' }
    ];

    reportButtons.forEach(button => {
      const el = document.getElementById(button.id);
      if (!el) return;
      el.addEventListener('click', () => downloadReport(button.type));
    });

    const dateInputs = ['exportProductsDate', 'exportOrdersDate', 'exportUsersDate'];
    dateInputs.forEach(id => {
      const input = document.getElementById(id);
      if (!input) return;
      if (!input.value) input.value = getTodayDateString();
    });
  }

  function getTodayDateString() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function downloadReport(type) {
    const allowed = ['products', 'orders', 'users'];
    if (!allowed.includes(type)) return;

    const dateInputMap = {
      products: 'exportProductsDate',
      orders: 'exportOrdersDate',
      users: 'exportUsersDate'
    };
    const formatInputMap = {
      products: 'exportProductsFormat',
      orders: 'exportOrdersFormat',
      users: 'exportUsersFormat'
    };

    const selectedDate = document.getElementById(dateInputMap[type])?.value || getTodayDateString();
    const selectedFormat = document.getElementById(formatInputMap[type])?.value || 'csv';
    const url = `api/report.php?type=${encodeURIComponent(type)}&date=${encodeURIComponent(selectedDate)}&format=${encodeURIComponent(selectedFormat)}`;
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
  }

  function setupHeaderSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.querySelector('.search-btn');
    if (!searchInput || !searchBtn) return;

    const doSearch = () => {
      const query = searchInput.value.trim();
      if (query) window.location.href = `../user/shop.php?q=${encodeURIComponent(query)}`;
    };

    searchBtn.addEventListener('click', doSearch);
    searchInput.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') doSearch();
    });
  }

  function loadAdminSettings() {
    const storeName = localStorage.getItem('veloraStoreName');
    const storeEmail = localStorage.getItem('veloraStoreEmail');
    const currency = localStorage.getItem('veloraCurrency');
    const freeShipping = localStorage.getItem('veloraFreeShippingThreshold');
    const shippingCost = localStorage.getItem('veloraShippingCostSetting');
    const codCharge = localStorage.getItem('veloraCodCharge');

    if (storeName) document.getElementById('storeName').value = storeName;
    if (storeEmail) document.getElementById('storeEmail').value = storeEmail;
    if (currency) document.getElementById('currency').value = currency;
    if (freeShipping) document.getElementById('freeShippingThreshold').value = freeShipping;
    if (shippingCost) document.getElementById('shippingCostSetting').value = shippingCost;
    if (codCharge) document.getElementById('codCharge').value = codCharge;

    document.getElementById('saveGeneralSettingsBtn')?.addEventListener('click', saveGeneralSettings);
    document.getElementById('saveShippingSettingsBtn')?.addEventListener('click', saveShippingSettings);
  }

  function saveGeneralSettings() {
    const name = document.getElementById('storeName')?.value.trim();
    const email = document.getElementById('storeEmail')?.value.trim();
    const currency = document.getElementById('currency')?.value;
    if (!name || !email) {
      alert('Please enter both store name and email.');
      return;
    }
    localStorage.setItem('veloraStoreName', name);
    localStorage.setItem('veloraStoreEmail', email);
    localStorage.setItem('veloraCurrency', currency || 'INR');
    alert('Store settings saved successfully.');
  }

  function saveShippingSettings() {
    const freeShipping = document.getElementById('freeShippingThreshold')?.value;
    const shippingCost = document.getElementById('shippingCostSetting')?.value;
    const codCharge = document.getElementById('codCharge')?.value;
    if (isNaN(Number(freeShipping)) || isNaN(Number(shippingCost)) || isNaN(Number(codCharge))) {
      alert('Please enter valid numeric shipping settings.');
      return;
    }
    localStorage.setItem('veloraFreeShippingThreshold', freeShipping);
    localStorage.setItem('veloraShippingCostSetting', shippingCost);
    localStorage.setItem('veloraCodCharge', codCharge);
    alert('Shipping settings saved successfully.');
  }

  // ════════════════════════════════════════════════
  // FEEDBACK
  // ════════════════════════════════════════════════
  async function loadFeedback() {
    const status  = document.getElementById('feedbackStatusFilter')?.value || 'all';
    const url     = status === 'all'
      ? 'api/feedback.php?action=list'
      : `api/feedback.php?action=list&status=${status}`;
    const data    = await apiCall(url);
    const tbody   = document.getElementById('feedbackTableBody');
    if (!data.success || !tbody) return;

    if (!data.feedback.length) {
      tbody.innerHTML = '<p style="padding:1rem;color:#b8a89a;">No feedback found.</p>';
      return;
    }

    tbody.innerHTML = data.feedback.map(f => `
      <div style="background:#1a1a1a;border:1px solid #333;border-radius:14px;padding:1.2rem 1.5rem;margin-bottom:0.8rem;box-shadow:0 2px 8px -2px rgba(0,0,0,0.4);display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:start;">
        <div>
          <div style="display:flex;align-items:center;gap:0.8rem;margin-bottom:0.4rem;">
            <strong style="font-size:0.95rem;color:#f0ebe3;">${f.name}</strong>
            <span style="font-size:0.75rem;color:#b8a89a;">${f.email}</span>
            <span style="font-size:0.75rem;padding:0.2rem 0.6rem;border-radius:10px;background:#2a2a2a;color:#c9a96e;text-transform:capitalize;border:1px solid #444;">${f.type}</span>
            ${f.rating ? '<span style="color:#c9a96e;">' + '★'.repeat(f.rating) + '</span>' : ''}
          </div>
          <div style="font-size:0.85rem;color:#f0ebe3;font-weight:600;margin-bottom:0.3rem;">${f.subject || '(no subject)'}</div>
          <div style="font-size:0.85rem;color:#b8a89a;line-height:1.5;">${f.message.length > 150 ? f.message.substring(0,150) + '...' : f.message}</div>
          ${f.adminReply ? `<div style="margin-top:0.6rem;padding:0.6rem 0.8rem;background:#2a2420;border-radius:8px;font-size:0.8rem;color:#c9a96e;border:1px solid #444;"><strong>Your reply:</strong> ${f.adminReply}</div>` : ''}
          <div style="font-size:0.75rem;color:#666;margin-top:0.5rem;">${f.date}</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.4rem;align-items:flex-end;">
          <span style="padding:0.25rem 0.7rem;border-radius:20px;font-size:0.75rem;font-weight:600;text-transform:uppercase;
            background:${f.status==='new'?'#4a4620':f.status==='replied'?'#1b4620':f.status==='resolved'?'#0d5c2e':'#2a2a2a'};
            color:${f.status==='new'?'#ffeb3b':f.status==='replied'?'#81c784':f.status==='resolved'?'#66bb6a':'#b8a89a'};">
            ${f.status}
          </span>
          <button onclick="openFeedbackReply(${JSON.stringify(f).replace(/"/g, '&quot;')})"
            style="padding:0.3rem 0.8rem;background:#c9a96e;color:#0c0c0c;border:none;border-radius:20px;font-size:0.78rem;cursor:pointer;font-weight:500;">
            reply
          </button>
          <button onclick="resolveFeedback(${f.id})"
            style="padding:0.3rem 0.8rem;background:transparent;color:#b8a89a;border:1px solid #444;border-radius:20px;font-size:0.78rem;cursor:pointer;">
            resolve
          </button>
          <button onclick="deleteFeedbackItem(${f.id})"
            style="padding:0.3rem 0.8rem;background:transparent;color:#ff6b6b;border:1px solid #6b3535;border-radius:20px;font-size:0.78rem;cursor:pointer;">
            delete
          </button>
        </div>
      </div>`).join('');
  }

  window.openFeedbackReply = function(f) {
    const existing = document.getElementById('feedbackReplyModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id        = 'feedbackReplyModal';
    modal.className = 'modal';
    modal.style.display = 'block';
    modal.innerHTML = `
      <div class="modal-content" style="max-width:560px;background:#1a1a1a;border:1px solid #333;">
        <span class="close-modal" onclick="document.getElementById('feedbackReplyModal').remove()">&times;</span>
        <h2 style="font-size:1.2rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:1.5rem;color:#f0ebe3;">Reply to Feedback</h2>
        <p style="font-size:0.85rem;color:#b8a89a;margin-bottom:0.3rem;"><strong>${f.name}</strong> · ${f.email}</p>
        <p style="font-size:0.9rem;color:#f0ebe3;margin-bottom:1.2rem;padding:0.8rem;background:#222;border-radius:8px;border:1px solid #444;">${f.message}</p>
        <label style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;color:#c9a96e;font-weight:600;display:block;margin-bottom:0.4rem;">Your Reply</label>
        <textarea id="fbReplyText" style="width:100%;padding:0.8rem;border:1px solid #444;border-radius:8px;font-size:0.9rem;resize:vertical;min-height:100px;background:#222;color:#f0ebe3;">${f.adminReply || ''}</textarea>
        <button onclick="submitFeedbackReply(${f.id})"
          style="margin-top:1rem;padding:0.8rem 2rem;background:#c9a96e;color:#0c0c0c;border:none;border-radius:40px;cursor:pointer;font-weight:500;">
          Send Reply
        </button>
        <span id="fbReplySaveMsg" style="margin-left:1rem;font-size:0.85rem;"></span>
      </div>`;
    document.body.appendChild(modal);
    window.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
  };

  window.submitFeedbackReply = async function(id) {
    const reply = document.getElementById('fbReplyText')?.value.trim();
    const msgEl = document.getElementById('fbReplySaveMsg');
    if (!reply) { if (msgEl) { msgEl.style.color='#cc0000'; msgEl.textContent='Reply cannot be empty.'; } return; }
    const data = await apiCall('../api/feedback.php?action=reply', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, reply })
    });
    if (msgEl) { msgEl.style.color = data.success ? '#81c784' : '#ff6b6b'; msgEl.textContent = data.success ? '✓ Reply saved' : data.message; }
    if (data.success) { setTimeout(() => { document.getElementById('feedbackReplyModal')?.remove(); loadFeedback(); }, 1200); }
  };

  window.resolveFeedback = async function(id) {
    await apiCall('../api/feedback.php?action=update', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id, status: 'resolved' }) });
    loadFeedback();
  };

  window.deleteFeedbackItem = async function(id) {
    if (!confirm('Delete this feedback permanently?')) return;
    await apiCall('../api/feedback.php?action=delete', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id }) });
    loadFeedback();
  };

  // ════════════════════════════════════════════════
  // RETURNS
  // ════════════════════════════════════════════════
  async function loadReturns() {
    const status = document.getElementById('returnsStatusFilter')?.value || 'all';
    const url    = status === 'all' ? 'api/returns.php?action=list' : `api/returns.php?action=list&status=${status}`;
    const data   = await apiCall(url);
    const tbody  = document.getElementById('returnsTableBody');
    if (!data.success || !tbody) return;

    if (!data.returns.length) {
      tbody.innerHTML = '<p style="padding:1rem;color:#b8a89a;">No return requests found.</p>';
      return;
    }

    tbody.innerHTML = data.returns.map(r => `
      <div style="background:#1a1a1a;border:1px solid #333;border-radius:14px;padding:1.2rem 1.5rem;margin-bottom:0.8rem;box-shadow:0 2px 8px -2px rgba(0,0,0,0.4);">
        <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:1rem;">
          <div>
            <div style="display:flex;align-items:center;gap:0.8rem;margin-bottom:0.4rem;">
              <strong style="color:#f0ebe3;">${r.return_number}</strong>
              <span style="font-size:0.8rem;color:#b8a89a;">Order: ${r.order_number}</span>
            </div>
            <div style="font-size:0.85rem;color:#b8a89a;">
              <strong style="color:#f0ebe3;">${r.first_name} ${r.last_name}</strong> · ${r.email}<br>
              Reason: <em>${r.reason.replace(/_/g,' ')}</em>
              ${r.description ? ' · ' + r.description.substring(0,80) + (r.description.length>80?'...':'') : ''}
            </div>
            <div style="font-size:0.75rem;color:#666;margin-top:0.4rem;">${new Date(r.requested_at).toLocaleDateString('en-IN')}</div>
          </div>
          <div style="display:flex;flex-direction:column;gap:0.5rem;align-items:flex-end;">
            <span style="padding:0.25rem 0.7rem;border-radius:20px;font-size:0.75rem;font-weight:600;text-transform:uppercase;background:#2a2a2a;color:#c9a96e;border:1px solid #444;">
              ${r.status.replace(/_/g,' ')}
            </span>
            <span style="font-size:0.85rem;font-weight:600;color:#c9a96e;">₹${parseFloat(r.refund_amount).toFixed(2)}</span>
            <button onclick="openReturnModal(${JSON.stringify(r).replace(/"/g,'&quot;')})"
              style="padding:0.3rem 0.8rem;background:#c9a96e;color:#0c0c0c;border:none;border-radius:20px;font-size:0.78rem;cursor:pointer;font-weight:500;">
              manage
            </button>
          </div>
        </div>
      </div>`).join('');
  }

  window.openReturnModal = function(r) {
    const existing = document.getElementById('returnManageModal');
    if (existing) existing.remove();

    const statuses = ['requested','approved','rejected','pickup_scheduled','received','refunded'];
    const modal    = document.createElement('div');
    modal.id        = 'returnManageModal';
    modal.className = 'modal';
    modal.style.display = 'block';
    modal.innerHTML = `
      <div class="modal-content" style="max-width:520px;background:#1a1a1a;border:1px solid #333;">
        <span class="close-modal" onclick="document.getElementById('returnManageModal').remove()">&times;</span>
        <h2 style="font-size:1.2rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:1.5rem;color:#f0ebe3;">Manage Return</h2>
        <p style="font-size:0.85rem;color:#b8a89a;margin-bottom:1rem;"><strong>${r.return_number}</strong> · Order ${r.order_number}</p>
        <div style="margin-bottom:1rem;">
          <label style="display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;color:#c9a96e;font-weight:600;margin-bottom:0.4rem;">Status</label>
          <select id="returnStatusSelect" style="width:100%;padding:0.7rem;border:1px solid #444;border-radius:8px;font-size:0.9rem;background:#222;color:#f0ebe3;">
            ${statuses.map(s => `<option value="${s}" ${r.status===s?'selected':''}>${s.replace(/_/g,' ')}</option>`).join('')}
          </select>
        </div>
        <div style="margin-bottom:1rem;">
          <label style="display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;color:#c9a96e;font-weight:600;margin-bottom:0.4rem;">Refund Amount (₹)</label>
          <input type="number" id="returnRefundAmt" value="${r.refund_amount}" step="0.01" style="width:100%;padding:0.7rem;border:1px solid #444;border-radius:8px;font-size:0.9rem;background:#222;color:#f0ebe3;">
        </div>
        <div style="margin-bottom:1rem;">
          <label style="display:block;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;color:#c9a96e;font-weight:600;margin-bottom:0.4rem;">Admin Note (shown to customer)</label>
          <textarea id="returnAdminNote" style="width:100%;padding:0.7rem;border:1px solid #444;border-radius:8px;font-size:0.9rem;resize:vertical;min-height:80px;background:#222;color:#f0ebe3;">${r.admin_note || ''}</textarea>
        </div>
        <button onclick="saveReturn(${r.id})"
          style="padding:0.8rem 2rem;background:#c9a96e;color:#0c0c0c;border:none;border-radius:40px;cursor:pointer;font-weight:500;">
          Save Changes
        </button>
        <span id="returnSaveMsg" style="margin-left:1rem;font-size:0.85rem;"></span>
      </div>`;
    document.body.appendChild(modal);
    window.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
  };

  window.saveReturn = async function(id) {
    const status  = document.getElementById('returnStatusSelect')?.value;
    const refund  = parseFloat(document.getElementById('returnRefundAmt')?.value || 0);
    const note    = document.getElementById('returnAdminNote')?.value.trim();
    const msgEl   = document.getElementById('returnSaveMsg');
    const data    = await apiCall('../api/returns.php?action=update', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ id, status, adminNote: note, refundAmount: refund })
    });
    if (msgEl) { msgEl.style.color = data.success ? '#81c784' : '#ff6b6b'; msgEl.textContent = data.success ? '✓ Saved' : data.message; }
    if (data.success) { setTimeout(() => { document.getElementById('returnManageModal')?.remove(); loadReturns(); }, 1200); }
  };

  // ════════════════════════════════════════════════
  // INIT
  // ════════════════════════════════════════════════
  async function init() {
    const ok = await checkAdminAccess();
    if (!ok) return;
    setupTabs();
    setupProductModal();
    setupModals();
    setupFilters();
    setupReportButtons();
    setupHeaderSearch();
    loadAdminSettings();
    loadDashboard();
    setupDeliverySettings();
  }

  // ── Delivery Settings ────────────────────────────────────────────────
  function setupDeliverySettings() {
    const radiusSelect = document.getElementById('deliveryRadius');
    const customWrapper = document.getElementById('customPincodesWrapper');
    const selectStatesWrapper = document.getElementById('selectStatesWrapper');
    const statesCheckboxList = document.getElementById('statesCheckboxList');
    
    if (!radiusSelect) return;

    const allIndianStates = [
      'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
      'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
      'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
      'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
      'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
      'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi', 'Jammu & Kashmir'
    ];

    // Populate state checkboxes
    if (statesCheckboxList) {
      statesCheckboxList.innerHTML = allIndianStates.map(state => `
        <label class="state-checkbox-label">
          <input type="checkbox" class="state-checkbox" value="${state}">
          <span>${state}</span>
        </label>
      `).join('');
    }

    radiusSelect.addEventListener('change', function () {
      if (this.value === 'custom-pincodes') {
        customWrapper.style.display = 'block';
        selectStatesWrapper.style.display = 'none';
      } else if (this.value === 'select-states') {
        selectStatesWrapper.style.display = 'block';
        customWrapper.style.display = 'none';
      } else {
        customWrapper.style.display = 'none';
        selectStatesWrapper.style.display = 'none';
      }
    });

    // Load saved settings
    const saved = localStorage.getItem('deliveryRadius');
    if (saved) {
      radiusSelect.value = saved;
      if (saved === 'custom-pincodes') {
        customWrapper.style.display = 'block';
        const savedPins = localStorage.getItem('customPincodes');
        if (savedPins) {
          document.getElementById('customPincodes').value = savedPins;
        }
      } else if (saved === 'select-states') {
        selectStatesWrapper.style.display = 'block';
        const savedStates = JSON.parse(localStorage.getItem('selectedStates') || '[]');
        document.querySelectorAll('.state-checkbox').forEach(cb => {
          cb.checked = savedStates.includes(cb.value);
        });
      }
    }
  }

  window.saveDeliverySettings = async function () {
    const radiusSelect = document.getElementById('deliveryRadius');
    const customPincodes = document.getElementById('customPincodes');
    const radius = radiusSelect.value;

    localStorage.setItem('deliveryRadius', radius);

    if (radius === 'custom-pincodes') {
      const pincodes = customPincodes.value.trim();
      if (pincodes) {
        localStorage.setItem('customPincodes', pincodes);
      } else {
        localStorage.removeItem('customPincodes');
      }
    } else if (radius === 'select-states') {
      const selectedStates = Array.from(document.querySelectorAll('.state-checkbox:checked'))
        .map(cb => cb.value);
      localStorage.setItem('selectedStates', JSON.stringify(selectedStates));
    }

    alert('Delivery settings saved successfully!');
  };

  init();
});


