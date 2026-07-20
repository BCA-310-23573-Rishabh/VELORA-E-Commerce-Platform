// cart.js - Cart page (PHP backend version)

document.addEventListener('DOMContentLoaded', function () {

  // =============================================
  // STATE
  // =============================================
  let cart         = [];
  let subtotal     = 0;
  let gstTotal     = 0;  // calculated live from product GST rates
  let total        = 0;
  let discount     = 0;
  let shippingCost = 0;
  let appliedPromo = null;
  let productGstRates = {}; // cache: productId -> gst_rate

  const promoCodes = {
    'WELCOME10': { type: 'percentage', value: 10,  description: '10% off' },
    'FREESHIP':  { type: 'shipping',   value: 0,   description: 'free shipping' },
    'SAVE20':    { type: 'percentage', value: 20,  description: '20% off' },
    'SUMMER25':  { type: 'percentage', value: 25,  description: '25% off' }
  };

  const recommendedProducts = [
    { id: 301, name: 'wool blend coat',  price: 4999, image: 'url("Images/wool blend coat.webp")' },
    { id: 202, name: 'selvedge denim',   price: 4199, image: 'url("Images/selvedge straight fit.avif")' },
    { id: 401, name: 'linen shirt',      price: 2299, image: 'url("Images/linen button down.jpeg")' },
    { id: 502, name: 'leather belt',     price: 1299, image: 'url("Images/leather belt.avif")' }
  ];
  const fallbackGradient = 'linear-gradient(135deg, rgba(201,169,110,0.35), rgba(139,111,76,0.8))';

  // =============================================
  // DOM
  // =============================================
  const cartItemsContainer = document.getElementById('cartItems');
  const emptyCartDiv       = document.getElementById('emptyCart');
  const cartInfo           = document.getElementById('cartInfo');
  const subtotalEl         = document.getElementById('subtotal');
  const totalEl            = document.getElementById('total');
  const discountRow        = document.getElementById('discountRow');
  const discountAmount     = document.getElementById('discountAmount');
  const promoInput         = document.getElementById('promoInput');
  const applyPromoBtn      = document.getElementById('applyPromoBtn');
  const promoMessage       = document.getElementById('promoMessage');
  const updateCartBtn      = document.getElementById('updateCartBtn');
  const checkoutBtn        = document.getElementById('checkoutBtn');
  const recommendationsGrid = document.getElementById('recommendationsGrid');
  const cartCountSpan      = document.getElementById('cartCount');
  const checkoutModal      = document.getElementById('checkoutModal');
  const closeModal         = document.getElementById('closeModal');
  const checkoutForm       = document.getElementById('checkoutForm');
  const modalTotal         = document.getElementById('modalTotal');
  const shippingEl         = document.getElementById('shipping');

  // =============================================
  // INIT
  // =============================================
  async function init() {
    loadCart();
    showCartLoadingState();
    await fetchGstRates();   // get GST rates from server before rendering
    renderCart();
    renderRecommendations();
    setupEventListeners();
    updateCartCounter();

    if (typeof window.updateNavFromSession === 'function') {
      window.updateNavFromSession();
    }
  }

  function loadCart() {
    cart = JSON.parse(localStorage.getItem('veloraCart')) || [];
  }

  function showCartLoadingState() {
    if (!cartItemsContainer) return;
    cartItemsContainer.innerHTML = `
      <div class="cart-skeleton">
        <div class="skeleton-row"></div>
        <div class="skeleton-row"></div>
        <div class="skeleton-row"></div>
      </div>`;
    if (emptyCartDiv) emptyCartDiv.style.display = 'none';
    if (cartInfo) cartInfo.textContent = 'loading your cart...';
    if (promoMessage) promoMessage.textContent = '';
  }

  function setCheckoutFeedback(message, type = 'info') {
    const feedback = document.getElementById('checkoutFeedback');
    if (!feedback) return;
    feedback.textContent = message;
    feedback.className = `checkout-feedback ${type}`;
  }

  function resolveImageStyle(imageValue) {
    if (typeof imageValue === 'string' && imageValue.trim()) {
      const normalized = imageValue.trim();
      if (normalized.startsWith('url(')) return normalized;
      if (normalized.startsWith('http') || normalized.startsWith('/') || normalized.startsWith('../') || normalized.startsWith('Images/')) {
        return `url("${normalized}")`;
      }
    }
    return fallbackGradient;
  }

  function attachFallbackImage(element, imageValue, fallbackLabel = 'VELORA') {
    const resolvedStyle = resolveImageStyle(imageValue);
    element.style.backgroundImage = resolvedStyle;
    element.classList.toggle('has-image', typeof imageValue === 'string' && imageValue.trim() && imageValue.trim().startsWith('url('));
    element.innerHTML = '';
    if (typeof imageValue !== 'string' || !imageValue.trim() || !imageValue.trim().startsWith('url(')) {
      const label = document.createElement('span');
      label.className = 'image-fallback-label';
      label.textContent = fallbackLabel;
      element.appendChild(label);
    }
    return element;
  }

  // Fetch GST rates for all cart products from the server
  async function fetchGstRates() {
    if (cart.length === 0) return;
    try {
      const res  = await fetch('../api/products.php?action=list');
      const data = await res.json();
      if (data.success) {
        data.products.forEach(p => { productGstRates[p.id] = p.gst_rate || p.gstRate || 12; });
      }
    } catch (_) {
      // Default to 12% if fetch fails
    }
  }

  function getGstRate(productId) {
    return productGstRates[productId] || 12;
  }

  function saveCart() {
    localStorage.setItem('veloraCart', JSON.stringify(cart));
    updateCartCounter();
  }

  // =============================================
  // RENDER CART
  // =============================================
  function renderCart() {
    if (!cartItemsContainer) return;

    if (cart.length === 0) {
      cartItemsContainer.innerHTML = '';
      if (emptyCartDiv)  emptyCartDiv.style.display  = 'block';
      if (cartInfo)      cartInfo.textContent         = 'your cart is empty';
      updateSummary();
      return;
    }

    if (emptyCartDiv) emptyCartDiv.style.display = 'none';
    if (cartInfo)     cartInfo.textContent = `you have ${cart.length} item${cart.length > 1 ? 's' : ''} in your cart`;

    cartItemsContainer.innerHTML = '';
    cart.forEach((item, idx) => cartItemsContainer.appendChild(createCartRow(item, idx)));
    updateSummary();
  }

  function createCartRow(item, index) {
    const row = document.createElement('div');
    row.className = 'cart-item';

    const productCol = document.createElement('div');
    productCol.className = 'cart-item-product';

    const imgDiv = document.createElement('div');
    imgDiv.className             = 'cart-item-image';
    attachFallbackImage(imgDiv, item.image, (item.name || 'VELORA').split(' ')[0].toUpperCase());

    const details  = document.createElement('div');
    details.className = 'cart-item-details';

    const name     = document.createElement('h3');
    name.textContent = item.name;

    const meta     = document.createElement('p');
    const parts    = [];
    if (item.size)  parts.push(`Size: ${item.size}`);
    if (item.color) parts.push(`Color: ${item.color}`);
    meta.textContent = parts.join(' | ');

    details.appendChild(name);
    details.appendChild(meta);
    productCol.appendChild(imgDiv);
    productCol.appendChild(details);

    const priceCol    = document.createElement('div');
    priceCol.className = 'cart-item-price';
    priceCol.textContent = `₹${item.price.toFixed(2)}`;

    const qtyCol      = document.createElement('div');
    qtyCol.className  = 'cart-item-quantity';

    const minusBtn    = document.createElement('button');
    minusBtn.className = 'quantity-btn';
    minusBtn.textContent = '-';

    const qtyInput    = document.createElement('input');
    const quantityVal = Number.isInteger(item.quantity) && item.quantity > 0 ? item.quantity : 1;
    item.quantity     = quantityVal;
    qtyInput.type     = 'text';
    qtyInput.readOnly = true;
    qtyInput.className = 'quantity-input';
    qtyInput.value    = quantityVal;
    qtyInput.setAttribute('aria-label', `quantity of ${item.name}`);

    const plusBtn     = document.createElement('button');
    plusBtn.className = 'quantity-btn';
    plusBtn.textContent = '+';

    qtyCol.appendChild(minusBtn);
    qtyCol.appendChild(qtyInput);
    qtyCol.appendChild(plusBtn);

    const totalCol    = document.createElement('div');
    totalCol.className = 'cart-item-total';
    totalCol.textContent = `₹${(item.price * item.quantity).toFixed(2)}`;

    const removeCol   = document.createElement('div');
    const removeBtn   = document.createElement('button');
    removeBtn.className = 'remove-item';
    removeBtn.textContent = '✕';
    removeCol.appendChild(removeBtn);

    row.appendChild(productCol);
    row.appendChild(priceCol);
    row.appendChild(qtyCol);
    row.appendChild(totalCol);
    row.appendChild(removeCol);

    // Events
    minusBtn.addEventListener('click', () => updateQuantity(index, -1));
    plusBtn.addEventListener('click',  () => updateQuantity(index, 1));
    removeBtn.addEventListener('click', () => removeItem(index));

    return row;
  }

  function renderRecommendations() {
    if (!recommendationsGrid) return;
    recommendationsGrid.innerHTML = '';
    recommendedProducts.forEach(p => {
      const card   = document.createElement('div');
      card.className = 'recommendation-card';

      const img    = document.createElement('div');
      img.className             = 'recommendation-image';
      attachFallbackImage(img, p.image, p.name.toUpperCase());

      const info   = document.createElement('div');
      info.className = 'recommendation-info';

      const nameEl = document.createElement('h4');
      nameEl.textContent = p.name;

      const priceEl = document.createElement('div');
      priceEl.className  = 'recommendation-price';
      priceEl.textContent = `₹${p.price}`;

      const btn    = document.createElement('button');
      btn.className  = 'recommendation-add';
      btn.textContent = 'add to cart';
      btn.addEventListener('click', e => {
        e.stopPropagation();
        const ex = cart.find(i => i.id === p.id);
        if (ex) { ex.quantity++; } else { cart.push({ ...p, quantity: 1 }); }
        saveCart();
        renderCart();
        btn.textContent = '✓ added';
        setTimeout(() => { btn.textContent = 'add to cart'; }, 600);
      });

      info.appendChild(nameEl);
      info.appendChild(priceEl);
      info.appendChild(btn);
      card.appendChild(img);
      card.appendChild(info);
      recommendationsGrid.appendChild(card);
    });
  }

  // =============================================
  // CART OPERATIONS
  // =============================================
  function updateQuantity(index, delta, newVal) {
    if (newVal !== undefined) {
      cart[index].quantity = newVal;
    } else {
      cart[index].quantity = Math.max(1, cart[index].quantity + delta);
    }
    saveCart();
    renderCart();
  }

  function removeItem(index) {
    cart.splice(index, 1);
    saveCart();
    renderCart();
  }

  // =============================================
  // SUMMARY
  // =============================================
  function updateSummary() {
    // Calculate subtotal (prices are exclusive of GST)
    subtotal = cart.reduce((sum, i) => sum + i.price * i.quantity, 0);

    // Calculate GST live from product rates
    gstTotal = cart.reduce((sum, i) => {
      const rate = getGstRate(i.id);
      return sum + (i.price * i.quantity * rate / 100);
    }, 0);

    const subtotalWithGst = subtotal + gstTotal;
    shippingCost = subtotalWithGst >= 5000 ? 0 : (cart.length === 0 ? 0 : 99);

    let discounted = subtotal;
    if (appliedPromo) {
      const code = promoCodes[appliedPromo];
      if (code.type === 'percentage') {
        discount   = subtotal * (code.value / 100);
        discounted = subtotal - discount;
      } else if (code.type === 'shipping') {
        shippingCost = 0;
        discount     = 0;
      }
    } else {
      discount = 0;
    }

    total = discounted + gstTotal + shippingCost;

    if (subtotalEl) subtotalEl.textContent = `₹${subtotal.toFixed(2)}`;

    // Show live GST estimate in the summary
    const gstEl = document.getElementById('gstAmount');
    if (gstEl) {
      if (cart.length === 0) {
        gstEl.textContent = '₹0.00';
      } else {
        gstEl.textContent = `₹${gstTotal.toFixed(2)}`;
        gstEl.title = cart.map(i => {
          const rate = getGstRate(i.id);
          return `${i.name}: ${rate}% GST`;
        }).join(' | ');
      }
    }

    if (shippingEl) {
      shippingEl.textContent = shippingCost === 0 ? (cart.length ? 'FREE' : '₹0.00') : `₹${shippingCost.toFixed(2)}`;
    }

    if (totalEl)    totalEl.textContent    = `₹${total.toFixed(2)}`;
    if (modalTotal) modalTotal.textContent = `total: ₹${total.toFixed(2)}`;

    if (discountRow && discountAmount) {
      if (discount > 0) {
        discountRow.style.display  = 'flex';
        discountAmount.textContent = `-₹${discount.toFixed(2)}`;
      } else {
        discountRow.style.display = 'none';
      }
    }

    // Show GST breakdown tooltip note
    const gstNote = document.getElementById('gstBreakdownNote');
    if (gstNote && cart.length > 0) {
      const breakdown = cart.map(i => {
        const rate = getGstRate(i.id);
        const amt  = (i.price * i.quantity * rate / 100).toFixed(2);
        return `${i.name}: ${rate}% = ₹${amt}`;
      });
      gstNote.textContent = breakdown.join(' | ');
      gstNote.style.display = 'block';
    } else if (gstNote) {
      gstNote.style.display = 'none';
    }
  }

  // =============================================
  // PROMO CODE
  // =============================================
  function applyPromoCode() {
    const code = promoInput ? promoInput.value.trim().toUpperCase() : '';
    if (!code) return;

    if (promoCodes[code]) {
      appliedPromo = code;
      if (promoMessage) {
        promoMessage.textContent  = `✓ ${promoCodes[code].description} applied!`;
        promoMessage.className    = 'promo-message success';
      }
    } else {
      appliedPromo = null;
      if (promoMessage) {
        promoMessage.textContent  = '✕ Invalid promo code';
        promoMessage.className    = 'promo-message error';
      }
    }
    updateSummary();
  }

  // =============================================
  // CHECKOUT MODAL - open / close
  // =============================================
  function openCheckoutModal() {
    if (cart.length === 0) {
      alert('Your cart is empty. Please add items before checking out.');
      return;
    }

    setCheckoutFeedback('', 'info');

    // Pre-fill email + name from stored session
    const storedUser = localStorage.getItem('veloraCurrentUser');
    if (storedUser) {
      try {
        const u = JSON.parse(storedUser);
        const emailEl = document.getElementById('checkoutEmail');
        if (emailEl && u.email) emailEl.value = u.email;
      } catch (e) {}
    }

    // Update total display
    if (modalTotal) modalTotal.textContent = 'Total: ₹' + total.toFixed(2) + ' (incl. GST)';
    if (checkoutModal) checkoutModal.style.display = 'block';

    // Load saved addresses for logged-in users
    loadSavedAddresses();
  }

  // Load saved addresses from API and show selector in checkout
  async function loadSavedAddresses() {
    try {
      const res  = await fetch('../api/account.php?action=addresses');
      const data = JSON.parse(await res.text());
      if (!data.success || !data.addresses || !data.addresses.length) return;

      const panel = document.getElementById('savedAddressesPanel');
      const list  = document.getElementById('savedAddressList');
      if (!panel || !list) return;

      panel.style.display = 'block';
      list.innerHTML = data.addresses.map((addr, i) => `
        <div style="border:1.5px solid var(--border);border-radius:10px;padding:0.8rem 1rem;cursor:pointer;transition:border-color 0.2s;background:var(--bg-surface);"
             class="saved-addr-chip"
             onclick="fillCheckoutAddress(${JSON.stringify(addr).replace(/"/g, '&quot;')}, this)">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--gold);">${addr.label || 'Address'}</strong>
            ${addr.isDefault ? '<span style="font-size:0.72rem;background:var(--bg-elevated);color:var(--gold);padding:2px 8px;border-radius:10px;font-weight:600;">DEFAULT</span>' : ''}
          </div>
          <div style="font-size:0.85rem;color:var(--text-secondary);margin-top:0.3rem;line-height:1.6;">
            ${addr.fullName ? addr.fullName + '<br>' : ''}
            ${addr.address || ''}, ${addr.city || ''}${addr.state ? ', ' + addr.state : ''} ${addr.pincode || ''}
          </div>
        </div>`).join('');

      // Auto-fill default address
      const def = data.addresses.find(a => a.isDefault) || data.addresses[0];
      if (def) fillCheckoutAddress(def, null);

    } catch (e) { /* not logged in or no addresses */ }
  }

  // Fill checkout form fields from a saved address object
  window.fillCheckoutAddress = function (addr, el) {
    // Highlight selected chip
    document.querySelectorAll('.saved-addr-chip').forEach(c => {
      c.style.borderColor = 'var(--border, #ddd)';
      c.style.background  = 'var(--bg-surface)';
    });
    if (el) {
      el.style.borderColor = 'var(--gold, #8b6f4c)';
      el.style.background  = 'var(--bg-elevated)';
    }

    // Split fullName into first/last
    const parts = (addr.fullName || '').split(' ');
    const first  = parts[0] || '';
    const last   = parts.slice(1).join(' ') || '';

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
    set('checkoutFirstName', first);
    set('checkoutLastName',  last);
    set('checkoutAddress',   addr.address);
    set('checkoutAddress2',  '');
    set('checkoutCity',      addr.city);
    set('checkoutState',     addr.state);
    set('checkoutPincode',   addr.pincode);
    if (addr.phone) set('checkoutPhone', addr.phone);
  };

  function closeCheckoutModal() {
    if (checkoutModal) checkoutModal.style.display = 'none';
  }

  // =============================================
  // PLACE ORDER
  // =============================================
  async function handleCheckoutSubmit(e) {
    e.preventDefault();

    // --- Read all form fields by ID ---
    const email     = (document.getElementById('checkoutEmail')?.value     || '').trim();
    const phone     = (document.getElementById('checkoutPhone')?.value     || '').trim();
    const firstName = (document.getElementById('checkoutFirstName')?.value || '').trim();
    const lastName  = (document.getElementById('checkoutLastName')?.value  || '').trim();
    const addr1     = (document.getElementById('checkoutAddress')?.value   || '').trim();
    const addr2     = (document.getElementById('checkoutAddress2')?.value  || '').trim();
    const city      = (document.getElementById('checkoutCity')?.value      || '').trim();
    const state     = (document.getElementById('checkoutState')?.value     || '').trim();
    const pincode   = (document.getElementById('checkoutPincode')?.value   || '').trim();
    const address   = addr2 ? addr1 + ', ' + addr2 : addr1;
    const payment   = checkoutForm.querySelector('input[name="payment"]:checked')?.value || 'upi';

    // --- Client-side validation ---
    if (!email) { alert('Please enter your email address.'); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { alert('Please enter a valid email address.'); return; }
    if (!firstName) { alert('Please enter your first name.'); return; }
    if (!address)   { alert('Please enter your shipping address.'); return; }
    if (!city)      { alert('Please enter your city.'); return; }
    if (phone && !/^[0-9]{6,15}$/.test(phone)) {
      alert('Mobile number must be 10 digits starting with 6, 7, 8 or 9.');
      return;
    }
    if (pincode && !/^[1-9][0-9]{5}$/.test(pincode)) {
      alert('Please enter a valid 6-digit PIN code.');
      return;
    }

    // --- Build order payload ---
    const orderPayload = {
      email:     email,
      phone:     phone,
      firstName: firstName,
      lastName:  lastName,
      address:   address,
      city:      city,
      state:     state,
      pincode:   pincode,
      country:   'India',
      payment:   payment,
      promoCode: appliedPromo || '',
      items: cart.map(function(item) {
        return {
          id:       item.id,
          name:     item.name,
          price:    item.price,
          quantity: item.quantity,
          size:     item.size  || '',
          color:    item.color || ''
        };
      }),
      subtotal: subtotal,
      gst:      gstTotal,
      discount: discount,
      shipping: shippingCost,
      total:    total
    };

    // --- Submit regular orders ---
    const btn = checkoutForm.querySelector('.complete-order-btn');
    if (btn) { btn.textContent = 'placing order...'; btn.disabled = true; }
    setCheckoutFeedback('Preparing your order...', 'loading');

    let responseText = '';
    try {
      const response = await fetch('../api/orders.php?action=place', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(orderPayload)
      });

      responseText = await response.text(); // read as text first

      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseErr) {
        // PHP returned non-JSON - show the actual server output for debugging
        console.error('Server returned non-JSON:', responseText);
        setCheckoutFeedback('We could not place the order right now. Please try again.', 'error');
        alert('Server error. Check the browser console (F12) for details, or visit:\nhttp://localhost/velora/api/orders.php?action=diagnose');
        if (btn) { btn.textContent = 'complete order'; btn.disabled = false; }
        return;
      }

      if (data.success) {
        // Success - clear cart and show confirmation
        cart = [];
        localStorage.removeItem('veloraCart');
        updateCartCounter();
        setCheckoutFeedback('Order placed successfully. A confirmation is on its way.', 'success');
        closeCheckoutModal();
        showOrderConfirmation({
          orderNumber: data.orderNumber,
          email:       email,
          items:       orderPayload.items.map(function(i) {
            return Object.assign({}, i, { total: i.price * i.quantity });
          }),
          subtotal:    data.subtotal   || subtotal,
          gstAmount:   data.gstAmount  || gstTotal,
          discount:    data.discount   || discount,
          shipping:    data.shipping   || shippingCost,
          total:       data.total      || total,
          smsWarning:  data.smsWarning || ''
        });
      } else {
        setCheckoutFeedback((data.message || 'We could not place the order. Please try again.'), 'error');
        alert('Order failed: ' + (data.message || 'Unknown error. Please try again.'));
        if (btn) { btn.textContent = 'complete order'; btn.disabled = false; }
      }
    } catch (err) {
      console.error('Fetch error:', err);
      setCheckoutFeedback('We could not reach the server right now. Please try again.', 'error');
      alert('Network error. Please check your connection and try again.');
      if (btn) { btn.textContent = 'complete order'; btn.disabled = false; }
    }
  }

  // =============================================
  // ORDER CONFIRMATION MODAL
  // =============================================
  function showOrderConfirmation(order) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('confirmationModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id        = 'confirmationModal';
      modal.className = 'modal';
      document.body.appendChild(modal);
    }

    const itemsHtml = order.items.map(function(i) {
      const lineTotal = (i.price * i.quantity).toFixed(2);
      return '<div class="confirmation-item"><span>' + i.name + ' ×' + i.quantity + '</span><span>₹' + lineTotal + '</span></div>';
    }).join('');

    const gstLine      = order.gstAmount > 0
      ? '<div class="confirmation-row"><span>GST:</span><span>₹' + order.gstAmount.toFixed(2) + '</span></div>' : '';
    const discountLine = order.discount > 0
      ? '<div class="confirmation-row discount"><span>Discount:</span><span>-₹' + order.discount.toFixed(2) + '</span></div>' : '';
    const shipLine     = order.shipping === 0
      ? '<div class="confirmation-row"><span>Shipping:</span><span>FREE</span></div>'
      : '<div class="confirmation-row"><span>Shipping:</span><span>₹' + order.shipping.toFixed(2) + '</span></div>';

    modal.innerHTML =
      '<div class="modal-content confirmation-content">' +
        '<span class="close-modal" onclick="document.getElementById(\'confirmationModal\').style.display=\'none\'">&times;</span>' +
        '<div class="confirmation-icon">✓</div>' +
        '<h2>thank you for your order!</h2>' +
        '<p class="order-number">Order #' + order.orderNumber + '</p>' +
        '<p class="confirmation-email">Confirmation sent to ' + order.email + '</p>' +
        (order.smsWarning ? '<p class="confirmation-warning" style="color:#b63131;margin:0.75rem 0 0;padding:0.75rem 1rem;background:#fff1f0;border:1px solid #f5c2c7;border-radius:8px;">SMS delivery issue: ' + order.smsWarning + '</p>' : '') +
        '<div class="order-details">' +
          '<h3>order summary</h3>' +
          itemsHtml +
          '<div class="confirmation-totals">' +
            '<div class="confirmation-row"><span>Subtotal (excl. GST):</span><span>₹' + order.subtotal.toFixed(2) + '</span></div>' +
            gstLine + discountLine + shipLine +
            '<div class="confirmation-row total"><span>Total:</span><span>₹' + order.total.toFixed(2) + '</span></div>' +
          '</div>' +
        '</div>' +
        '<div class="confirmation-actions" style="flex-direction:column;gap:0.8rem;margin-top:1.5rem;">' +
          '<a href="order-tracking.php?order=' + order.orderNumber + '" ' +
             'style="display:block;padding:0.9rem 2rem;background:#8b6f4c;color:white;border-radius:40px;text-decoration:none;font-weight:500;text-align:center;">' +
            'Track Your Order' +
          '</a>' +
          '<button class="continue-shopping-btn" onclick="window.location.href=\'shop.php\'">continue shopping</button>' +
        '</div>' +
      '</div>';

    modal.style.display = 'block';
    renderCart();

    window.addEventListener('click', function(ev) {
      if (ev.target === modal) modal.style.display = 'none';
    });
  }

  // =============================================
  // CART COUNTER
  // =============================================
  function updateCartCounter() {
    if (!cartCountSpan) return;
    cartCountSpan.textContent = cart.reduce((a, i) => a + i.quantity, 0);
  }

  // =============================================
  // EVENT LISTENERS
  // =============================================
  function setupEventListeners() {
    if (updateCartBtn) updateCartBtn.addEventListener('click', () => { saveCart(); renderCart(); });

    const clearCartBtn = document.getElementById('clearCartBtn');
    if (clearCartBtn) {
      clearCartBtn.addEventListener('click', () => {
        if (cart.length === 0) return;
        cart = [];
        saveCart();
        renderCart();
      });
    }
    if (applyPromoBtn) applyPromoBtn.addEventListener('click', applyPromoCode);
    if (promoInput)    promoInput.addEventListener('keypress', e => { if (e.key === 'Enter') { e.preventDefault(); applyPromoCode(); } });
    if (checkoutBtn)   checkoutBtn.addEventListener('click', openCheckoutModal);
    if (closeModal)    closeModal.addEventListener('click', closeCheckoutModal);
    if (checkoutForm)  checkoutForm.addEventListener('submit', handleCheckoutSubmit);

    window.addEventListener('click', e => { if (e.target === checkoutModal) closeCheckoutModal(); });

    // --- Indian payment method toggle ---
    document.querySelectorAll('input[name="payment"]').forEach(radio => {
      radio.addEventListener('change', function () {
        // Hide all payment forms
        ['upiForm','cardForm','netbankingForm','codForm'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.style.display = 'none';
        });
        // Show selected
        const formMap = { upi: 'upiForm', card: 'cardForm', netbanking: 'netbankingForm', cod: 'codForm' };
        const target = document.getElementById(formMap[this.value]);
        if (target) target.style.display = 'block';
      });
    });

    // --- Checkout form validation ---
    const checkoutFormEl = document.getElementById('checkoutForm');
    if (checkoutFormEl) {
      // Real-time PIN code - only digits, max 6
      checkoutFormEl.addEventListener('input', function(e) {
        const t = e.target;
        if (t.placeholder && t.placeholder.includes('PIN code')) {
          t.value = t.value.replace(/[^0-9]/g, '').slice(0, 6);
        }
        if (t.id === 'upiId') {
          // UPI ID: allow alphanumeric, dots, hyphens and @
          t.value = t.value.replace(/[^a-zA-Z0-9.\-@]/g, '');
        }
        if (t.id === 'cardNumber') {
          t.value = t.value.replace(/[^0-9]/g, '').slice(0, 16)
            .replace(/(.{4})/g, '$1 ').trim();
        }
        if (t.id === 'cardExpiry') {
          t.value = t.value.replace(/[^0-9]/g, '').slice(0, 4)
            .replace(/^(\d{2})(\d)/, '$1/$2');
        }
        if (t.id === 'cardCvv') {
          t.value = t.value.replace(/[^0-9]/g, '').slice(0, 3);
        }
        // Phone field - only digits, max 10
        if (t.placeholder && t.placeholder.includes('mobile number')) {
          t.value = t.value.replace(/[^0-9]/g, '').slice(0, 10);
        }
      });
    }

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && checkoutModal?.style.display === 'block') closeCheckoutModal();
    });
  }

  init();
});

