<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check if user is authenticated
requireAuth();

// Get user's cart items from database
$stmt = $pdo->prepare("SELECT c.id, c.product_id, c.quantity, p.name, p.price, p.image_url, p.is_active FROM cart c LEFT JOIN products p ON c.product_id = p.id WHERE c.user_id = ? ORDER BY c.created_at DESC");
$stmt->execute([currentUserId()]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$notifications = [];
$validCartItems = [];

// Validate cart items
foreach ($cartItems as $item) {
    if (!$item['name'] || !$item['is_active']) {
        // Product does not exist or is inactive, remove from cart
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
        $stmt->execute([$item['id']]);
        $notifications[] = "El producto '{$item['name']}' ya no está disponible y ha sido eliminado de su carrito.";
    } else {
        $validCartItems[] = $item;
    }
}

$cartItems = $validCartItems;

// Calculate total
$total = 0;
foreach ($cartItems as $item) {
    $total += $item['price'] * $item['quantity'];
}

$pageTitle = 'Carrito de Compras';
$cssFiles = ['css/cart.css'];
$jsFiles = ['js/cart.js'];

include 'includes/header.php';
?>

<div class="cart-container">
    <div class="cart-header">
        <h1>Mi Carrito de Compras</h1>
        <p>Revisa y gestiona tus productos seleccionados</p>
    </div>

    <?php if (!empty($notifications)): ?>
        <div class="alert alert-info">
            <?php foreach ($notifications as $notification): ?>
                <p><?php echo $notification; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="cart-content">
        <div class="cart-items">
            <?php if (count($cartItems) > 0): ?>
                <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item" data-item-id="<?php echo $item['id']; ?>">
                        <div class="item-image">
                            <img src="<?php echo $item['image_url']; ?>" alt="<?php echo $item['name']; ?>">
                        </div>
                        <div class="item-details">
                            <h3 class="item-name"><?php echo $item['name']; ?></h3>
                            <p class="item-price">CFA <?php echo number_format($item['price'], 2); ?></p>
                            <div class="item-quantity">
                                <button class="quantity-btn decrease" data-item-id="<?php echo $item['id']; ?>">-</button>
                                <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" min="1" data-item-id="<?php echo $item['id']; ?>">
                                <button class="quantity-btn increase" data-item-id="<?php echo $item['id']; ?>">+</button>
                            </div>
                            <p class="item-total">Total: CFA <?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                        </div>
                        <button class="remove-item" data-item-id="<?php echo $item['id']; ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Tu carrito está vacío</p>
                    <p>No tienes productos en tu carrito de compras.</p>
                    <a href="index.php#products" class="btn-continue">Continuar Comprando</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="cart-summary">
            <h2>Resumen del Pedido</h2>
            <div class="summary-item">
                <span>Subtotal:</span>
                <span>CFA <?php echo number_format($total, 2); ?></span>
            </div>
            <div class="summary-item">
                <span>Envío:</span>
                <span>CFA 0.00</span>
            </div>
            <div class="summary-item">
                <span>Impuestos:</span>
                <span>CFA 0.00</span>
            </div>
            <div class="summary-total">
                <span>Total:</span>
                <span>CFA <?php echo number_format($total, 2); ?></span>
            </div>
            <?php if (count($cartItems) > 0): ?>
                <a href="checkout.php" class="btn-checkout">
                    <i class="fas fa-lock"></i> Proceder al Checkout
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>