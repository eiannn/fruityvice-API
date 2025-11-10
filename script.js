// Propessionallll FruitasInforr ya
document.addEventListener('DOMContentLoaded', function() {
    console.log('FruitInfo: Initializing professional application...');
    initApp();
});

function initApp() {
    setupEventListeners();
    initScrollAnimations();
    createScrollProgressBar();
    createBackToTopButton();
    checkSelectedFruit();
    initFruitGridAnimations();
}

function setupEventListeners() {
    // dire ka maka serch ug formmm submissssion ya
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const searchInput = document.getElementById('searchInput');
            const searchValue = searchInput.value.trim();
            
            if (searchValue === '') {
                window.location.href = '?category=all';
            } else {
                showLoadingState();
                this.submit();
            }
        });
    }
    
    // Navvvv na buttons loadingggg sssstatteee
    const navButtons = document.querySelectorAll('.nav-btn');
    navButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.classList.contains('active')) {
                showLoadingState();
            }
        });
    });
}

function selectFruit(fruitName) {
    const url = new URL(window.location);
    const currentFruit = url.searchParams.get('fruit');
    const currentShowOnly = url.searchParams.get('showOnly');
    
    if (currentFruit === fruitName && currentShowOnly === 'true') {
        showAllFruits();
    } else {
        url.searchParams.set('fruit', fruitName);
        url.searchParams.set('showOnly', 'true');
        showLoadingState();
        setTimeout(() => {
            window.location.href = url.toString();
        }, 300);
    }
}

function showAllFruits() {
    const url = new URL(window.location);
    url.searchParams.delete('fruit');
    url.searchParams.delete('showOnly');
    showLoadingState();
    setTimeout(() => {
        window.location.href = url.toString();
    }, 300);
}

function checkSelectedFruit() {
    const urlParams = new URLSearchParams(window.location.search);
    const selectedFruit = urlParams.get('fruit');
    const showOnly = urlParams.get('showOnly');
    
    if (selectedFruit && showOnly === 'true') {
        setTimeout(() => {
            const selectedCard = document.querySelector(`[data-fruit-name="${selectedFruit}"]`);
            if (selectedCard) {
                selectedCard.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
        }, 500);
    }
}

function initScrollAnimations() {
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const animatedElements = document.querySelectorAll('.fruit-grid-item');
    animatedElements.forEach(el => {
        observer.observe(el);
    });
}

function initFruitGridAnimations() {
    const fruitCards = document.querySelectorAll('.fruit-card');
    fruitCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, (index % 8) * 100);
    });
}

function createScrollProgressBar() {
    // mao ni pragggress barrrr ya na is alreadyyyy  na naa na sa HTML ya
}

function updateProgressBar() {
    const progressBar = document.querySelector('.scroll-progress');
    if (!progressBar) return;

    const windowHeight = window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight - windowHeight;
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    const scrollPercentage = (scrollTop / documentHeight) * 100;
    progressBar.style.width = `${scrollPercentage}%`;
}

function createBackToTopButton() {
    const backToTopBtn = document.querySelector('.back-to-top');
    if (!backToTopBtn) return;

    backToTopBtn.addEventListener('click', scrollToTop);

    window.addEventListener('scroll', throttle(() => {
        const scrolled = window.pageYOffset;
        if (scrolled > 300) {
            backToTopBtn.classList.add('visible');
        } else {
            backToTopBtn.classList.remove('visible');
        }
        
        updateProgressBar();
    }, 100));
}

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

function showLoadingState() {
    const fruitGrid = document.getElementById('fruitGrid');
    const loadingElement = document.getElementById('loading');
    
    if (fruitGrid && loadingElement) {
        fruitGrid.style.opacity = '0.5';
        loadingElement.style.display = 'block';
        loadingElement.classList.add('show');
    }
}

// Utiiilityyyy fuuuunctionnnn ttto throooottleee sssscroll eventsss ya
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// maka himo ug punctions tibook kalibotan avelableeee
window.selectFruit = selectFruit;
window.showAllFruits = showAllFruits;

// selpon meno punctionality
const mobileToggle = document.querySelector('.mobile-menu-toggle');
const navContainer = document.querySelector('.nav-container');
const mobileOverlay = document.querySelector('.mobile-overlay');

if (mobileToggle && navContainer && mobileOverlay) {
    mobileToggle.addEventListener('click', function() {
        navContainer.classList.toggle('mobile-open');
        mobileOverlay.classList.toggle('active');
    });

    mobileOverlay.addEventListener('click', function() {
        navContainer.classList.remove('mobile-open');
        mobileOverlay.classList.remove('active');
    });
}



