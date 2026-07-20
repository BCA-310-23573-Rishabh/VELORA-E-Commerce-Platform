// Carousel Functionality
class Carousel {
  constructor() {
    this.currentSlide = 0;
    this.slides = document.querySelectorAll('.carousel-slide');
    this.indicators = document.querySelectorAll('.indicator');
    this.totalSlides = this.slides.length;
    this.autoPlayInterval = null;
    
    this.init();
  }

  init() {
    if (this.totalSlides === 0) return;

    // Show first slide initially
    this.showSlide(0);

    // Event listeners for navigation buttons
    document.querySelector('.carousel-prev')?.addEventListener('click', () => this.prevSlide());
    document.querySelector('.carousel-next')?.addEventListener('click', () => this.nextSlide());

    // Event listeners for indicators
    this.indicators.forEach((indicator, index) => {
      indicator.addEventListener('click', () => this.goToSlide(index));
    });

    // Auto-play carousel
    this.startAutoPlay();

    // Pause on hover
    document.querySelector('.carousel-container')?.addEventListener('mouseenter', () => this.stopAutoPlay());
    document.querySelector('.carousel-container')?.addEventListener('mouseleave', () => this.startAutoPlay());
  }

  showSlide(n) {
    this.slides.forEach(slide => slide.classList.remove('active'));
    this.indicators.forEach(indicator => indicator.classList.remove('active'));

    this.slides[n].classList.add('active');
    this.indicators[n].classList.add('active');
  }

  nextSlide() {
    this.currentSlide = (this.currentSlide + 1) % this.totalSlides;
    this.showSlide(this.currentSlide);
  }

  prevSlide() {
    this.currentSlide = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
    this.showSlide(this.currentSlide);
  }

  goToSlide(n) {
    this.currentSlide = n;
    this.showSlide(this.currentSlide);
  }

  startAutoPlay() {
    this.autoPlayInterval = setInterval(() => {
      this.nextSlide();
    }, 5000); // Change slide every 5 seconds
  }

  stopAutoPlay() {
    clearInterval(this.autoPlayInterval);
  }
}

// Initialize carousel when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new Carousel();
});
