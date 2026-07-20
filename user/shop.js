// shop.js - Shop page (PHP backend version)

document.addEventListener('DOMContentLoaded', function () {

  // =============================================
  // STATE
  // =============================================
  let allProducts      = [];
  let filteredProducts = [];
  let currentPage      = 1;
  const perPage        = 8;

  let activeFilters = {
    categories: [],
    priceRange: { min: 0, max: 10000 },
    sizes: [],
    colors: []
  };
  let currentSort = 'featured';

  // =============================================
  // DOM
  // =============================================
  const grid             = document.getElementById('shopProductsGrid');
  const productCountSpan = document.getElementById('productCount');
  const sortSelect       = document.getElementById('sortSelect');
  const loadMoreBtn      = document.getElementById('loadMoreBtn');
  const clearFiltersBtn  = document.getElementById('clearFilters');
  const filterToggle     = document.getElementById('filterToggle');
  const closeFiltersBtn  = document.getElementById('closeFilters');
  const filterSidebar    = document.getElementById('filterSidebar');
  const priceMin         = document.getElementById('priceMin');
  const priceMax         = document.getElementById('priceMax');
  const minPriceDisplay  = document.getElementById('minPriceDisplay');
  const maxPriceDisplay  = document.getElementById('maxPriceDisplay');

  // =============================================
  // INIT
  // =============================================
  async function init() {
    updateCartCounter();
    setupEventListeners();

    // Check for URL params (e.g., ?category=denim)
    const params   = new URLSearchParams(window.location.search);
    const catParam = params.get('category');
    if (catParam) {
      const cb = document.querySelector(`.filter-checkbox[value="${catParam}"]`);
      if (cb) { cb.checked = true; activeFilters.categories.push(catParam); }
    }

    await fetchAndDisplay();

    if (typeof window.updateNavFromSession === 'function') {
      window.updateNavFromSession();
    }
  }

  // =============================================
  // FETCH FROM PHP API
  // =============================================
  async function fetchAndDisplay() {
    try {
      const params = new URLSearchParams({ action: 'list', sort: currentSort });

      if (activeFilters.categories.length === 1) {
        params.set('category', activeFilters.categories[0]);
      }
      params.set('min_price', activeFilters.priceRange.min);
      params.set('max_price', activeFilters.priceRange.max);
      if (activeFilters.colors.length === 1) {
        params.set('color', activeFilters.colors[0]);
      }

      const res  = await fetch(`../api/products.php?${params.toString()}`);
      const data = await res.json();

      if (!data.success) throw new Error(data.message);

      allProducts = data.products;

      // Client-side size filter (since size is stored as CSV)
      filteredProducts = allProducts.filter(p => {
        if (activeFilters.sizes.length === 0) return true;
        return activeFilters.sizes.some(s => p.size.includes(s));
      });

      // Multi-category filter (if more than 1 selected, filter client-side)
      if (activeFilters.categories.length > 1) {
        filteredProducts = filteredProducts.filter(p =>
          activeFilters.categories.includes(p.category)
        );
      }

      currentPage = 1;
      renderProducts();
    } catch (err) {
      console.error('Failed to load products:', err);
      grid.innerHTML = '<p class="no-products">Failed to load products. Make sure XAMPP is running.</p>';
    }
  }

  // =============================================
  // RENDER
  // =============================================
  function renderProducts() {
    const total   = filteredProducts.length;
    const visible = filteredProducts.slice(0, currentPage * perPage);

    if (productCountSpan) productCountSpan.textContent = `${total} products`;
    grid.innerHTML = '';

    if (total === 0) {
      grid.innerHTML = '<p class="no-products">No products found. Try adjusting your filters.</p>';
      if (loadMoreBtn) loadMoreBtn.style.display = 'none';
      return;
    }

    visible.forEach(p => grid.appendChild(createProductCard(p)));

    if (loadMoreBtn) {
      loadMoreBtn.style.display = visible.length < total ? 'block' : 'none';
    }
  }

  // =============================================
  // PRODUCT CARD
  // =============================================
  function createProductCard(product) {
    const card = document.createElement('div');
    card.className = 'product-card';

    const imgDiv = document.createElement('div');
    imgDiv.className             = 'product-image';
    imgDiv.style.backgroundImage = product.image;

    if (product.badge) {
      const badge       = document.createElement('div');
      badge.className   = 'product-badge';
      badge.textContent = product.badge;
      imgDiv.appendChild(badge);
    }

    imgDiv.addEventListener('mouseenter', () => { imgDiv.style.backgroundImage = product.hoverImage; });
    imgDiv.addEventListener('mouseleave', () => { imgDiv.style.backgroundImage = product.image; });

    const info = document.createElement('div');
    info.className = 'product-info';

    const cat = document.createElement('div');
    cat.className  = 'product-category';
    cat.textContent = product.category;

    const title = document.createElement('div');
    title.className  = 'product-title';
    title.textContent = product.name;

    const price = document.createElement('div');
    price.className  = 'product-price';
    price.textContent = `₹${product.price}`;

    const addBtn = document.createElement('button');
    addBtn.className  = 'add-to-cart';
    addBtn.textContent = 'add to cart';

    addBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      addToCart(product);
      this.textContent = '✓ added';
      this.classList.add('added');
      setTimeout(() => {
        this.textContent = 'add to cart';
        this.classList.remove('added');
      }, 600);
    });

    info.appendChild(cat);
    info.appendChild(title);
    info.appendChild(price);
    info.appendChild(addBtn);
    card.appendChild(imgDiv);
    card.appendChild(info);

    card.addEventListener('click', () => { window.location.href = `details.php?id=${product.id}`; });

    return card;
  }

  // =============================================
  // CART
  // =============================================
  function addToCart(product) {
    let cart = JSON.parse(localStorage.getItem('veloraCart')) || [];
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
    const ex = cart.find(i => i.id === normalizedProduct.id);
    if (ex) {
      ex.quantity += 1;
      if (!ex.image) ex.image = normalizedProduct.image;
      if (!ex.color && normalizedProduct.color) ex.color = normalizedProduct.color;
      if (!ex.size && normalizedProduct.size) ex.size = normalizedProduct.size;
    } else {
      cart.push(normalizedProduct);
    }
    localStorage.setItem('veloraCart', JSON.stringify(cart));
    updateCartCounter();
  }

  function updateCartCounter() {
    const span = document.getElementById('cartCount');
    if (!span) return;
    const cart  = JSON.parse(localStorage.getItem('veloraCart')) || [];
    span.textContent = cart.reduce((a, i) => a + i.quantity, 0);
  }

  // =============================================
  // FILTERS
  // =============================================
  function handleFilterChange() {
    // Categories
    activeFilters.categories = [];
    document.querySelectorAll('.filter-checkbox:checked').forEach(cb => {
      activeFilters.categories.push(cb.value);
    });

    // Price
    activeFilters.priceRange.min = parseInt(priceMin.value);
    activeFilters.priceRange.max = parseInt(priceMax.value);

    currentPage = 1;
    fetchAndDisplay();
  }

  function handleSizeClick(e) {
    const btn  = e.currentTarget;
    const size = btn.dataset.size;
    btn.classList.toggle('active');

    if (btn.classList.contains('active')) {
      activeFilters.sizes.push(size);
    } else {
      activeFilters.sizes = activeFilters.sizes.filter(s => s !== size);
    }
    fetchAndDisplay();
  }

  function handleColorClick(e) {
    const btn   = e.currentTarget;
    const color = btn.dataset.color;

    document.querySelectorAll('.color-btn').forEach(b => b.classList.remove('active'));
    btn.classList.toggle('active');

    if (btn.classList.contains('active')) {
      activeFilters.colors = [color];
    } else {
      activeFilters.colors = [];
    }
    fetchAndDisplay();
  }

  function updatePriceDisplay() {
    if (minPriceDisplay) minPriceDisplay.textContent = priceMin.value;
    if (maxPriceDisplay) maxPriceDisplay.textContent = priceMax.value;
  }

  function clearAllFilters() {
    document.querySelectorAll('.filter-checkbox').forEach(cb => { cb.checked = false; });
    if (priceMin) { priceMin.value = 0; minPriceDisplay.textContent = '0'; }
    if (priceMax) { priceMax.value = 10000; maxPriceDisplay.textContent = '10000'; }
    document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.color-btn').forEach(b => b.classList.remove('active'));

    activeFilters = { categories: [], priceRange: { min: 0, max: 10000 }, sizes: [], colors: [] };
    currentSort = 'featured';
    if (sortSelect) sortSelect.value = 'featured';
    fetchAndDisplay();
  }

  // =============================================
  // EVENT LISTENERS
  // =============================================
  function setupEventListeners() {
    document.querySelectorAll('.filter-checkbox').forEach(cb => {
      cb.addEventListener('change', handleFilterChange);
    });

    if (priceMin) {
      priceMin.addEventListener('input', updatePriceDisplay);
      priceMin.addEventListener('change', handleFilterChange);
    }
    if (priceMax) {
      priceMax.addEventListener('input', updatePriceDisplay);
      priceMax.addEventListener('change', handleFilterChange);
    }

    document.querySelectorAll('.size-btn').forEach(btn => btn.addEventListener('click', handleSizeClick));
    document.querySelectorAll('.color-btn').forEach(btn => btn.addEventListener('click', handleColorClick));

    if (sortSelect) {
      sortSelect.addEventListener('change', () => {
        currentSort = sortSelect.value;
        fetchAndDisplay();
      });
    }

    if (loadMoreBtn) {
      loadMoreBtn.addEventListener('click', () => {
        currentPage++;
        renderProducts();
      });
    }

    if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', clearAllFilters);

    if (filterToggle) {
      filterToggle.addEventListener('click', () => filterSidebar.classList.add('active'));
    }
    if (closeFiltersBtn) {
      closeFiltersBtn.addEventListener('click', () => filterSidebar.classList.remove('active'));
    }
  }

  init();
});

