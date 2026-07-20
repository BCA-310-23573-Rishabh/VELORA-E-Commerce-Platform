// order-tracking.js

document.addEventListener('DOMContentLoaded', function () {

  const trackingInput = document.getElementById('trackingInput');
  const trackBtn      = document.getElementById('trackBtn');
  const trackingError = document.getElementById('trackingError');
  const resultSection = document.getElementById('trackingResult');

  // ── Auto-fill from URL ?order=VLR-XXXX ───────────────────────────
  const params      = new URLSearchParams(window.location.search);
  const orderParam  = params.get('order');
  if (orderParam) {
    trackingInput.value = orderParam.toUpperCase();
    trackOrder(orderParam.toUpperCase());
  }

  // ── Event listeners ───────────────────────────────────────────────
  trackBtn.addEventListener('click', () => {
    const num = trackingInput.value.trim().toUpperCase();
    if (!num) { showError('Please enter an order number.'); return; }
    trackOrder(num);
  });

  trackingInput.addEventListener('keypress', e => {
    if (e.key === 'Enter') trackBtn.click();
  });

  // ── Safe fetch ────────────────────────────────────────────────────
  async function apiCall(url) {
    try {
      const res  = await fetch(url);
      const text = await res.text();
      try { return JSON.parse(text); }
      catch (_) { return { success: false, message: 'Server error. Please try again.' }; }
    } catch (_) {
      return { success: false, message: 'Cannot connect. Please ensure the server is running.' };
    }
  }

  // ── Track order ───────────────────────────────────────────────────
  async function trackOrder(orderNumber) {
    hideError();
    resultSection.style.display = 'none';
    trackBtn.disabled    = true;
    trackBtn.textContent = 'Tracking...';

    const data = await apiCall(`api/orders.php?action=get&order_number=${encodeURIComponent(orderNumber)}`);

    trackBtn.disabled    = false;
    trackBtn.textContent = 'Track Order';

    if (!data.success) {
      showError(data.message === 'Order not found'
        ? `No order found with number "${orderNumber}". Please check and try again.`
        : data.message || 'Something went wrong. Please try again.'
      );
      return;
    }

    renderResult(data.order);
  }

  // ── Render result ─────────────────────────────────────────────────
  function renderResult(order) {
    // Order header
    document.getElementById('resultOrderNumber').textContent = order.orderNumber;
    document.getElementById('resultOrderDate').textContent   = `Placed on ${order.date}`;

    const statusPill = document.getElementById('resultStatusPill');
    statusPill.textContent  = order.status;
    statusPill.className    = `tracking-status-pill status-${order.status}`;

    // Timeline
    renderTimeline(order.trackingSteps, order.status);

    // Admin tracking note
    const noteBox = document.getElementById('trackingNoteBox');
    if (order.trackingNote) {
      document.getElementById('trackingNoteText').textContent    = order.trackingNote;
      document.getElementById('trackingNoteUpdated').textContent =
        order.trackingUpdated ? `Last updated: ${order.trackingUpdated}` : '';
      noteBox.style.display = 'block';
    } else {
      noteBox.style.display = 'none';
    }

    // Shipping address
    const addr = order.shippingAddress;
    document.getElementById('resultShippingAddress').innerHTML =
      `${addr.address || '—'}<br>
       ${addr.city}${addr.state ? ', ' + addr.state : ''} ${addr.pincode || ''}<br>
       ${addr.country || 'India'}`;

    // Items
    const itemsEl = document.getElementById('resultItems');
    itemsEl.innerHTML = (order.items || []).map(item => `
      <div class="tracking-item-row">
        <div>
          <div class="tracking-item-name">${item.name}</div>
          <div class="tracking-item-meta">
            ${item.size  ? 'Size: ' + item.size  : ''}
            ${item.color ? ' · Color: ' + item.color : ''}
            · Qty: ${item.quantity}
          </div>
        </div>
        <div class="tracking-item-price">₹${parseFloat(item.total).toFixed(2)}</div>
      </div>`).join('');

    // Totals
    const gstAmt  = parseFloat(order.gstAmount  || 0);
    const disc    = parseFloat(order.discount    || 0);
    const ship    = parseFloat(order.shipping    || 0);
    const sub     = parseFloat(order.subtotal    || 0);
    const tot     = parseFloat(order.total       || 0);

    document.getElementById('resultTotals').innerHTML = `
      <div class="tracking-totals">
        <div class="tracking-total-row">
          <span>Subtotal (excl. GST)</span>
          <span>₹${sub.toFixed(2)}</span>
        </div>
        ${gstAmt > 0 ? `
        <div class="tracking-total-row">
          <span>GST</span>
          <span>₹${gstAmt.toFixed(2)}</span>
        </div>` : ''}
        ${disc > 0 ? `
        <div class="tracking-total-row" style="color:#27ae60;">
          <span>Discount</span>
          <span>-₹${disc.toFixed(2)}</span>
        </div>` : ''}
        <div class="tracking-total-row">
          <span>Shipping</span>
          <span>${ship === 0 ? 'FREE' : '₹' + ship.toFixed(2)}</span>
        </div>
        <div class="tracking-total-row grand">
          <span>Total</span>
          <span>₹${tot.toFixed(2)}</span>
        </div>
      </div>`;

    // Login note for non-logged-in users
    const loginNote = document.getElementById('loginNote');
    const user = localStorage.getItem('veloraCurrentUser');
    loginNote.style.display = user ? 'none' : 'block';

    // Show the result section
    resultSection.style.display = 'block';
    resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  // ── Render timeline ───────────────────────────────────────────────
  function renderTimeline(steps, currentStatus) {
    const container  = document.getElementById('timelineSteps');
    const progressEl = document.getElementById('timelineProgress');

    const doneCount  = steps.filter(s => s.done).length;
    const totalSteps = steps.length;

    // Progress bar width: span from first to last done step
    const pct = doneCount === 0 ? 0
              : doneCount === totalSteps ? 80
              : ((doneCount - 1) / (totalSteps - 1)) * 80;

    progressEl.style.width = pct + '%';

    // Determine "current active" step (last done that isn't fully delivered)
    const lastDoneIdx = steps.reduce((acc, s, i) => s.done ? i : acc, -1);

    container.innerHTML = steps.map((step, idx) => {
      let cls = '';
      if (step.done && idx < lastDoneIdx)   cls = 'done';
      else if (step.done && idx === lastDoneIdx) cls = currentStatus === 'delivered' ? 'done' : 'done current';
      else cls = '';

      return `
        <div class="timeline-step ${cls}">
          <div class="timeline-step-circle">${step.done ? '✓' : step.icon}</div>
          <div class="timeline-step-label">${step.label}</div>
          <div class="timeline-step-desc">${step.desc || ''}</div>
        </div>`;
    }).join('');
  }

  // ── Error helpers ─────────────────────────────────────────────────
  function showError(msg) {
    trackingError.textContent = msg;
    trackingError.style.display = 'block';
    resultSection.style.display = 'none';
  }

  function hideError() {
    trackingError.textContent   = '';
    trackingError.style.display = 'none';
  }
});
