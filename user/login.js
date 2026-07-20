// login.js - Login and Registration

document.addEventListener('DOMContentLoaded', function () {

  // ── DOM refs ──────────────────────────────────────────────────────
  const loginToggle    = document.getElementById('loginToggle');
  const registerToggle = document.getElementById('registerToggle');
  const loginFormWrap  = document.getElementById('loginForm');
  const regFormWrap    = document.getElementById('registerForm');
  const loginEmail     = document.getElementById('loginEmail');
  const loginPassword  = document.getElementById('loginPassword');
  const loginSubmit    = document.getElementById('login');
  const firstNameEl    = document.getElementById('firstName');
  const lastNameEl     = document.getElementById('lastName');
  const registerEmail    = document.getElementById('registerEmail');
  const phoneEl          = document.getElementById('registerPhone');
  const registerPassword = document.getElementById('registerPassword');
  const confirmPassEl    = document.getElementById('confirmPassword');
  const registerSubmit   = document.getElementById('register');
  const strengthEl       = document.getElementById('passwordStrength');
  const matchEl          = document.getElementById('passwordMatch');

  // ── Init ──────────────────────────────────────────────────────────
  function init() {
    setupEventListeners();
    redirectIfLoggedIn();
  }

  async function redirectIfLoggedIn() {
    try {
      const res = await fetch('../api/auth.php?action=check');
      if (!res.ok) return;
      const data = await res.json();
      if (data.loggedIn) window.location.href = 'account.php';
    } catch (_) {
      // Server offline or not yet set up — stay on page silently
    }
  }

  // ── Toggle forms ──────────────────────────────────────────────────
  function showLogin() {
    loginToggle.classList.add('active');
    registerToggle.classList.remove('active');
    loginFormWrap.classList.add('active');
    regFormWrap.classList.remove('active');
    clearAllErrors();
  }

  function showRegister() {
    registerToggle.classList.add('active');
    loginToggle.classList.remove('active');
    regFormWrap.classList.add('active');
    loginFormWrap.classList.remove('active');
    clearAllErrors();
  }

  // ── Validation rules ──────────────────────────────────────────────
  const rules = {
    name:    (v) => /^[a-zA-Z\s'\-]{2,50}$/.test(v.trim()),
    email:   (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()),
    phone:   (v) => /^[0-9]{6,15}$/.test(v.trim()),  // Indian mobile
    password:(v) => v.length >= 6
  };

  // ── Error display ─────────────────────────────────────────────────
  function showErr(input, message) {
    if (!input) return;
    input.classList.add('error');
    input.parentElement.querySelector('.field-error-msg')?.remove();
    const span = document.createElement('span');
    span.className   = 'field-error-msg';
    span.textContent = message;
    span.style.cssText = 'color:#c0392b;font-size:0.78rem;display:block;margin-top:0.25rem;font-weight:500;';
    input.parentElement.appendChild(span);
  }

  function clearErr(input) {
    if (!input) return;
    input.classList.remove('error');
    input.parentElement.querySelector('.field-error-msg')?.remove();
  }

  function clearAllErrors() {
    document.querySelectorAll('.field-error-msg').forEach(el => el.remove());
    document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
  }

  // ── Safe API call ─────────────────────────────────────────────────
  // Always returns a data object — never throws, never shows generic alert
  async function apiCall(url, options) {
    try {
      const res  = await fetch(url, options);
      const text = await res.text();
      try {
        return JSON.parse(text);
      } catch (_) {
        // PHP returned a non-JSON response (fatal error etc.)
        console.error('Server response (non-JSON):', text.substring(0, 400));
        return {
          success: false,
          message: 'A server error occurred. Please visit http://localhost/velora/test.php to diagnose the issue.'
        };
      }
    } catch (_) {
      return {
        success: false,
        message: 'Unable to connect to the server. Please ensure Apache and MySQL are both running in XAMPP.'
      };
    }
  }

  // ── Login handler ─────────────────────────────────────────────────
  async function handleLogin(e) {
    e.preventDefault();
    clearAllErrors();

    const email    = loginEmail.value.trim();
    const password = loginPassword.value;
    let   isValid  = true;

    if (!email) {
      showErr(loginEmail, 'Email address is required.');
      isValid = false;
    } else if (!rules.email(email)) {
      showErr(loginEmail, 'Please enter a valid email address.');
      isValid = false;
    }

    if (!password) {
      showErr(loginPassword, 'Password is required.');
      isValid = false;
    }

    if (!isValid) return;

    const btn  = loginSubmit.querySelector('button[type="submit"]') || loginSubmit;
    const orig = btn.textContent;
    btn.textContent = 'Signing in...';
    btn.disabled    = true;

    const data = await apiCall('../api/auth.php?action=login', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ email, password })
    });

    if (data.success) {
      localStorage.setItem('veloraCurrentUser', JSON.stringify(data.user));
      if (typeof window.buildNav === 'function') window.buildNav(data.user);
      window.location.href = 'account.php';
    } else {
      showErr(loginPassword, data.message || 'Incorrect email or password.');
      btn.textContent = orig;
      btn.disabled    = false;
    }
  }

  // ── Register handler ──────────────────────────────────────────────
  async function handleRegister(e) {
    e.preventDefault();
    clearAllErrors();

    const firstName = firstNameEl?.value.trim()    || '';
    const lastName  = lastNameEl?.value.trim()     || '';
    const email     = registerEmail?.value.trim()  || '';
    const phone     = phoneEl?.value.trim()        || '';
    const password  = registerPassword?.value      || '';
    const confirm   = confirmPassEl?.value         || '';

    let isValid = true;

    if (!firstName) {
      showErr(firstNameEl, 'First name is required.');
      isValid = false;
    } else if (!rules.name(firstName)) {
      showErr(firstNameEl, 'First name can only contain letters — no numbers or special characters.');
      isValid = false;
    }

    if (!lastName) {
      showErr(lastNameEl, 'Last name is required.');
      isValid = false;
    } else if (!rules.name(lastName)) {
      showErr(lastNameEl, 'Last name can only contain letters — no numbers or special characters.');
      isValid = false;
    }

    if (!email) {
      showErr(registerEmail, 'Email address is required.');
      isValid = false;
    } else if (!rules.email(email)) {
      showErr(registerEmail, 'Please enter a valid email address (e.g. name@gmail.com).');
      isValid = false;
    }

    if (phone && !rules.phone(phone)) {
      showErr(phoneEl, 'Mobile number must be 10 digits and start with 6, 7, 8 or 9.');
      isValid = false;
    }

    if (!password) {
      showErr(registerPassword, 'Password is required.');
      isValid = false;
    } else if (!rules.password(password)) {
      showErr(registerPassword, 'Password must be at least 6 characters.');
      isValid = false;
    }

    if (!confirm) {
      showErr(confirmPassEl, 'Please confirm your password.');
      isValid = false;
    } else if (password !== confirm) {
      showErr(confirmPassEl, 'Passwords do not match.');
      isValid = false;
    }

    if (!isValid) return;

    const btn  = registerSubmit.querySelector('button[type="submit"]') || registerSubmit;
    const orig = btn.textContent;
    btn.textContent = 'Creating account...';
    btn.disabled    = true;

    const data = await apiCall('../api/auth.php?action=register', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ firstName, lastName, email, phone, password })
    });

    if (data.success) {
      localStorage.setItem('veloraCurrentUser', JSON.stringify(data.user));
      if (typeof window.buildNav === 'function') window.buildNav(data.user);
      window.location.href = 'account.php';
    } else {
      const msg = data.message || 'Registration failed. Please try again.';
      if (msg.toLowerCase().includes('email')) showErr(registerEmail, msg);
      else { showErr(registerPassword, msg); }
      btn.textContent = orig;
      btn.disabled    = false;
    }
  }

  // ── Real-time field validation ────────────────────────────────────
  function setupRealTimeValidation() {

    // Name fields: strip numbers/special characters as user types
    [firstNameEl, lastNameEl].filter(Boolean).forEach(input => {
      input.addEventListener('input', function () {
        this.value = this.value.replace(/[^a-zA-Z\s'\-]/g, '');
        if (this.value.trim().length >= 2) clearErr(this);
      });
    });

    // Phone: digits only, max 10
    if (phoneEl) {
      phoneEl.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        if (rules.phone(this.value)) clearErr(this);
      });
    }

    // Email: validate on blur
    if (registerEmail) {
      registerEmail.addEventListener('blur', function () {
        if (this.value && !rules.email(this.value)) {
          showErr(this, 'Please enter a valid email address.');
        } else if (this.value) {
          clearErr(this);
        }
      });
    }

    // Password strength meter
    if (registerPassword) {
      registerPassword.addEventListener('input', function () {
        updateStrengthMeter(this.value);
        updateMatchStatus();
        if (rules.password(this.value)) clearErr(this);
      });
    }

    if (confirmPassEl) {
      confirmPassEl.addEventListener('input', updateMatchStatus);
    }
  }

  // ── Password strength meter ───────────────────────────────────────
  function updateStrengthMeter(password) {
    if (!strengthEl) return;
    const bars = strengthEl.querySelectorAll('.strength-bar');
    bars.forEach(b => { b.className = 'strength-bar'; });
    if (!password) return;

    let score = 0;
    if (password.length >= 6)  score++;
    if (password.length >= 10) score++;
    if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    if (score <= 2)      { bars[0].classList.add('weak'); }
    else if (score <= 3) { bars[0].classList.add('medium'); bars[1].classList.add('medium'); }
    else                 { bars.forEach(b => b.classList.add('strong')); }
  }

  // ── Password match indicator ──────────────────────────────────────
  function updateMatchStatus() {
    if (!matchEl) return;
    const pass    = registerPassword?.value || '';
    const confirm = confirmPassEl?.value    || '';
    if (!confirm) {
      matchEl.textContent = '';
      matchEl.className   = 'password-match';
      return;
    }
    if (pass === confirm) {
      matchEl.textContent = '✓ Passwords match';
      matchEl.className   = 'password-match match';
      clearErr(confirmPassEl);
    } else {
      matchEl.textContent = '✗ Passwords do not match';
      matchEl.className   = 'password-match error';
    }
  }

  // ── Event listeners ───────────────────────────────────────────────
  function setupEventListeners() {
    loginToggle?.addEventListener('click', showLogin);
    registerToggle?.addEventListener('click', showRegister);
    loginSubmit?.addEventListener('submit', handleLogin);
    registerSubmit?.addEventListener('submit', handleRegister);
    setupRealTimeValidation();

    document.querySelector('.forgot-link')?.addEventListener('click', e => {
      e.preventDefault();
      window.location.href = 'reset-password.php';
    });
  }

  init();
});


