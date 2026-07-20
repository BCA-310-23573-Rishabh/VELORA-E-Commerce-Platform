// account.js — My Account page

document.addEventListener('DOMContentLoaded', async function () {

  // ── Auth check ────────────────────────────────────────────────────
  let currentUser = null;

  try {
    const res  = await fetch('../api/auth.php?action=check');
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (_) {
      console.error('Auth check non-JSON:', text.substring(0, 300));
      document.getElementById('accountName').textContent  = 'Server Error';
      document.getElementById('accountEmail').textContent = 'Open http://localhost/velora/test.php';
      return;
    }
    if (!data.loggedIn) { window.location.href = 'login.php'; return; }
    currentUser = data.user;
  } catch (e) {
    document.getElementById('accountName').textContent  = 'Cannot connect';
    document.getElementById('accountEmail').textContent = 'Check Apache + MySQL in XAMPP';
    return;
  }

  // ── API helper ────────────────────────────────────────────────────
  async function api(url, options) {
    try {
      const res  = await fetch(url, options);
      const text = await res.text();
      try   { return JSON.parse(text); }
      catch (_) { console.error('Non-JSON:', url, text.substring(0,200)); return { success:false, message:'Server error' }; }
    } catch (_) { return { success:false, message:'Connection error' }; }
  }

  function sortOrders(orders, mode) {
    if (!Array.isArray(orders)) return orders || [];
    const list = orders.slice();
    switch (mode) {
      case 'oldest':
        return list.sort(function(a, b) { return new Date(a.date) - new Date(b.date); });
      case 'total-desc':
        return list.sort(function(a, b) { return (b.total || 0) - (a.total || 0); });
      case 'total-asc':
        return list.sort(function(a, b) { return (a.total || 0) - (b.total || 0); });
      case 'newest':
      default:
        return list.sort(function(a, b) { return new Date(b.date) - new Date(a.date); });
    }
  }

  // ── Hero ──────────────────────────────────────────────────────────
  function populateHero(user) {
    var init = ((user.firstName||'?')[0] + (user.lastName||'?')[0]).toUpperCase();
    document.getElementById('accountAvatar').textContent = init;
    document.getElementById('accountName').textContent   = user.firstName + ' ' + user.lastName;
    document.getElementById('accountEmail').textContent  = user.email;
  }
  populateHero(currentUser);

  if (currentUser && currentUser.isAdmin) {
    var heroText = document.querySelector('.account-hero-text');
    if (heroText && !document.getElementById('adminPanelLink')) {
      var adminLink = document.createElement('a');
      adminLink.id = 'adminPanelLink';
      adminLink.href = 'admin.php';
      adminLink.textContent = 'Admin Panel';
      adminLink.style.cssText = 'display:inline-block;margin-top:1rem;padding:0.75rem 1rem;background:#8b6f4c;color:#fff;border-radius:999px;text-decoration:none;font-weight:600;';
      adminLink.href = '../admin/admin.php';
      heroText.appendChild(adminLink);
    }
  }

  // ── Tab switching ─────────────────────────────────────────────────
  function switchTab(name) {
    document.querySelectorAll('.account-tab').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.account-nav-btn[data-tab]').forEach(function(b){ b.classList.remove('active'); });
    var tab = document.getElementById(name + 'Tab');
    var btn = document.querySelector('[data-tab="' + name + '"]');
    if (tab) tab.classList.add('active');
    if (btn) btn.classList.add('active');
  }
  window.switchTab = switchTab;

  function setupHeaderSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.querySelector('.search-btn');
    if (!searchInput || !searchBtn) return;

    const doSearch = () => {
      const query = searchInput.value.trim();
      if (query) window.location.href = `shop.php?q=${encodeURIComponent(query)}`;
    };

    searchBtn.addEventListener('click', doSearch);
    searchInput.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') doSearch();
    });
  }

  setupHeaderSearch();

  document.querySelectorAll('.account-nav-btn[data-tab]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var tab = this.dataset.tab;
      switchTab(tab);
      if (tab === 'overview')    { loadOverview(); }
      if (tab === 'orders')      { loadOrders(); }
      if (tab === 'addresses')   { loadAddresses(); }
      if (tab === 'profile')     { loadProfile(); }
      if (tab === 'returns')     { loadMyReturns(); }
      if (tab === 'myfeedback')  { loadMyFeedback(); }
    });
  });

  var ordersSortSelect = document.getElementById('ordersSortSelect');
  if (ordersSortSelect) {
    ordersSortSelect.addEventListener('change', function() {
      loadOrders();
    });
  }

  // ── Load initial data ──
  setTimeout(function() {
    loadOverview();
    loadProfile();
  }, 300);

  document.getElementById('logoutBtn')?.addEventListener('click', async function() {
    try { await fetch('../api/auth.php?action=logout'); } catch(_) {}
    localStorage.removeItem('veloraCurrentUser');
    window.location.href = 'login.php';
  });

  // ══════════════════════════════════════════════════════════════════
  // OVERVIEW
  // ══════════════════════════════════════════════════════════════════
  async function loadOverview() {
    var profileData = await api('../api/account.php?action=profile');
    if (profileData.success && profileData.user)
      document.getElementById('memberSince').textContent = 'member since ' + profileData.user.memberSince;

    var ordersData = await api('../api/account.php?action=orders');
    var orders = ordersData.orders || [];
    var spent  = orders.reduce(function(s,o){ return s + o.total; }, 0);
    var active = orders.filter(function(o){ return ['pending','processing','shipped'].includes(o.status); }).length;

    document.getElementById('totalOrdersCount').textContent   = orders.length;
    document.getElementById('totalSpentAmount').textContent   = '\u20B9' + spent.toFixed(2);
    document.getElementById('pendingOrdersCount').textContent = active;

    var recentDiv = document.getElementById('recentOrdersOverview');
    if (!orders.length) {
      recentDiv.innerHTML = '<p class="empty-msg">No orders yet. <a href="shop.php">Start shopping \u2192</a></p>';
    } else {
      recentDiv.innerHTML = orders.slice(0,3).map(function(o) {
        return '<div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid #f5f5f5;font-size:0.9rem;cursor:pointer;" onclick="viewOrderDetail(\'' + o.orderNumber + '\')">' +
          '<span style="font-weight:600;">' + o.orderNumber + '</span>' +
          '<span class="order-status-badge status-' + o.status + '">' + o.status + '</span>' +
          '<span style="color:#8b6f4c;font-weight:600;">\u20B9' + o.total.toFixed(2) + '</span>' +
        '</div>';
      }).join('');
    }

    var addrData = await api('../api/account.php?action=addresses');
    var addrs    = addrData.addresses || [];
    var addrDiv  = document.getElementById('addressesOverview');

    if (!addrs.length) {
      addrDiv.innerHTML = '<p class="empty-msg">No saved addresses. <button class="text-btn" onclick="switchTab(\'addresses\')">Add one \u2192</button></p>';
    } else {
      var def = addrs.find(function(a){ return a.isDefault; }) || addrs[0];
      addrDiv.innerHTML =
        '<div style="font-size:0.9rem;line-height:1.8;color:#555;">' +
          '<strong style="color:#8b6f4c;text-transform:uppercase;font-size:0.8rem;">' + def.label + '</strong><br>' +
          (def.fullName ? def.fullName + '<br>' : '') +
          (def.address  || '') + '<br>' +
          (def.city || '') + (def.state ? ', ' + def.state : '') + ' ' + (def.pincode || '') + '<br>' +
          (def.country  || 'India') +
        '</div>' +
        (addrs.length > 1 ? '<p style="color:#999;font-size:0.8rem;margin-top:0.5rem;">+' + (addrs.length-1) + ' more address' + (addrs.length>2?'es':'') + '</p>' : '');
    }
  }

  // ══════════════════════════════════════════════════════════════════
  // ORDERS LIST
  // ══════════════════════════════════════════════════════════════════
  async function loadOrders() {
    var container = document.getElementById('ordersList');
    container.innerHTML = '<p class="empty-msg">Loading orders...</p>';

    var data   = await api('../api/account.php?action=orders');
    var orders = data.orders || [];
    var sortMode = document.getElementById('ordersSortSelect')?.value || 'newest';
    orders = sortOrders(orders, sortMode);

    if (!data.success || !orders.length) {
      container.innerHTML = '<p class="empty-msg">No orders yet. <a href="shop.php">Browse our collection →</a></p>';
      return;
    }

    container.innerHTML = orders.map(function(o) {
      var trackLink   = '<a href="order-tracking.php?order=' + o.orderNumber + '" onclick="event.stopPropagation()" style="font-size:0.78rem;color:#8b6f4c;text-decoration:none;padding:0.25rem 0.7rem;border:1px solid #8b6f4c;border-radius:20px;font-weight:500;">🚚 track</a>';
      var invoiceLink = '<a href="../api/invoice.php?order=' + o.orderNumber + '&action=download" target="_blank" onclick="event.stopPropagation()" style="font-size:0.78rem;color:#555;text-decoration:none;padding:0.25rem 0.7rem;border:1px solid #ccc;border-radius:20px;font-weight:500;">🧾 download invoice</a>';
      var returnLink  = o.status === 'delivered'
        ? '<a href="returns.php?order=' + o.orderNumber + '" onclick="event.stopPropagation()" style="font-size:0.78rem;color:#c0392b;text-decoration:none;padding:0.25rem 0.7rem;border:1px solid #e0aaaa;border-radius:20px;font-weight:500;">↩ return</a>'
        : '';

      return '<div class="order-card" onclick="viewOrderDetail(\'' + o.orderNumber + '\')">' +
        '<div class="order-card-header">' +
          '<span class="order-number-label">' + o.orderNumber + '</span>' +
          '<span class="order-status-badge status-' + o.status + '">' + o.status + '</span>' +
        '</div>' +
        '<div class="order-card-meta">' +
          '<span>\uD83D\uDCC5 ' + o.date + '</span>' +
          '<span>\uD83D\uDCE6 ' + o.itemCount + ' item' + (o.itemCount !== 1 ? 's' : '') + '</span>' +
        '</div>' +
        '<div class="order-card-footer">' +
          '<span class="order-total-label">\u20B9' + o.total.toFixed(2) + '</span>' +
          '<div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">' +
            '<span class="view-order-link">view details \u2192</span>' +
            trackLink + invoiceLink + returnLink +
          '</div>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  // ══════════════════════════════════════════════════════════════════
  // ORDER DETAIL
  // ══════════════════════════════════════════════════════════════════
  window.viewOrderDetail = async function(orderNumber) {
    switchTab('orderDetail');
    var content = document.getElementById('orderDetailContent');
    var title   = document.getElementById('orderDetailTitle');
    content.innerHTML = '<p class="empty-msg">Loading...</p>';
    title.textContent = 'Loading order...';

    document.getElementById('trackOrderBtn')?.remove();
    document.getElementById('invoiceOrderBtn')?.remove();

    var data = await api('../api/account.php?action=order_detail&order_number=' + encodeURIComponent(orderNumber));
    if (!data.success) { content.innerHTML = '<p class="empty-msg">Order not found.</p>'; return; }

    var o = data.order;
    title.textContent = 'Order ' + o.orderNumber;

    var backBtn = document.querySelector('#orderDetailTab .back-btn');
    if (backBtn) {
      var trackBtn       = document.createElement('a');
      trackBtn.id        = 'trackOrderBtn';
      trackBtn.href      = 'order-tracking.php?order=' + o.orderNumber;
      trackBtn.target    = '_blank';
      trackBtn.textContent = 'track this order';
      trackBtn.style.cssText = 'display:inline-block;margin-left:0.8rem;padding:0.4rem 1rem;background:#8b6f4c;color:white;border-radius:20px;font-size:0.85rem;text-decoration:none;font-weight:500;';

      var invBtn       = document.createElement('a');
      invBtn.id        = 'invoiceOrderBtn';
      invBtn.href      = '../api/invoice.php?order=' + o.orderNumber;
      invBtn.target    = '_blank';
      invBtn.textContent = 'invoice';
      invBtn.style.cssText = 'display:inline-block;margin-left:0.5rem;padding:0.4rem 1rem;background:transparent;color:#555;border:1px solid #ccc;border-radius:20px;font-size:0.85rem;text-decoration:none;';

      backBtn.insertAdjacentElement('afterend', invBtn);
      backBtn.insertAdjacentElement('afterend', trackBtn);
    }

    // Tracking steps
    var steps = o.trackingSteps || [];
    var trackingHTML = '<div class="tracking-bar"><h3>order tracking</h3><div class="tracking-steps">' +
      steps.map(function(s) {
        return '<div class="tracking-step ' + (s.done ? 'done' : '') + '">' +
          '<div class="tracking-step-icon">' + s.icon + '</div>' +
          '<div class="tracking-step-label">' + s.label + '</div>' +
        '</div>';
      }).join('') + '</div></div>';

    // Items
    var items = o.items || [];
    var itemsHTML = '<div class="order-items-card"><h3>items ordered</h3>' +
      items.map(function(item) {
        var meta = [];
        if (item.size)  meta.push('Size: '  + item.size);
        if (item.color) meta.push('Color: ' + item.color);
        meta.push('Qty: ' + item.quantity);
        return '<div class="order-item-row">' +
          '<div><div class="order-item-name">' + item.name + '</div>' +
          '<div class="order-item-meta">' + meta.join(' \u00B7 ') + '</div></div>' +
          '<div class="order-item-price">\u20B9' + item.total.toFixed(2) + '</div>' +
        '</div>';
      }).join('') + '</div>';

    // Totals
    var gst  = (o.gstAmount  > 0) ? '<div class="totals-row"><span>GST</span><span>\u20B9' + o.gstAmount.toFixed(2)  + '</span></div>' : '';
    var disc = (o.discount   > 0) ? '<div class="totals-row" style="color:#27ae60;"><span>Discount</span><span>-\u20B9' + o.discount.toFixed(2) + '</span></div>' : '';
    var ship = (o.shipping  === 0) ? 'FREE' : '\u20B9' + o.shipping.toFixed(2);
    var totalsHTML = '<div class="order-totals-card">' +
      '<div class="totals-row"><span>Subtotal</span><span>\u20B9' + o.subtotal.toFixed(2) + '</span></div>' +
      gst + disc +
      '<div class="totals-row"><span>Shipping</span><span>' + ship + '</span></div>' +
      '<div class="totals-row grand-total"><span>Total</span><span>\u20B9' + o.total.toFixed(2) + '</span></div>' +
    '</div>';

    // Address
    var addr = o.shippingAddress || {};
    var shippingHTML = '<div class="order-items-card"><h3>shipping address</h3>' +
      '<p style="color:#555;line-height:1.8;font-size:0.95rem;">' +
        (addr.address||'') + '<br>' +
        (addr.city||'') + (addr.state?', '+addr.state:'') + ' ' + (addr.pincode||'') + '<br>' +
        (addr.country||'India') +
      '</p></div>';

    content.innerHTML = trackingHTML + itemsHTML + totalsHTML + shippingHTML;
  };

  // ══════════════════════════════════════════════════════════════════
  // ADDRESSES
  // ══════════════════════════════════════════════════════════════════
  async function loadAddresses() {
    var container = document.getElementById('addressesList');
    container.innerHTML = '<p class="empty-msg">Loading...</p>';

    var data  = await api('../api/account.php?action=addresses');
    var addrs = data.addresses || [];

    if (!addrs.length) {
      container.innerHTML = '<p class="empty-msg">No saved addresses yet. Click "Add new address" to add one.</p>';
      return;
    }

    container.innerHTML = '<div class="address-grid">' +
      addrs.map(function(a) {
        var safe = JSON.stringify(a).replace(/"/g, '&quot;');
        return '<div class="address-card ' + (a.isDefault?'default':'') + '">' +
          '<div class="address-card-label">' + a.label +
            (a.isDefault ? ' <span class="default-badge">default</span>' : '') +
          '</div>' +
          '<div class="address-card-text">' +
            '<strong>' + (a.fullName||'') + '</strong><br>' +
            (a.address||'') + '<br>' +
            (a.city||'') + (a.state?', '+a.state:'') + ' ' + (a.pincode||'') + '<br>' +
            (a.country||'India') + '<br>' +
            (a.phone ? '\uD83D\uDCDE ' + a.phone : '') +
          '</div>' +
          '<div class="address-card-actions">' +
            '<button class="edit-address-btn" onclick="editAddress(' + safe + ')">edit</button>' +
            '<button class="delete-address-btn" onclick="deleteAddress(' + a.id + ')">delete</button>' +
          '</div>' +
        '</div>';
      }).join('') + '</div>';
  }

  document.getElementById('addAddressBtn')?.addEventListener('click', function() {
    document.getElementById('addressFormTitle').textContent = 'add new address';
    document.getElementById('addressForm').reset();
    document.getElementById('addressId').value   = '';
    document.getElementById('addrCountry').value = 'India';
    document.getElementById('addressFormWrap').style.display = 'block';
    document.getElementById('addressFormWrap').scrollIntoView({ behavior:'smooth' });
  });

  document.getElementById('cancelAddressBtn')?.addEventListener('click', function() {
    document.getElementById('addressFormWrap').style.display = 'none';
  });

  window.editAddress = function(addr) {
    document.getElementById('addressFormTitle').textContent = 'edit address';
    document.getElementById('addressId').value      = addr.id       || '';
    document.getElementById('addrLabel').value      = addr.label    || 'Home';
    document.getElementById('addrFullName').value   = addr.fullName || '';
    document.getElementById('addrAddress').value    = addr.address  || '';
    document.getElementById('addrAddress2').value   = addr.address2 || '';
    document.getElementById('addrCity').value       = addr.city     || '';
    document.getElementById('addrState').value      = addr.state    || '';
    document.getElementById('addrPincode').value    = addr.pincode  || '';
    document.getElementById('addrPhone').value      = addr.phone    || '';
    document.getElementById('addrCountry').value    = addr.country  || 'India';
    document.getElementById('addrDefault').checked  = !!addr.isDefault;
    document.getElementById('addressFormWrap').style.display = 'block';
    document.getElementById('addressFormWrap').scrollIntoView({ behavior:'smooth' });
  };

  window.deleteAddress = async function(id) {
    if (!confirm('Delete this address?')) return;
    var data = await api('../api/account.php?action=delete_address', {
      method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:id})
    });
    if (data.success) { loadAddresses(); loadOverview(); }
    else alert(data.message || 'Failed to delete.');
  };

  document.getElementById('addrPincode')?.addEventListener('input', function(){ this.value = this.value.replace(/[^0-9]/g,'').slice(0,6); });
  document.getElementById('addrPhone')?.addEventListener('input',   function(){ this.value = this.value.replace(/[^0-9]/g,'').slice(0,10); });

  document.getElementById('addressForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    var fb      = document.getElementById('addressFeedback');
    fb.className = 'form-feedback';
    var address = document.getElementById('addrAddress').value.trim();
    var city    = document.getElementById('addrCity').value.trim();
    var pincode = document.getElementById('addrPincode').value.trim();
    var phone   = document.getElementById('addrPhone').value.trim();

    if (!address) { showMsg(fb,'Address line 1 is required.',false); return; }
    if (!city)    { showMsg(fb,'City is required.',false); return; }
    if (pincode && !/^[1-9][0-9]{5}$/.test(pincode)) { showMsg(fb,'Enter a valid 6-digit PIN code.',false); return; }
    if (phone   && !/^[0-9]{6,15}$/.test(phone))   { showMsg(fb,'Mobile must be 10 digits starting 6-9.',false); return; }

    var btn = this.querySelector('.save-btn');
    btn.textContent = 'Saving...'; btn.disabled = true;

    var data = await api('../api/account.php?action=save_address', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        id:        parseInt(document.getElementById('addressId').value)||0,
        label:     document.getElementById('addrLabel').value    ||'Home',
        fullName:  document.getElementById('addrFullName').value ||'',
        address:   address, city:city,
        state:     document.getElementById('addrState').value    ||'',
        pincode:   pincode,
        country:   document.getElementById('addrCountry').value  ||'India',
        phone:     phone,
        isDefault: document.getElementById('addrDefault').checked ? 1 : 0
      })
    });

    btn.textContent = 'save address'; btn.disabled = false;

    if (data.success) {
      document.getElementById('addressFormWrap').style.display = 'none';
      this.reset();
      document.getElementById('addrCountry').value = 'India';
      loadAddresses(); loadOverview();
    } else {
      showMsg(fb, data.message||'Failed to save address.', false);
    }
  });

  // ══════════════════════════════════════════════════════════════════
  // PROFILE
  // ══════════════════════════════════════════════════════════════════
  async function loadProfile() {
    var data = await api('../api/account.php?action=profile');
    if (!data.success) return;
    document.getElementById('profileFirstName').value = data.user.firstName||'';
    document.getElementById('profileLastName').value  = data.user.lastName ||'';
    document.getElementById('profileEmail').value     = data.user.email    ||'';
  }

  ['profileFirstName','profileLastName'].forEach(function(id){
    document.getElementById(id)?.addEventListener('input', function(){ this.value = this.value.replace(/[^a-zA-Z\s'\-]/g,''); });
  });

  document.getElementById('profileForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    var fb    = document.getElementById('profileFeedback');
    var fname = document.getElementById('profileFirstName').value.trim();
    var lname = document.getElementById('profileLastName').value.trim();
    var email = document.getElementById('profileEmail').value.trim();

    if (!fname || !/^[a-zA-Z\s'\-]{2,50}$/.test(fname)) { showMsg(fb,'First name — letters only.',false); return; }
    if (!lname || !/^[a-zA-Z\s'\-]{2,50}$/.test(lname)) { showMsg(fb,'Last name — letters only.',false); return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showMsg(fb,'Enter a valid email.',false); return; }

    var btn = this.querySelector('.save-btn');
    btn.textContent='Saving...'; btn.disabled=true;

    var data = await api('../api/account.php?action=update_profile', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({firstName:fname,lastName:lname,email:email})
    });
    showMsg(fb, data.message, data.success);

    if (data.success) {
      currentUser = {firstName:fname, lastName:lname, email:email};
      document.getElementById('accountName').textContent   = fname + ' ' + lname;
      document.getElementById('accountEmail').textContent  = email;
      document.getElementById('accountAvatar').textContent = (fname[0]+lname[0]).toUpperCase();
      var stored={};
      try{stored=JSON.parse(localStorage.getItem('veloraCurrentUser')||'{}');}catch(_){}
      stored.firstName=fname; stored.lastName=lname; stored.email=email;
      localStorage.setItem('veloraCurrentUser',JSON.stringify(stored));
      // Refresh profile form data to confirm save
      setTimeout(function() { loadProfile(); }, 500);
      // Refresh overview stats
      setTimeout(function() { loadOverview(); }, 500);
    }
    btn.textContent='save changes'; btn.disabled=false;
    setTimeout(function(){ fb.className='form-feedback'; },4000);
  });

  // ══════════════════════════════════════════════════════════════════
  // CHANGE PASSWORD
  // ══════════════════════════════════════════════════════════════════
  document.getElementById('passwordForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    var fb      = document.getElementById('passwordFeedback');
    var current = document.getElementById('currentPassword').value;
    var newPass = document.getElementById('newPassword').value;
    var confirm = document.getElementById('confirmNewPassword').value;

    if (!current)              { showMsg(fb,'Current password is required.',false); return; }
    if (newPass.length < 6)    { showMsg(fb,'New password must be at least 6 characters.',false); return; }
    if (newPass !== confirm)   { showMsg(fb,'Passwords do not match.',false); return; }

    var btn = this.querySelector('.save-btn');
    btn.textContent='Updating...'; btn.disabled=true;

    var data = await api('../api/account.php?action=change_password', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({currentPassword:current,newPassword:newPass})
    });
    showMsg(fb, data.message, data.success);
    if (data.success) this.reset();
    btn.textContent='update password'; btn.disabled=false;
    setTimeout(function(){ fb.className='form-feedback'; },4000);
  });


  // ======================================================================
  // MY RETURNS
  // ======================================================================
  async function loadMyReturns() {
    var container = document.getElementById('myReturnsList');
    if (!container) return;
    container.innerHTML = '<p class="empty-msg">Loading...</p>';

    var data    = await api('../api/returns.php?action=my_returns');
    var returns = data.returns || [];

    if (!data.success || !returns.length) {
      container.innerHTML =
        '<p class="empty-msg">You have no return requests yet.</p>' +
        '<p style="margin-top:1rem;font-size:0.9rem;color:var(--text-secondary);">Need to return something? ' +
        '<a href="returns.php" style="color:var(--gold);font-weight:500;">Submit a return request →</a></p>';
      return;
    }

    // Status → colours
    var statusColor = {
      'requested':        { bg:'rgba(201,169,110,0.14)', color:'#f0d58a' },
      'approved':         { bg:'rgba(76,175,80,0.16)', color:'#a5d6a7' },
      'rejected':         { bg:'rgba(229,115,115,0.16)', color:'#f28b82' },
      'pickup_scheduled': { bg:'rgba(96,165,250,0.16)', color:'#93c5fd' },
      'received':         { bg:'rgba(72,187,120,0.16)', color:'#86efac' },
      'refunded':         { bg:'rgba(129,199,132,0.16)', color:'#86cbb5' }
    };

    // Status → friendly step label
    var statusStep = {
      'requested':        'Step 1 of 4 — Request received',
      'approved':         'Step 2 of 4 — Return approved',
      'pickup_scheduled': 'Step 3 of 4 — Pickup scheduled',
      'received':         'Step 3 of 4 — Item received by us',
      'refunded':         'Step 4 of 4 — Refund processed',
      'rejected':         'Request rejected'
    };

    container.innerHTML = returns.map(function(r) {
      var sc    = statusColor[r.status] || { bg:'rgba(255,255,255,0.08)', color:'#dcdcdc' };
      var step  = statusStep[r.status]  || r.status;
      var pct   = { requested:10, approved:35, pickup_scheduled:60, received:80, refunded:100, rejected:0 }[r.status] || 0;
      var barColor = r.status === 'rejected' ? '#e57373' : 'var(--gold)';

      return '<div style="background:var(--bg-surface);border-radius:16px;padding:1.5rem;margin-bottom:1rem;box-shadow:0 18px 45px rgba(0,0,0,0.25);">' +

        // Header
        '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.8rem;margin-bottom:1rem;">' +
          '<div>' +
            '<div style="font-weight:700;font-size:1rem;color:var(--text-primary);">' + r.returnNumber + '</div>' +
            '<div style="font-size:0.82rem;color:var(--text-secondary);margin-top:0.2rem;">Order: ' + r.orderNumber + ' · ' + r.date + '</div>' +
          '</div>' +
          '<span style="padding:0.3rem 0.9rem;border-radius:20px;font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;background:' + sc.bg + ';color:' + sc.color + ';">' +
            r.status.replace(/_/g,' ') +
          '</span>' +
        '</div>' +

        // Progress bar
        '<div style="margin-bottom:1rem;">' +
          '<div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--text-secondary);margin-bottom:0.4rem;">' +
            '<span>' + step + '</span>' +
            '<span>' + pct + '%</span>' +
          '</div>' +
          '<div style="height:6px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;">' +
            '<div style="height:100%;width:' + pct + '%;background:' + barColor + ';border-radius:3px;transition:width 0.5s;"></div>' +
          '</div>' +
        '</div>' +

        // Details
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;font-size:0.85rem;">' +
          '<div><span style="color:var(--text-secondary);">Reason:</span> ' + r.reason.replace(/_/g,' ') + '</div>' +
          '<div><span style="color:var(--text-secondary);">Refund:</span> <strong style="color:var(--gold);">₹' + parseFloat(r.refundAmount).toFixed(2) + '</strong></div>' +
        '</div>' +

        // Description if any
        (r.description ? '<div style="margin-top:0.6rem;font-size:0.83rem;color:var(--text-secondary);">' + r.description + '</div>' : '') +
        // Admin note
        (r.adminNote ?
          '<div style="margin-top:0.8rem;padding:0.7rem 1rem;background:rgba(201,169,110,0.12);border-left:3px solid var(--gold);border-radius:0 8px 8px 0;font-size:0.83rem;color:var(--text-primary);">' +
            '<strong style="color:var(--gold);display:block;margin-bottom:0.2rem;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Update from VELORA</strong>' +
            r.adminNote +
          '</div>'
        : '') +

      '</div>';
    }).join('');
  }

  // ======================================================================
  // MY FEEDBACK
  // ======================================================================
  async function loadMyFeedback() {
    var container = document.getElementById('myFeedbackList');
    if (!container) return;
    container.innerHTML = '<p class="empty-msg">Loading...</p>';

    var data     = await api('../api/feedback.php?action=my_feedback');
    var feedbacks = data.feedback || [];

    if (!data.success || !feedbacks.length) {
      container.innerHTML =
        '<p class="empty-msg">You have not submitted any feedback yet.</p>' +
        '<p style="margin-top:1rem;font-size:0.9rem;color:var(--text-secondary);">We would love to hear from you. ' +
        '<a href="feedback.php" style="color:var(--gold);font-weight:500;">Send us a message →</a></p>';
      return;
    }

    var statusColor = {
      'new':      { bg:'rgba(201,169,110,0.14)', color:'#f0d58a', label:'Received' },
      'read':     { bg:'rgba(255,255,255,0.06)', color:'#dcdcdc', label:'Being reviewed' },
      'replied':  { bg:'rgba(76,175,80,0.16)', color:'#a5d6a7', label:'Replied' },
      'resolved': { bg:'rgba(76,175,80,0.12)', color:'#86efac', label:'Resolved' }
    };

    container.innerHTML = feedbacks.map(function(f) {
      var sc     = statusColor[f.status] || { bg:'rgba(255,255,255,0.08)', color:'#dcdcdc', label: f.status };
      var stars  = f.rating ? '★'.repeat(parseInt(f.rating)) + '☆'.repeat(5 - parseInt(f.rating)) : '';

      return '<div style="background:var(--bg-surface);border-radius:16px;padding:1.5rem;margin-bottom:1rem;box-shadow:0 18px 45px rgba(0,0,0,0.25);">' +

        // Header
        '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.8rem;margin-bottom:0.8rem;">' +
          '<div>' +
            '<div style="font-weight:600;font-size:0.95rem;color:var(--text-primary);">' + (f.subject || '(no subject)') + '</div>' +
            '<div style="font-size:0.8rem;color:var(--text-secondary);margin-top:0.2rem;">' +
              new Date(f.created_at).toLocaleDateString('en-IN', {day:'numeric',month:'short',year:'numeric'}) +
              ' · ' + (f.type || 'general') +
              (stars ? ' · <span style="color:var(--gold);">' + stars + '</span>' : '') +
            '</div>' +
          '</div>' +
          '<span style="padding:0.3rem 0.9rem;border-radius:20px;font-size:0.78rem;font-weight:700;background:' + sc.bg + ';color:' + sc.color + ';">' +
            sc.label +
          '</span>' +
        '</div>' +

        // Your message
        '<div style="font-size:0.88rem;color:var(--text-secondary);line-height:1.6;padding:0.8rem;background:rgba(255,255,255,0.05);border-radius:8px;margin-bottom:0.6rem;">' +
          f.message +
        '</div>' +

        // Admin reply
        (f.admin_reply ?
          '<div style="padding:0.8rem 1rem;background:rgba(201,169,110,0.12);border-radius:8px;border-left:3px solid var(--gold);">' +
            '<div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--gold);font-weight:700;margin-bottom:0.3rem;">' +
              'VELORA replied' +
              (f.replied_at ? ' · ' + new Date(f.replied_at).toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'}) : '') +
            '</div>' +
            '<div style="font-size:0.88rem;color:#5e4b34;line-height:1.6;">' + f.admin_reply + '</div>' +
          '</div>'
        :
          '<div style="font-size:0.8rem;color:#bbb;font-style:italic;">No reply yet. We usually respond within 24 hours.</div>'
        ) +

      '</div>';
    }).join('');
  }

  // ── Helper ────────────────────────────────────────────────────────
  function showMsg(el, msg, ok) {
    if (!el) return;
    el.className   = 'form-feedback ' + (ok ? 'success' : 'error');
    el.textContent = msg || '';
  }

  // ── Load initial tab ──────────────────────────────────────────────
  loadOverview();
});


