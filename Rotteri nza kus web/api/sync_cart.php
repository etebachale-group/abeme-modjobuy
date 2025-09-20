<?php
require_once '../includes/db.php';
require_once '../includes/auth.php'; // For currentUserId()

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'items' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $clientCartItems = $input['items'] ?? [];

    if (!isAuthenticated()) {
        $response['message'] = 'Usuario no autenticado.';
        echo json_encode($response);
        exit();
    }

    $userId = currentUserId();
    $validatedCart = [];

    try {
        // Start a transaction for atomicity
        $pdo->beginTransaction();

        // Clear existing cart for the user on the server
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);

        foreach ($clientCartItems as $item) {
            $productId = $item['id'] ?? null;
            $quantity = $item['quantity'] ?? null;

            // Basic validation
            if (!is_numeric($productId) || $productId <= 0 || !is_numeric($quantity) || $quantity <= 0) {
                // Skip invalid items, or add a specific error message for them
                continue;
            }

            // Fetch product details from database to validate price, active status
            // Note: 'stock' may not exist in schema; handle gracefully if null
            $stmt = $pdo->prepare("SELECT id, name, price, image_url, is_active, /* optional */ NULL as stock FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product || !$product['is_active']) {
                // Product not found or inactive, skip it
                continue;
            }

            // Validate stock if available in schema (null means no stock control)
            $available = isset($product['stock']) && $product['stock'] !== null ? (int)$product['stock'] : null;
            if ($available !== null) {
                if ($available < $quantity) {
                    $quantity = $available;
                    if ($quantity <= 0) {
                        continue; // Skip if no stock available
                    }
                }
            }

            // Insert/Update item in server-side cart
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $productId, $quantity]);

            // Add validated item to the response
            $validatedCart[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'], // Use server-side price
                'image_url' => $product['image_url'],
                'quantity' => $quantity
            ];
        }

        $pdo->commit(); // Commit the transaction

        $response['success'] = true;
        $response['message'] = 'Carrito sincronizado exitosamente.';
        $response['items'] = $validatedCart;

    } catch (PDOException $e) {
        $pdo->rollBack(); // Rollback on error
        $response['message'] = 'Error de base de datos al sincronizar el carrito: ' . $e->getMessage();
    } catch (Exception $e) {
        $pdo->rollBack(); // Rollback on error
        $response['message'] = 'Error interno del servidor al sincronizar el carrito.';
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
?>