// Hide header and top-bar on scroll down, show on scroll up
(function() {
  let lastScrollTop = 0;
  let isHidden = false;
  const header = document.querySelector('.header');
  const topBar = document.querySelector('.top-bar');
  
  if (!header || !topBar) return;
  
  window.addEventListener('scroll', function() {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    // Threshold for when to hide (at least 50px scrolled)
    if (scrollTop > 50) {
      if (scrollTop > lastScrollTop) {
        // Scrolling down - hide header
        if (!isHidden) {
          header.classList.remove('show-header');
          header.classList.add('hide-header');
          topBar.classList.remove('show-header');
          topBar.classList.add('hide-header');
          isHidden = true;
        }
      } else {
        // Scrolling up - show header
        if (isHidden) {
          header.classList.remove('hide-header');
          header.classList.add('show-header');
          topBar.classList.remove('hide-header');
          topBar.classList.add('show-header');
          isHidden = false;
        }
      }
    } else {
      // At top - always show
      header.classList.remove('hide-header');
      header.classList.add('show-header');
      topBar.classList.remove('hide-header');
      topBar.classList.add('show-header');
      isHidden = false;
    }
    
    lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
  }, false);
})();
