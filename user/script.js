// script.js - Homepage (PHP backend version)

document.addEventListener('DOMContentLoaded', function () {
  const grids = {
    essential: document.getElementById('essentialGrid'),
    denim:     document.getElementById('denimGrid'),
    outerwear: document.getElementById('outerwearGrid'),
    linen:     document.getElementById('linenGrid')
  };

  const cartCountSpan = document.getElementById('cartCount');
  let   cart          = JSON.parse(localStorage.getItem('veloraCart')) || [];

  // =============================================
  // SEARCH SETUP - DO THIS FIRST
  // =============================================
  (function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.querySelector('.search-btn');

    if (searchBtn) {
      searchBtn.addEventListener('click', function() {
        const query = (searchInput?.value || '').trim();
        if (query) {
          window.location.href = 'shop.php?q=' + encodeURIComponent(query);
        }
      });
    }

    if (searchInput) {
      searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          const query = (searchInput.value || '').trim();
          if (query) {
            window.location.href = 'shop.php?q=' + encodeURIComponent(query);
          }
        }
      });
    }
  })();

  // =============================================
  // INIT
  // =============================================
  async function init() {
    updateCartCounter();
    await loadProductsFromDB();
    setupCartButton();

    // Update nav with session state
    if (typeof window.updateNavFromSession === 'function') {
      window.updateNavFromSession();
    }
  }

  // =============================================
  // LOAD PRODUCTS FROM PHP API
  // =============================================
  async function loadProductsFromDB() {
    try {
      const res  = await fetch('../api/products.php?action=list');
      const data = await res.json();

      if (!data.success) throw new Error(data.message);

      // Group by category
      const grouped = { essential: [], denim: [], outerwear: [], linen: [] };
      data.products.forEach(p => {
        if (grouped[p.category] !== undefined) {
          grouped[p.category].push(p);
        }
      });

      // Render first 4 per category on homepage
      Object.keys(grids).forEach(cat => {
        if (grids[cat]) {
          grids[cat].innerHTML = '';
          grouped[cat].slice(0, 4).forEach(prod => {
            grids[cat].appendChild(createProductCard(prod));
          });
          setupScrollAnimations();
        }
      });
    } catch (err) {
      console.error('Failed to load products:', err);
      // Fallback: show placeholder message
      Object.values(grids).forEach(grid => {
        if (grid) {
          grid.innerHTML = '<p style="padding:1rem;color:#999;">Products loading... make sure XAMPP is running.</p>';
        }
      });
    }
  }

  // =============================================
  // PRODUCT CARD
  // =============================================
  function createProductCard(product) {
    const card   = document.createElement('div');
    card.className = 'product-card';

    const imgDiv = document.createElement('div');
    imgDiv.className            = 'product-image';
    imgDiv.style.backgroundImage = product.image;

    if (product.badge) {
      const badge       = document.createElement('div');
      badge.className   = 'product-badge';
      badge.textContent = product.badge;
      imgDiv.appendChild(badge);
    }

    imgDiv.addEventListener('mouseenter', () => { imgDiv.style.backgroundImage = product.hoverImage; });
    imgDiv.addEventListener('mouseleave', () => { imgDiv.style.backgroundImage = product.image; });

    const info       = document.createElement('div');
    info.className   = 'product-info';

    const title      = document.createElement('div');
    title.className  = 'product-title';
    title.textContent = product.name;

    const price      = document.createElement('div');
    price.className  = 'product-price';
    price.textContent = `₹${product.price}`;

    const addBtn = document.createElement('button');
    addBtn.className  = 'add-to-cart';
    addBtn.textContent = 'add to cart';

    addBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      addToCart(product);
      this.textContent            = '✓ added';
      this.style.background       = '#4caf50';
      this.style.color            = 'white';
      this.style.borderColor      = '#4caf50';
      setTimeout(() => {
        this.textContent       = 'add to cart';
        this.style.background  = '';
        this.style.color       = '';
        this.style.borderColor = '';
      }, 800);
    });

    card.addEventListener('click', () => { window.location.href = `details.php?id=${product.id}`; });

    info.appendChild(title);
    info.appendChild(price);
    info.appendChild(addBtn);
    card.appendChild(imgDiv);
    card.appendChild(info);

    return card;
  }

  // =============================================
  // CART
  // =============================================
  function addToCart(product) {
    const normalizedProduct = {
      id: product.id,
      name: product.name,
      price: Number(product.price) || 0,
      quantity: 1,
      image: product.image || product.imageRaw || product.images?.[0] || '',
      hoverImage: product.hoverImage || product.image || product.imageRaw || '',
      size: product.size?.[0] || product.size || '',
      color: product.color || '',
      category: product.category || '',
      badge: product.badge || ''
    };

    const existing = cart.find(i => i.id === normalizedProduct.id);
    if (existing) {
      existing.quantity += 1;
      if (!existing.image) existing.image = normalizedProduct.image;
      if (!existing.color && normalizedProduct.color) existing.color = normalizedProduct.color;
      if (!existing.size && normalizedProduct.size) existing.size = normalizedProduct.size;
    } else {
      cart.push(normalizedProduct);
    }
    localStorage.setItem('veloraCart', JSON.stringify(cart));
    updateCartCounter();
  }

  function updateCartCounter() {
    if (!cartCountSpan) return;
    const total = cart.reduce((acc, i) => acc + i.quantity, 0);
    cartCountSpan.textContent = total;
  }

  function setupCartButton() {
    const cartBtn = document.querySelector('.cart-btn');
    if (cartBtn) {
      cartBtn.addEventListener('click', () => {
        window.location.href = 'cart.php';
      });
    }
  }

  // =============================================
  // SCROLL ANIMATIONS
  // =============================================
  function setupScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity   = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('.product-card').forEach(card => {
      card.style.opacity    = '0';
      card.style.transform  = 'translateY(20px)';
      card.style.transition = 'opacity 0.6s, transform 0.6s';
      observer.observe(card);
    });
  }

  init();
});

// =============================================
// GLOBAL HELPERS
// =============================================
window.addToCart = function (id, name, price) {
  let cart = JSON.parse(localStorage.getItem('veloraCart')) || [];
  const ex = cart.find(i => i.id === id);
  if (ex) { ex.quantity += 1; } else { cart.push({ id, name, price, quantity: 1 }); }
  localStorage.setItem('veloraCart', JSON.stringify(cart));

  const span = document.getElementById('cartCount');
  if (span) span.textContent = cart.reduce((a, i) => a + i.quantity, 0);
};

window.clearCart = function () {
  if (!confirm('Clear your entire cart?')) return;
  localStorage.removeItem('veloraCart');
  const span = document.getElementById('cartCount');
  if (span) span.textContent = '0';
  if (window.location.pathname.includes('cart.php')) window.location.reload();
};
