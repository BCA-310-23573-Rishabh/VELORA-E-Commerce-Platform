// nav.js - Global navigation session manager
// Works with header-right structure (class="header-right")

(async function () {

  // ─── Cart counter ──────────────────────────────────────────────────
  function updateCartCounter() {
    const span = document.getElementById('cartCount');
    if (!span) return;
    const cart = JSON.parse(localStorage.getItem('veloraCart')) || [];
    span.textContent = cart.reduce((a, i) => a + i.quantity, 0);
  }

  const serviceablePincodes = [
    '110001', '560001', '400001', '600001', '500001',
    '700001', '380001', '411001', '302001', '226001',
    '781001', '160001', '600001', '700027'
  ];
  const pinStorageKey = 'veloraPincode';

  function formatPincode(pin) {
    return (pin || '').replace(/[^0-9]/g, '').slice(0, 6);
  }

  function isValidPincode(pin) {
    return /^[1-9][0-9]{5}$/.test(pin);
  }

  function getDeliveryRadius() {
    return localStorage.getItem('deliveryRadius') || 'all-india';
  }

  function getCustomPincodes() {
    const custom = localStorage.getItem('customPincodes');
    if (!custom) return [];
    return custom.split(',').map(p => p.trim()).filter(p => /^[0-9]{6}$/.test(p));
  }

  function getCityForPincode(pin) {
    const cityMap = {
      '110001': 'delhi', '110002': 'delhi', '110003': 'delhi',
      '560001': 'bangalore', '560002': 'bangalore',
      '400001': 'mumbai', '400002': 'mumbai',
      '600001': 'chennai', '600002': 'chennai',
      '500001': 'hyderabad', '500002': 'hyderabad',
      '700001': 'kolkata', '700002': 'kolkata',
      '380001': 'ahmedabad', '380002': 'ahmedabad',
      '411001': 'pune', '411002': 'pune',
      '302001': 'jaipur', '302002': 'jaipur',
      '226001': 'lucknow', '226002': 'lucknow',
      '781001': 'guwahati', '781002': 'guwahati',
      '160001': 'chandigarh', '160002': 'chandigarh'
    };
    return cityMap[pin] || 'unknown';
  }

  function getNearbyStatesPincodes() {
    return {
      'delhi': ['110001', '110002', '110003'],
      'karnataka': ['560001', '560002'],
      'maharashtra': ['400001', '400002', '411001', '411002'],
      'tamil-nadu': ['600001', '600002'],
      'telangana': ['500001', '500002'],
      'west-bengal': ['700001', '700002'],
      'gujarat': ['380001', '380002'],
      'rajasthan': ['302001', '302002'],
      'uttar-pradesh': ['226001', '226002'],
      'assam': ['781001', '781002'],
      'punjab': ['160001', '160002']
    };
  }
  function getStateForPincode(pin) {
    const stateMap = {
      '110001': 'Delhi', '110002': 'Delhi', '110003': 'Delhi',
      '560001': 'Karnataka', '560002': 'Karnataka',
      '400001': 'Maharashtra', '400002': 'Maharashtra',
      '600001': 'Tamil Nadu', '600002': 'Tamil Nadu',
      '500001': 'Telangana', '500002': 'Telangana',
      '700001': 'West Bengal', '700002': 'West Bengal',
      '380001': 'Gujarat', '380002': 'Gujarat',
      '411001': 'Maharashtra', '411002': 'Maharashtra',
      '302001': 'Rajasthan', '302002': 'Rajasthan',
      '226001': 'Uttar Pradesh', '226002': 'Uttar Pradesh',
      '781001': 'Assam', '781002': 'Assam',
      '160001': 'Punjab', '160002': 'Punjab'
    };
    return stateMap[pin] || 'Unknown';
  }

  function getSelectedStates() {
    try {
      return JSON.parse(localStorage.getItem('selectedStates') || '[]');
    } catch (_) {
      return [];
    }
  }
  function isServiceablePincode(pin) {
    const radius = getDeliveryRadius();

    switch (radius) {
      case 'same-city':
        const savedPin = sessionStorage.getItem(pinStorageKey);
        const savedCity = savedPin ? getCityForPincode(savedPin) : null;
        return savedCity && getCityForPincode(pin) === savedCity;

      case 'nearby-states':
        const nearbyMap = getNearbyStatesPincodes();
        return Object.values(nearbyMap).flat().includes(pin);

      case 'select-states':
        const selectedStates = getSelectedStates();
        const pinState = getStateForPincode(pin);
        return selectedStates.includes(pinState);

      case 'all-india':
        return serviceablePincodes.includes(pin);

      case 'custom-pincodes':
        return getCustomPincodes().includes(pin);

      default:
        return serviceablePincodes.includes(pin);
    }
  }

  function savePincode(pin) {
  sessionStorage.setItem(pinStorageKey, pin);
}

  function getSavedPincode() {
  return sessionStorage.getItem(pinStorageKey) || '';
}

  function updatePincodeStatus(pin, serviceable) {
    const topBar = document.querySelector('.top-bar');
    if (!topBar) return;

    let statusEl = topBar.querySelector('.pincode-status');
    if (!statusEl) {
      statusEl = document.createElement('span');
      statusEl.className = 'pincode-status';
      topBar.appendChild(statusEl);
    }

    statusEl.textContent = serviceable
      ? `Delivery available at ${pin}`
      : `Delivery unavailable at ${pin}`;
    statusEl.style.color = serviceable ? '#2d8f47' : '#c93b3b';
  }

  function setPincodeLinkText(pin) {
    const topBar = document.querySelector('.top-bar');
    const link = topBar?.querySelector('.pincode-link');
    if (!link) return;

    if (isValidPincode(pin)) {
      link.textContent = `Pincode: ${pin}`;
      link.title = 'Click to change delivery PIN code';
    } else {
      link.textContent = 'Enter Pincode - to check delivery';
      link.title = 'Enter your delivery PIN code';
    }
  }

  function setupPincodeChecker() {
    const topBar = document.querySelector('.top-bar');
    const link = topBar?.querySelector('.pincode-link');
    if (!link || !topBar) return;

    const savedPin = formatPincode(getSavedPincode());
    setPincodeLinkText(savedPin);

    if (isValidPincode(savedPin)) {
      const available = isServiceablePincode(savedPin);
      updatePincodeStatus(savedPin, available);
    }

    let panel = null;

    function createPincodePanel() {
      const wrapper = document.createElement('div');
      wrapper.className = 'pincode-panel';
      wrapper.innerHTML = `
        <input type="text" class="pincode-input" placeholder="Enter PIN code" maxlength="6" aria-label="Enter PIN code">
        <button type="button" class="pincode-submit">check</button>
      `;
      topBar.appendChild(wrapper);

      const input = wrapper.querySelector('.pincode-input');
      const submit = wrapper.querySelector('.pincode-submit');
      input.value = savedPin;

      function submitPin() {
        const pin = formatPincode(input.value);
        if (!isValidPincode(pin)) {
          window.alert('Please enter a valid 6-digit PIN code.');
          input.focus();
          return;
        }

        savePincode(pin);
        setPincodeLinkText(pin);
        const available = isServiceablePincode(pin);
        updatePincodeStatus(pin, available);

        const checkoutPinField = document.getElementById('checkoutPincode');
        if (checkoutPinField) {
          checkoutPinField.value = pin;
        }

        wrapper.classList.remove('active');
      }

      submit.addEventListener('click', submitPin);
      input.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          submitPin();
        }
      });
      input.addEventListener('input', function () {
        this.value = formatPincode(this.value);
      });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') wrapper.classList.remove('active');
      });

      document.addEventListener('click', function (e) {
        if (!wrapper.contains(e.target) && !link.contains(e.target)) {
          wrapper.classList.remove('active');
        }
      });

      return wrapper;
    }

    link.addEventListener('click', function (e) {
      e.preventDefault();
      if (!panel) panel = createPincodePanel();
      panel.classList.toggle('active');
      const input = panel.querySelector('.pincode-input');
      if (panel.classList.contains('active')) {
        input.focus();
      }
    });
  }

  // ─── Update account icon/link based on session ─────────────────────
  function buildNav(user) {
    // Works with BOTH header structures:
    // 1. class="header-right" (custom pages - account icon link)
    // 2. class="nav" (velora pages - nav link)

    // ── header-right structure ─────────────────────────────────────
    const headerRight = document.querySelector('.header-right');
    if (headerRight) {
      // Find the account link (icon link to login.php)
      let accountLink = headerRight.querySelector('a[href="login.php"], a[href="account.php"], #navAccountLink');

      if (!accountLink) {
        // Create one if it doesn't exist (shouldn't happen but safety net)
        accountLink = headerRight.querySelector('.header-icon');
      }

      if (user) {
        // Logged in — update icon to show name, link to account page
        if (accountLink) {
          accountLink.href  = 'account.php';
          accountLink.title = user.firstName + ' ' + user.lastName;
          accountLink.innerHTML = '<span class="icon">👤</span><span style="font-size:0.75rem;font-weight:500;letter-spacing:0.5px;">' + user.firstName + '</span>';
          accountLink.classList.add('nav-account-pill');
          if (user.isAdmin) {
            // Show admin badge
            let adminBadge = headerRight.querySelector('#navAdminBadge');
            if (!adminBadge) {
              adminBadge           = document.createElement('a');
              adminBadge.id        = 'navAdminBadge';
              adminBadge.href      = 'admin.php';
              adminBadge.className = 'header-icon nav-admin-badge';
              adminBadge.title     = 'Admin Panel';
              adminBadge.innerHTML = '<span class="icon" style="font-size:0.75rem;color:#c9a96e;">ADMIN</span>';
              adminBadge.style.cssText = 'font-size:0.7rem;letter-spacing:1px;text-decoration:none;';
              headerRight.insertBefore(adminBadge, accountLink);
            }
          }
          // Add logout link if not present
          let logoutLink = headerRight.querySelector('#navLogoutLink');
          if (!logoutLink) {
            logoutLink           = document.createElement('a');
            logoutLink.id        = 'navLogoutLink';
            logoutLink.href      = '#';
            logoutLink.className = 'header-icon';
            logoutLink.title     = 'Logout';
            logoutLink.innerHTML = '<span class="icon" style="font-size:0.85rem;">⏻</span>';
            logoutLink.style.cssText = 'cursor:pointer;';
            logoutLink.addEventListener('click', async function(e) {
              e.preventDefault();
              try { await fetch('api/auth.php?action=logout'); } catch(_) {}
              localStorage.removeItem('veloraCurrentUser');
              window.location.href = 'login.php';
            });
            headerRight.appendChild(logoutLink);
          }
        }
      } else {
        // Not logged in — restore login link
        if (accountLink) {
          accountLink.href      = 'login.php';
          accountLink.title     = 'Account';
          accountLink.innerHTML = '<span class="icon">👤</span>';
          accountLink.classList.remove('nav-account-pill');
        }
        headerRight.querySelector('#navAdminBadge')?.remove();
        headerRight.querySelector('#navLogoutLink')?.remove();
      }
    }

    // ── .nav structure (original velora pages) ─────────────────────
    const nav = document.querySelector('.nav');
    if (nav) {
      nav.querySelectorAll('.nav-account, .nav-logout, .nav-admin').forEach(el => el.remove());
      const accountLinkNav = nav.querySelector('a[href="login.php"]');

      if (!user) {
        if (accountLinkNav) accountLinkNav.style.display = '';
        return;
      }

      if (accountLinkNav) accountLinkNav.style.display = 'none';

      if (user.isAdmin) {
        const adminEl     = document.createElement('a');
        adminEl.href      = 'admin.php';
        adminEl.className = 'nav-admin';
        adminEl.textContent = 'admin';
        nav.appendChild(adminEl);
      }

      const nameEl     = document.createElement('a');
      nameEl.href      = 'account.php';
      nameEl.className = 'nav-account';
      nameEl.textContent = '\uD83D\uDC64 ' + user.firstName;
      nav.appendChild(nameEl);

      const logoutEl        = document.createElement('a');
      logoutEl.href         = '#';
      logoutEl.className    = 'nav-logout';
      logoutEl.textContent  = 'logout';
      logoutEl.addEventListener('click', async function(e) {
        e.preventDefault();
        try { await fetch('api/auth.php?action=logout'); } catch(_) {}
        localStorage.removeItem('veloraCurrentUser');
        window.location.href = 'login.php';
      });
      nav.appendChild(logoutEl);
    }
  }

  // ─── Check PHP session ─────────────────────────────────────────────
  async function checkSession() {
    try {
      const res  = await fetch('api/auth.php?action=check');
      const text = await res.text();
      let data;
      try   { data = JSON.parse(text); }
      catch (_) { console.error('nav.js: auth check non-JSON', text.substring(0,200)); buildNav(null); return; }

      if (data.loggedIn && data.user) {
        localStorage.setItem('veloraCurrentUser', JSON.stringify(data.user));
        buildNav(data.user);
      } else {
        localStorage.removeItem('veloraCurrentUser');
        buildNav(null);
      }
    } catch (e) {
      // XAMPP offline — fall back to localStorage
      const stored = localStorage.getItem('veloraCurrentUser');
      if (stored) {
        try { buildNav(JSON.parse(stored)); } catch(_) {}
      }
    }
  }

  // ─── Run ───────────────────────────────────────────────────────────
  function run() {
    updateCartCounter();
    checkSession();
    setupPincodeChecker();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }

  window.updateNavFromSession = checkSession;
  window.buildNav = buildNav;

})();

