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
        button.addEventListener('click', async function() {
            const productId = this.getAttribute('data-product-id');
            await addToCart(productId);
        });
    });
    
    // Buy now buttons
    const buyButtons = document.querySelectorAll('.btn-buy');
    buyButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const productId = this.getAttribute('data-product-id');
            // Try server-side add-to-cart then go to checkout
            const ok = await addToCart(productId, { silent: true });
            if (ok) {
                window.location.href = 'checkout.php';
            } else {
                // Fallback modal for unauthenticated
                openPurchaseModal(productId);
            }
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
async function addToCart(productId, opts = {}) {
    const options = Object.assign({ quantity: 1, silent: false }, opts);
    // If authenticated, use server API for canonical cart
    if (window.IS_AUTH) {
        try {
            const res = await fetch('api/add_to_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: Number(productId), quantity: Number(options.quantity) || 1 })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'No se pudo añadir al carrito');
            // Update header count from server
            try { await refreshCartCountFromServer(); } catch(e) { /* ignore */ }
            if (!options.silent) showNotification('Producto añadido al carrito', 'success');
            return true;
        } catch (e) {
            if (!options.silent) showNotification(e.message, 'error');
            return false;
        }
    }
    // Guest fallback: localStorage cart
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    const idNum = String(productId);
    const existingItem = cart.find(item => String(item.id) === idNum);
    if (existingItem) {
        existingItem.quantity += Number(options.quantity) || 1;
    } else {
        cart.push({ id: idNum, quantity: Number(options.quantity) || 1 });
    }
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartCount();
    if (!options.silent) showNotification('Producto añadido al carrito', 'success');
    return true;
}

function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
    
    const cartCount = document.querySelector('.cart-count');
    if (cartCount) {
        cartCount.textContent = totalItems;
    }
}

async function refreshCartCountFromServer(){
    const res = await fetch('api/get_cart_count.php');
    const data = await res.json();
    if (data && data.success && typeof data.count !== 'undefined'){
        const cartCount = document.querySelector('.cart-count');
        if (cartCount) cartCount.textContent = data.count;
        if (window.updateCartCount) window.updateCartCount(data.count);
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