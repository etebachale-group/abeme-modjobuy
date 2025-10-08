// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }
    
    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        if (navMenu && navMenu.classList.contains('active')) {
            if (!navMenu.contains(event.target) && !menuToggle.contains(event.target)) {
                navMenu.classList.remove('active');
            }
        }
    });
    
    // Cart functionality
    const cartIcon = document.querySelector('.cart-icon');
    const cartCount = document.querySelector('.cart-count');
    
    // Load cart from localStorage
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    updateCartCount();
    
    // Add to cart buttons
    const addToCartButtons = document.querySelectorAll('.btn-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            addToCart(productId);
        });
    });
    
    // Buy now buttons
    const buyButtons = document.querySelectorAll('.btn-buy');
    buyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            openPurchaseModal(productId);
        });
    });
    
    // Modal functionality
    const modal = document.getElementById('purchaseModal');
    const closeModal = document.querySelector('.close');
    const confirmPurchase = document.getElementById('confirmPurchase');
    
    if (closeModal) {
        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }
    
    if (confirmPurchase) {
        confirmPurchase.addEventListener('click', function() {
            // Redirect to checkout
            window.location.href = 'checkout.php';
        });
    }
    
    window.addEventListener('click', function(event) {
        if (modal && event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Filter functionality
    const categoryFilter = document.getElementById('categoryFilter');
    const searchFilter = document.getElementById('searchFilter');
    const productCards = document.querySelectorAll('.product-card');
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterProducts);
    }
    
    if (searchFilter) {
        searchFilter.addEventListener('input', filterProducts);
    }
    
    function filterProducts() {
        const categoryValue = categoryFilter ? categoryFilter.value : '';
        const searchValue = searchFilter ? searchFilter.value.toLowerCase() : '';
        
        productCards.forEach(card => {
            const category = card.getAttribute('data-category');
            const productName = card.querySelector('.product-name').textContent.toLowerCase();
            const productDescription = card.querySelector('.product-description').textContent.toLowerCase();
            
            const categoryMatch = !categoryValue || category === categoryValue;
            const searchMatch = !searchValue || 
                productName.includes(searchValue) || 
                productDescription.includes(searchValue);
            
            if (categoryMatch && searchMatch) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
});

// Cart functions
function addToCart(productId) {
    // Load cart from localStorage
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    
    // Check if product already in cart
    const existingItem = cart.find(item => item.id === productId);
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: productId,
            quantity: 1
        });
    }
    
    // Save cart to localStorage
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // Update cart count
    updateCartCount();
    
    // Show notification
    showNotification('Producto añadido al carrito', 'success');
}

function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
    
    const cartCount = document.querySelector('.cart-count');
    if (cartCount) {
        cartCount.textContent = totalItems;
    }
}

// Purchase modal functions
function openPurchaseModal(productId) {
    // Get product details (in a real app, this would come from the server)
    const productCard = document.querySelector(`.product-card[data-product-id="${productId}"]`);
    if (productCard) {
        const productName = productCard.querySelector('.product-name').textContent;
        const productPrice = productCard.querySelector('.product-price').textContent;
        const productImage = productCard.querySelector('.product-image img').src;
        
        const modalDetails = document.getElementById('modalProductDetails');
        if (modalDetails) {
            modalDetails.innerHTML = `
                <div class="modal-product">
                    <img src="${productImage}" alt="${productName}" style="width: 100px; float: left; margin-right: 15px;">
                    <h3>${productName}</h3>
                    <p>Precio: ${productPrice}</p>
                    <p>¿Desea proceder con la compra de este producto?</p>
                </div>
            `;
            
            const modal = document.getElementById('purchaseModal');
            if (modal) {
                modal.style.display = 'block';
            }
        }
    }
}

// Notification function
function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '1000';
    notification.style.maxWidth = '300px';
    
    // Add to document
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Form validation
function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input, textarea, select');
    
    inputs.forEach(input => {
        if (input.hasAttribute('required') && !input.value.trim()) {
            isValid = false;
            input.style.borderColor = 'red';
            
            // Create error message
            const error = document.createElement('div');
            error.className = 'error-message';
            error.textContent = 'Este campo es obligatorio';
            error.style.color = 'red';
            error.style.fontSize = '0.8rem';
            error.style.marginTop = '5px';
            
            // Insert after input
            input.parentNode.insertBefore(error, input.nextSibling);
        } else {
            input.style.borderColor = '';
            
            // Remove existing error message
            const existingError = input.parentNode.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }
        }
    });
    
    return isValid;
}

// Apply form validation to all forms
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
});