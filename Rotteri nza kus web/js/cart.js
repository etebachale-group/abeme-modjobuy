document.addEventListener('DOMContentLoaded', function() {
    // Function to update cart count in the header
    function updateCartCount() {
        fetch('api/get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount) {
                        cartCount.textContent = data.count;
                    }
                }
            });
    }

    // Function to update the totals in the cart summary
    function updateCartSummary() {
        fetch('api/get_cart_summary.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector('.summary-item span:last-child').textContent = 'CFA ' + data.subtotal.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.querySelector('.summary-total span:last-child').textContent = 'CFA ' + data.total.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
            });
    }

    // Initial cart count update
    updateCartCount();

    // Event delegation for quantity buttons
    document.querySelector('.cart-items').addEventListener('click', function(e) {
        if (e.target.classList.contains('quantity-btn')) {
            const itemId = e.target.getAttribute('data-item-id');
            const input = document.querySelector(`.quantity-input[data-item-id="${itemId}"]`);
            let quantity = parseInt(input.value);

            if (e.target.classList.contains('increase')) {
                quantity++;
            } else if (e.target.classList.contains('decrease') && quantity > 1) {
                quantity--;
            }

            updateCartItemQuantity(itemId, quantity, input);
        }
    });

    // Event delegation for remove buttons
    document.querySelector('.cart-items').addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item') || e.target.parentElement.classList.contains('remove-item')) {
            const button = e.target.closest('.remove-item');
            const itemId = button.getAttribute('data-item-id');
            
            if (confirm('¿Estás seguro de que quieres eliminar este producto del carrito?')) {
                removeCartItem(itemId);
            }
        }
    });

    function updateCartItemQuantity(itemId, quantity, input) {
        fetch('api/update_cart_item.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId,
                quantity: quantity
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                input.value = quantity;
                // Update item total and summary without reloading
                const itemTotal = document.querySelector(`.cart-item[data-item-id="${itemId}"] .item-total`);
                const itemPrice = parseFloat(document.querySelector(`.cart-item[data-item-id="${itemId}"] .item-price`).textContent.replace('CFA ', '').replace(',', ''));
                itemTotal.textContent = 'Total: CFA ' + (itemPrice * quantity).toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                updateCartSummary();
                updateCartCount();
            } else {
                alert('Error al actualizar la cantidad: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al actualizar la cantidad');
        });
    }

    function removeCartItem(itemId) {
        fetch('api/remove_from_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cartItem = document.querySelector(`.cart-item[data-item-id="${itemId}"]`);
                if (cartItem) {
                    cartItem.remove();
                }
                updateCartSummary();
                updateCartCount();

                // Check if cart is empty
                const remainingItems = document.querySelectorAll('.cart-item');
                if (remainingItems.length === 0) {
                    document.querySelector('.cart-content').innerHTML = `
                        <div class="empty-cart" style="grid-column: 1 / -1;">
                            <i class="fas fa-shopping-cart"></i>
                            <p>Tu carrito está vacío</p>
                            <p>No tienes productos en tu carrito de compras.</p>
                            <a href="index.php#products" class="btn-continue">Continuar Comprando</a>
                        </div>`;
                }
            } else {
                alert('Error al eliminar el producto: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar el producto');
        });
    }
});
