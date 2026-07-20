// theme.js — improved dark/light mode system

(function () {

  const STORAGE_KEY = 'veloraTheme';

  const LIGHT_CSS = `
    :root {
      --bg-base: #f7f5f2 !important;
      --bg-surface: #ffffff !important;
      --bg-elevated: #f0ece6 !important;
      --bg-hover: #e8e3dc !important;

      --border: rgba(0,0,0,0.07) !important;
      --border-light: rgba(0,0,0,0.12) !important;

      --text-primary: #1a1a1a !important;
      --text-secondary: #5a5040 !important;
      --text-muted: #8a7d70 !important;

      --gold: #8b6f4c !important;
      --gold-light: #b8956a !important;
      --gold-dark: #6b4f34 !important;
    }

    html,
    body {
      background: #f7f5f2 !important;
      color: #1a1a1a !important;
    }

    .header,
    .navbar,
    .top-bar,
    .footer,
    .shop-main,
    .shop-container,
    .products-wrapper,
    .filter-sidebar,
    .order-card,
    .order-items-card,
    .tracking-container,
    .checkout-container,
    .success-container,
    .success-card,
    .cart-container,
    .cart-item,
    .product-card,
    .details-container,
    .account-container {
      background: #ffffff !important;
      color: #1a1a1a !important;
    }

    .header {
      border-bottom: 1px solid rgba(0,0,0,0.1) !important;
    }

    input,
    textarea,
    select {
      background: #ffffff !important;
      color: #1a1a1a !important;
      border: 1px solid rgba(0,0,0,0.15) !important;
    }

    button {
      color: inherit;
    }

    a,
    p,
    h1,
    h2,
    h3,
    h4,
    h5,
    h6,
    span,
    div,
    label {
      color: inherit;
    }
      /* Price range text fix */

.price-range,
.price-label,
.range-value,
.filter-title,
.filter-group label {
  color: #1a1a1a !important;
}

/* Slider fix */

input[type="range"] {
  -webkit-appearance: none;
  width: 100%;
  height: 6px;
  border-radius: 10px;
  background: #d6d0c8 !important;
  outline: none;
}

/* Chrome slider thumb */

input[type="range"]::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #8b6f4c !important;
  cursor: pointer;
  border: none;
}

/* Firefox */

input[type="range"]::-moz-range-thumb {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #8b6f4c !important;
  cursor: pointer;
  border: none;
}
  `;

 const DARK_CSS = `
  html,
  body {
    background: #0c0c0c !important;
    color: #f0ebe3 !important;
  }

  /* Price range text */

  .price-range,
  .price-label,
  .range-value,
  .filter-title,
  .filter-group label,
  .filter-sidebar,
  .filter-sidebar * {
    color: #f0ebe3 !important;
  }

  /* Range slider track */

  input[type="range"] {
    -webkit-appearance: none;
    width: 100%;
    height: 6px;
    border-radius: 10px;
    background: #3a3a3a !important;
    outline: none;
  }

  /* Chrome thumb */

  input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #c9a96e !important;
    cursor: pointer;
    border: none;
  }

  /* Firefox thumb */

  input[type="range"]::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #c9a96e !important;
    cursor: pointer;
    border: none;
    border: none;
  }
`;

  const styleTag = document.createElement('style');
  styleTag.id = 'velora-theme-style';
  document.head.appendChild(styleTag);

  function applyTheme(theme) {

    if (theme === 'light') {
      styleTag.textContent = LIGHT_CSS;
      document.documentElement.setAttribute('data-theme', 'light');
      document.documentElement.style.colorScheme = 'light';
    } else {
      styleTag.textContent = DARK_CSS;
      document.documentElement.removeAttribute('data-theme');
      document.documentElement.style.colorScheme = 'dark';
    }
  }

  const savedTheme = localStorage.getItem(STORAGE_KEY) || 'dark';
  applyTheme(savedTheme);

  function buildToggle() {

    if (document.getElementById('themeToggle')) return;

    const button = document.createElement('button');

    button.id = 'themeToggle';
    button.innerHTML = savedTheme === 'dark' ? '☀' : '☾';

    button.style.cssText = `
      background: transparent;
      border: 1px solid rgba(255,255,255,0.2);
      color: #c9a96e;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      cursor: pointer;
      margin-left: 15px;
      transition: 0.3s ease;
      font-size: 18px;
    `;

    button.addEventListener('click', function () {

      const current = localStorage.getItem(STORAGE_KEY) || 'dark';
      const next = current === 'dark' ? 'light' : 'dark';

      localStorage.setItem(STORAGE_KEY, next);

      applyTheme(next);

      button.innerHTML = next === 'dark' ? '☀' : '☾';
    });

    const navbar = document.querySelector('.navbar .container');

    if (navbar) {
      navbar.appendChild(button);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildToggle);
  } else {
    buildToggle();
  }

})();