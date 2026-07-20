document.addEventListener('DOMContentLoaded', function () {
  const productImage      = document.getElementById('productImage');
  const productName       = document.getElementById('productName');
  const productCategory   = document.getElementById('productCategory');
  const productPrice      = document.getElementById('productPrice');
  const productRatingLabel= document.getElementById('productRatingLabel');
  const productDescription= document.getElementById('productDescription');
  const productBadge      = document.getElementById('productBadge');
  const productThumbnails = document.getElementById('productThumbnails');
  const sizeWrapper       = document.getElementById('sizeWrapper');
  const colorWrapper      = document.getElementById('colorWrapper');
  const sizeOptions       = document.getElementById('sizeOptions');
  const colorOptions      = document.getElementById('colorOptions');
  const qtyMinus          = document.getElementById('qtyMinus');
  const qtyPlus           = document.getElementById('qtyPlus');
  const quantityInput     = document.getElementById('quantityInput');
  const stockInfo         = document.getElementById('stockInfo');
  const addToCartButton   = document.getElementById('detailsAddToCart');
  const reviewsSummary    = document.getElementById('reviewsSummary');
  const reviewsList       = document.getElementById('reviewsList');
  const reviewerName      = document.getElementById('reviewerName');
  const reviewRating      = document.getElementById('reviewRating');
  const reviewStarPicker  = document.getElementById('reviewStarPicker');
  const reviewText        = document.getElementById('reviewText');
  const submitReviewBtn   = document.getElementById('submitReview');
  const reviewMessage     = document.getElementById('reviewMessage');
  const searchInput       = document.getElementById('searchInput');

  let productId          = null;
  let selectedReviewRating = 0;
  let selectedSize       = null;
  let selectedColor      = null;
  let selectedImageUrl   = '';
  let productStock       = 0;
  let productData        = null;

  function getProductId() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
  }

  function showError(text) {
    document.body.innerHTML = `<main class="details-main container"><div class="product-notice"><h1>Product not found</h1><p>${text}</p><a class="add-to-cart detail-add-btn" href="index.php">Back to shop</a></div></main>`;
  }

  function buildDescription(product) {
    const description = product.subcategory ? `Designed for ${product.subcategory}.` : 'Crafted with premium materials for everyday wear.';
    const sizeDesc = product.size?.length ? `Available in sizes: ${product.size.join(', ')}.` : '';
    const colorDesc = product.color ? `Colour: ${product.color}.` : '';
    return `${description} ${sizeDesc} ${colorDesc}`;
  }

  function formatStars(rating) {
    const fullStars = Math.round(rating);
    return '★'.repeat(fullStars) + '☆'.repeat(5 - fullStars);
  }

  function updateReviewStarUI(rating) {
    if (!reviewStarPicker) return;
    reviewStarPicker.querySelectorAll('.star-btn').forEach(btn => {
      const value = parseInt(btn.dataset.value, 10);
      btn.classList.toggle('active', value <= rating);
    });
  }

  function initReviewStarPicker() {
    if (!reviewStarPicker) return;
    reviewStarPicker.innerHTML = '';

    for (let i = 1; i <= 5; i += 1) {
      const star = document.createElement('button');
      star.type = 'button';
      star.className = 'star-btn';
      star.dataset.value = i;
      star.textContent = '★';
      star.setAttribute('aria-label', `${i} star${i > 1 ? 's' : ''}`);

      star.addEventListener('click', () => {
        selectedReviewRating = i;
        reviewRating.value = i;
        updateReviewStarUI(i);
      });
      star.addEventListener('mouseover', () => updateReviewStarUI(i));
      star.addEventListener('mouseout', () => updateReviewStarUI(selectedReviewRating));

      reviewStarPicker.appendChild(star);
    }

    updateReviewStarUI(selectedReviewRating);
  }

  function renderSizes(sizes) {
    if (!sizes || sizes.length === 0) {
      sizeWrapper.style.display = 'none';
      return;
    }
    sizeWrapper.style.display = 'block';
    sizeOptions.innerHTML = '';
    sizes.forEach(size => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'detail-option-btn';
      btn.textContent = size;
      btn.addEventListener('click', () => {
        selectedSize = size;
        sizeOptions.querySelectorAll('.detail-option-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      });
      sizeOptions.appendChild(btn);
    });
  }

  function renderColors(color) {
    if (!color) {
      colorWrapper.style.display = 'none';
      return;
    }
    colorWrapper.style.display = 'block';
    colorOptions.innerHTML = '';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'detail-color-btn';
    btn.title = color;
    btn.style.background = color;
    if (color.toLowerCase() === 'white' || color.toLowerCase() === '#fff' || color.toLowerCase() === 'ivory') {
      btn.style.border = '1px solid #ccc';
    }
    btn.addEventListener('click', () => {
      selectedColor = color;
      colorOptions.querySelectorAll('.detail-color-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
    colorOptions.appendChild(btn);
  }

  function setQuantity(value) {
    const qty = Math.max(1, Math.min(productStock || 999, value));
    quantityInput.value = qty;
    stockInfo.textContent = productStock ? `${productStock} in stock` : 'Stock information unavailable';
  }

  function resolveProductImage(product) {
    if (!product) return '';
    if (product.imageRaw) {
      try {
        const parsed = JSON.parse(product.imageRaw);
        if (Array.isArray(parsed) && parsed.length) return parsed[0];
      } catch (_) {
        return product.imageRaw;
      }
    }
    if (Array.isArray(product.images) && product.images.length) return product.images[0];
    if (typeof product.image === 'string' && product.image.startsWith('url("')) {
      return product.image.slice(5, -2);
    }
    return '';
  }

  function updateCartCounter() {
    const cartCountSpan = document.getElementById('cartCount');
    if (!cartCountSpan) return;
    const cart = JSON.parse(localStorage.getItem('veloraCart')) || [];
    cartCountSpan.textContent = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
  }

  function getProductImagesList(product) {
    if (!product) return [];
    if (Array.isArray(product.images) && product.images.length) return product.images;
    if (product.imageRaw) {
      try {
        const parsed = JSON.parse(product.imageRaw);
        if (Array.isArray(parsed) && parsed.length) return parsed;
      } catch (_) {
        return [product.imageRaw];
      }
    }
    if (typeof product.image === 'string' && product.image.startsWith('url("')) {
      return [product.image.slice(5, -2)];
    }
    return [];
  }

  function renderProductThumbnails(product) {
    if (!productThumbnails) return;
    const images = getProductImagesList(product);
    if (!images.length) {
      productThumbnails.innerHTML = '';
      return;
    }

    productThumbnails.innerHTML = images.map((src, index) => `
      <button type="button" class="product-image-thumb${index === 0 ? ' active' : ''}" data-src="${src}">
        <img src="${src}" alt="${product.name} thumbnail ${index + 1}">
      </button>
    `).join('');

    productThumbnails.querySelectorAll('.product-image-thumb').forEach(btn => {
      btn.addEventListener('click', () => {
        const src = btn.dataset.src;
        if (!src) return;
        selectedImageUrl = src;
        productImage.src = src;
        productThumbnails.querySelectorAll('.product-image-thumb').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      });
    });
  }

  function addToCart() {
    const cart = JSON.parse(localStorage.getItem('veloraCart')) || [];
    const qty = parseInt(quantityInput.value) || 1;
    const item = {
      id: productData.id,
      name: productData.name,
      price: productData.price,
      quantity: qty,
      image: `url("${selectedImageUrl || resolveProductImage(productData)}")`,
      size: selectedSize || '',
      color: selectedColor || productData.color || ''
    };

    const existing = cart.find(i => i.id === item.id && i.size === item.size && i.color === item.color);
    if (existing) {
      existing.quantity += item.quantity;
    } else {
      cart.push(item);
    }
    localStorage.setItem('veloraCart', JSON.stringify(cart));
    updateCartCounter();
    addToCartButton.textContent = '✓ added to cart';
    setTimeout(() => { addToCartButton.textContent = 'add to cart'; }, 900);
  }

  async function loadRatings() {
    try {
      const res = await fetch(`../api/ratings.php?action=get_product&product_id=${productId}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.message);

      const average = data.average || 0;
      const count = data.count || 0;
      productRatingLabel.textContent = `${average || 4.5} / 5 · ${count} review${count !== 1 ? 's' : ''}`;
      reviewsSummary.innerHTML = `<strong>${average || 4.5}</strong> average · ${count} review${count !== 1 ? 's' : ''}`;

      if (count === 0) {
        reviewsList.innerHTML = '<p class="detail-empty">No reviews yet. Be the first to review this product.</p>';
        return;
      }

      reviewsList.innerHTML = data.ratings.map(r => `
        <article class="review-card">
          <div class="review-header">
            <strong>${r.reviewerName}</strong>
            <span>${formatStars(r.rating)}</span>
          </div>
          <p class="review-date">${r.date}${r.isVerified ? ' · verified purchase' : ''}</p>
          <p>${r.review || 'No comment provided.'}</p>
        </article>
      `).join('');
    } catch (err) {
      reviewsSummary.textContent = 'Reviews unavailable.';
      reviewsList.innerHTML = '<p class="detail-empty">Unable to load reviews.</p>';
      console.error(err);
    }
  }

  async function loadProduct() {
    productId = getProductId();
    if (!productId) {
      showError('No product selected.');
      return;
    }

    try {
      const res = await fetch(`../api/products.php?action=get&id=${encodeURIComponent(productId)}`);
      const data = await res.json();
      if (!data.success) {
        showError(data.message || 'Could not load product.');
        return;
      }

      productData = data.product;
      productName.textContent = productData.name;
      productCategory.textContent = productData.category.replace(/-/g, ' ');
      productPrice.textContent = `₹${productData.price}`;
      productDescription.textContent = buildDescription(productData);
      productBadge.textContent = productData.badge || '';
      productBadge.style.display = productData.badge ? 'inline-flex' : 'none';
      productImage.src = resolveProductImage(productData) || '';
      productImage.alt = productData.name;
      productStock = productData.stock || 0;
      setQuantity(1);
      renderSizes(productData.size || []);
      renderColors(productData.color || '');
      await loadRatings();
    } catch (err) {
      console.error(err);
      showError('Unable to retrieve product details.');
    }
  }

  function setupSearch() {
    const btn = document.querySelector('.search-btn');
    if (!btn || !searchInput) return;
    btn.addEventListener('click', () => {
      const query = (searchInput.value || '').trim();
      if (query) window.location.href = `shop.php?q=${encodeURIComponent(query)}`;
    });
    searchInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        const query = (searchInput.value || '').trim();
        if (query) window.location.href = `shop.php?q=${encodeURIComponent(query)}`;
      }
    });
  }

  qtyMinus.addEventListener('click', () => setQuantity(parseInt(quantityInput.value, 10) - 1));
  qtyPlus.addEventListener('click', () => setQuantity(parseInt(quantityInput.value, 10) + 1));
  quantityInput.addEventListener('change', () => setQuantity(parseInt(quantityInput.value, 10)));
  addToCartButton.addEventListener('click', addToCart);

  submitReviewBtn.addEventListener('click', async () => {
    reviewMessage.textContent = '';
    const name = (reviewerName.value || '').trim();
    const rating = parseInt(reviewRating.value, 10);
    const review = (reviewText.value || '').trim();
    if (!name) return reviewMessage.textContent = 'Please enter your name.';
    if (!rating) return reviewMessage.textContent = 'Please choose a rating.';
    if (!review) return reviewMessage.textContent = 'Please write a short review.';

    try {
      const res = await fetch('../api/ratings.php?action=submit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ productId: productId, reviewerName: name, rating, review })
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message);
      reviewMessage.textContent = data.message;
      reviewerName.value = '';
      selectedReviewRating = 0;
      reviewRating.value = '';
      updateReviewStarUI(0);
      reviewText.value = '';
      await loadRatings();
    } catch (err) {
      reviewMessage.textContent = err.message || 'Failed to save review.';
      console.error(err);
    }
  });

  const searchInputField = document.getElementById('searchInput');
  if (searchInputField) setupSearch();
  initReviewStarPicker();
  updateCartCounter();
  loadProduct();
});
