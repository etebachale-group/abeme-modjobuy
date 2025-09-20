<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Solo verificar que el usuario esté autenticado
requireAdmin();

// ---- Procesamiento de Formularios ----

// Mensajes de éxito y error
$success = '';
$error = '';

// Procesar formulario de nuevo envío

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shipment'])) {
    
    try {
        // Lógica de precios
        $price_per_kg = 6500; // Precio por kilo en Fcfa

        $weight = floatval($_POST['weight']);
        $total_price = $weight * $price_per_kg;
        
        // Calcular el descuento si se proporciona
        $discount_percentage = isset($_POST['discount_percentage']) ? floatval($_POST['discount_percentage']) : 0;
        $discount_amount = 0;
        $sale_price = $total_price;
        
        if ($discount_percentage > 0 && $discount_percentage <= 100) {
            $discount_amount = $total_price * ($discount_percentage / 100);
            $sale_price = $total_price - $discount_amount;
        }
        
        $shipping_cost = 0; // Ya no usamos costo de envío separado
        $profit = 0; // Ya no calculamos ganancia

        $code = generateShipmentCode($pdo);
        $ship_date = isset($_POST['ship_date']) ? $_POST['ship_date'] : date('Y-m-d');
        $group_code = generateGroupCode($ship_date);

        $data = [
            'code' => $code,
            'group_code' => $group_code,
            'sender_name' => $_POST['sender_name'],
            'sender_phone' => $_POST['sender_phone'],
            'receiver_name' => $_POST['receiver_name'],
            'receiver_phone' => $_POST['receiver_phone'],
            'product' => $_POST['product'],
            'weight' => $weight,
            'shipping_cost' => $shipping_cost,
            'sale_price' => $sale_price,
            'advance_payment' => floatval($_POST['advance_payment']),
            'profit' => $profit,
            'ship_date' => $_POST['ship_date'],
            'est_date' => $_POST['est_date'],
            'status' => $_POST['status']
        ];

        $shipment_id = createShipment($pdo, $data);

        // Redirigir a WhatsApp
        $sender_phone = preg_replace('/[^0-9]/', '', $_POST['sender_phone']);
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $ticket_url = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/generate_pdf.php?id=' . $shipment_id;
        
        $message = urlencode("¡Hola " . $_POST['sender_name'] . "! Tu envío ha sido registrado con éxito.\n\n");
        $message .= urlencode("Detalles del pago:\n");
        $message .= urlencode("Precio total: " . number_format($total_price, 0, ',', '.') . " XAF\n");
        
        if ($discount_percentage > 0) {
            $message .= urlencode("Descuento (" . $discount_percentage . "%): -" . number_format($discount_amount, 2, ',', '.') . " XAF\n");
            $message .= urlencode("Precio con descuento: " . number_format($sale_price, 2, ',', '.') . " XAF\n");
        }
        
        if (floatval($_POST['advance_payment']) > 0) {
            $advance_payment = floatval($_POST['advance_payment']);
            $balance = $sale_price - $advance_payment;
            $message .= urlencode("Pago adelantado: " . number_format($advance_payment, 2, ',', '.') . " XAF\n");
            $message .= urlencode("Saldo pendiente: " . number_format($balance, 2, ',', '.') . " XAF\n");
        }
        
        $message .= urlencode("\nPuedes ver y descargar tu ticket aquí: " . $ticket_url);
        
        $whatsapp_url = "https://wa.me/{$sender_phone}?text={$message}";
        
        header("Location: " . $whatsapp_url);
        exit;

    } catch (Exception $e) {
        $error = "Error al crear envío: " . $e->getMessage();
    }
}

// Procesar cambio de estado (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    
    if (updateShipmentStatus($pdo, $id, $status)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar el estado']);
    }
    exit;
}

// Procesar edición de envío (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_shipment'])) {
    $id = $_POST['id'];
    try {
        $weight = floatval($_POST['weight']);
        $price_per_kg = 6500; // Precio por kilo en Fcfa
        $sale_price = $weight * $price_per_kg;

        $data = [
            'sender_name' => $_POST['sender_name'],
            'sender_phone' => $_POST['sender_phone'],
            'receiver_name' => $_POST['receiver_name'],
            'receiver_phone' => $_POST['receiver_phone'],
            'product' => $_POST['product'],
            'weight' => $weight,
            'sale_price' => $sale_price,
            'advance_payment' => floatval($_POST['advance_payment']),
            'ship_date' => $_POST['ship_date'],
            'est_date' => $_POST['est_date'],
            'status' => $_POST['status']
        ];

        if (updateShipment($pdo, $id, $data)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar el envío']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Procesar eliminación de envío
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shipment'])) {
    $id = $_POST['id'];
    
    if (deleteShipment($pdo, $id)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => true]);
        } else {
            $success = "Envío eliminado correctamente!";
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => 'Error al eliminar el envío']);
        } else {
            $error = "Error al eliminar el envío. Por favor, inténtalo de nuevo.";
        }
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        exit;
    }
}

// Nota: La gestión de roles/usuarios se realiza ahora desde Registrar Socio.

// ---- Obtención de Datos ----
$grouped_shipments = getShipmentsByGroup($pdo);
// $admins = getAllAdmins($pdo); // Ya no se muestra la gestión de administradores aquí

// Definir los estados disponibles y sus etiquetas
$status_options = [
    'pending' => 'Pendiente',
    'ontheway' => 'En Camino',
    'arrived' => 'Llegada',
    'delay' => 'Retraso',
    'delivered' => 'Entregado'
];
?>

<?php include 'includes/header.php'; ?>

    <section class="admin-section">
        <h2 class="section-title">Gestión de Envíos</h2>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Sección de grupos de envíos -->
        <div class="shipment-groups">
            <?php 
            $hasActiveShipments = false;
            foreach ($grouped_shipments as $group): 
                if ($group['total_shipments'] > 0) {
                    $hasActiveShipments = true;
                    break;
                }
            endforeach; 
            
            if ($hasActiveShipments): 
            ?>
            <?php foreach ($grouped_shipments as $group): ?>
                <?php if ($group['total_shipments'] > 0): ?>
                <div class="group-container">
                    <div class="group-header group-toggle" id="group-<?php echo $group['group_code']; ?>-toggle"
                        data-group-code="<?php echo $group['group_code']; ?>" style="cursor: pointer;">
                        <div class="group-info">
                            <span class="group-date"><?php echo $group['formatted_date']; ?></span>
                            <div class="group-stats">
                                <span class="stat-item">Total: <?php echo $group['total_shipments']; ?></span>
                                <button type="button" class="notify-arrival-btn" onclick="event.stopPropagation(); notifyGroupArrival('<?php echo htmlspecialchars($group['group_code']); ?>', '<?php echo htmlspecialchars($group['formatted_date']); ?>')">
                                    <i class="fas fa-bell"></i> Notificar Llegada
                                </button>
                                <?php if ($group['pending'] > 0): ?>
                                    <span class="stat-item">Pendientes: <?php echo $group['pending']; ?></span>
                                <?php endif; ?>
                                <?php if ($group['ontheway'] > 0): ?>
                                    <span class="stat-item">En Camino: <?php echo $group['ontheway']; ?></span>
                                <?php endif; ?>
                                <?php if ($group['arrived'] > 0): ?>
                                    <span class="stat-item">Llegados: <?php echo $group['arrived']; ?></span>
                                <?php endif; ?>
                                <?php if ($group['delayed_count'] > 0): ?>
                                    <span class="stat-item">Retrasados: <?php echo $group['delayed_count']; ?></span>
                                <?php endif; ?>
                                <?php if ($group['delivered'] > 0): ?>
                                    <span class="stat-item">Entregados: <?php echo $group['delivered']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>

                    <div id="group-<?php echo $group['group_code']; ?>-content" class="group-content hidden">
                        <div class="table-responsive">
                            <table class="table" id="tban">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Remitente</th>
                                        <th>Destinatario</th>
                                        <th>Producto</th>
                                        <th>Peso</th>
                                        <th>Precio</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $group_shipments = getShipmentsByGroupCode($pdo, $group['group_code']);
                                    foreach ($group_shipments as $shipment): 
                                    ?>
                                    <tr id="shipment-<?php echo $shipment['id']; ?>">
                                        <td><?php echo htmlspecialchars($shipment['code']); ?></td>
                                        <td><?php echo htmlspecialchars($shipment['sender_name']); ?></td>
                                        <td><?php echo htmlspecialchars($shipment['receiver_name']); ?></td>
                                        <td><?php echo htmlspecialchars($shipment['product']); ?></td>
                                        <td><?php echo htmlspecialchars($shipment['weight']); ?> kg</td>
                                        <td><?php echo number_format($shipment['sale_price'], 0, ',', '.'); ?> XAF</td>
                                        <td>
                                            <select class="status-select" data-shipment-id="<?php echo $shipment['id']; ?>">
                                                <?php foreach ($status_options as $value => $label): ?>
                                                    <option value="<?php echo $value; ?>" 
                                                            <?php echo $shipment['status'] === $value ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php else: ?>
                <div class="no-shipments-message">
                    <i class="fas fa-box-open"></i>
                    <p>No hay grupos de envíos activos en este momento.</p>
                    <p>Los envíos aparecerán aquí cuando se registren nuevos paquetes.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <h3 class="section-title">Añadir Nuevo Envío</h3>
        <form id="new-shipment-form" method="POST" class="section">
            <input type="hidden" name="add_shipment" value="1">
            <div class="form-group">
                <label for="sender_name" class="form-label">Nombre del Remitente *</label>
                <input type="text" id="sender_name" name="sender_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="sender_phone" class="form-label">Teléfono del Remitente *</label>
                <input type="tel" id="sender_phone" name="sender_phone" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="receiver_name" class="form-label">Nombre del Destinatario *</label>
                <input type="text" id="receiver_name" name="receiver_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="receiver_phone" class="form-label">Teléfono del Destinatario *</label>
                <input type="tel" id="receiver_phone" name="receiver_phone" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="product-name" class="form-label">Nombre del Producto *</label>
                <input type="text" id="product-name" name="product" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="weight" class="form-label">Peso (kg) *</label>
                <input type="number" id="weight" name="weight" class="form-input" step="0.1" min="0.1" required>
            </div>
            
            <!-- Sección de Descuento -->
            <div class="form-group discount-section">
                <label for="discount-percentage" class="form-label">Descuento (%)</label>
                <input type="number" id="discount-percentage" name="discount_percentage" class="form-input discount-input" step="0.01" min="0" max="100" value="0">
                <div class="form-help">Ingrese un valor entre 0 y 100</div>
                <div class="discount-display">
                    <div class="discount-item">
                        <span class="discount-label">Precio Base:</span>
                        <span id="base-price" class="discount-value">0.00 XAF</span>
                    </div>
                    <div class="discount-item">
                        <span class="discount-label">Descuento:</span>
                        <span id="discount-amount" class="discount-value">0.00 XAF</span>
                    </div>
                    <div class="discount-item">
                        <span class="discount-label">Precio con Descuento:</span>
                        <span id="discounted-price" class="discount-value">0.00 XAF</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="status" class="form-label">Estado *</label>
                <select id="status" name="status" class="form-input" required>
                    <option value="pending">Pendiente</option>
                    <option value="ontheway">En Camino</option>
                    <option value="arrived">Llegada</option>
                    <option value="delayed">Retraso</option>
                    <option value="delivered">Entregado</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ship-date" class="form-label">Fecha de Envío *</label>
                <input type="date" id="ship-date" name="ship_date" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="est-date" class="form-label">Fecha Estimada de Entrega *</label>
                <input type="date" id="est-date" name="est_date" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="advance_payment" class="form-label">Pago Adelantado (XAF)</label>
                <input type="number" id="advance_payment" name="advance_payment" class="form-input" step="0.01" min="0" value="0">
            </div>
            <div class="form-group">
                <label class="form-label">Saldo Pendiente</label>
                <div id="balance-display" class="form-control">0.00 XAF</div>
            </div>
            <button type="submit" class="btn">Guardar Envío</button>
        </form>
        

        <h3 class="section-title" style="margin-top:2.5rem;">Todos los Envíos</h3>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Código Producto</th>
                    <th>Remitente</th>
                    <th>Destinatario</th>
                    <th>Producto</th>
                    <th>Peso</th>
                    <th>Fecha Envío</th>
                    <th>Fecha Estimada</th>
                    <th>Estado</th>
                    <th>Pago Adelantado</th>
                    <th>Saldo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_shipments = getAllShipmentsIncludingDelivered($pdo);
                foreach ($current_shipments as $shipment): 
                ?>
                    <tr data-id="<?php echo $shipment['id']; ?>">
                        <td><?php echo htmlspecialchars($shipment['code']); ?></td>
                        <td class="editable">
                            <div class="edit-group">
                                <input type="text" name="sender_name" value="<?php echo htmlspecialchars($shipment['sender_name']); ?>" class="edit-input">
                                <input type="tel" name="sender_phone" value="<?php echo htmlspecialchars($shipment['sender_phone']); ?>" class="edit-input">
                            </div>
                            <div class="display-text">
                                <?php echo htmlspecialchars($shipment['sender_name']); ?><br>
                                <small><?php echo htmlspecialchars($shipment['sender_phone']); ?></small>
                            </div>
                        </td>
                        <td class="editable">
                            <div class="edit-group">
                                <input type="text" name="receiver_name" value="<?php echo htmlspecialchars($shipment['receiver_name']); ?>" class="edit-input">
                                <input type="tel" name="receiver_phone" value="<?php echo htmlspecialchars($shipment['receiver_phone']); ?>" class="edit-input">
                            </div>
                            <div class="display-text">
                                <?php echo htmlspecialchars($shipment['receiver_name']); ?><br>
                                <small><?php echo htmlspecialchars($shipment['receiver_phone']); ?></small>
                            </div>
                        </td>
                        <td class="editable">
                            <input type="text" name="product" value="<?php echo htmlspecialchars($shipment['product']); ?>" class="edit-input">
                            <div class="display-text"><?php echo htmlspecialchars($shipment['product']); ?></div>
                        </td>
                        <td class="editable">
                            <input type="number" name="weight" value="<?php echo htmlspecialchars($shipment['weight']); ?>" step="0.1" min="0.1" class="edit-input">
                            <div class="display-text"><?php echo htmlspecialchars($shipment['weight']); ?> kg</div>
                        </td>
                        <td class="editable">
                            <input type="date" name="ship_date" value="<?php echo htmlspecialchars($shipment['ship_date']); ?>" class="edit-input">
                            <div class="display-text"><?php echo htmlspecialchars($shipment['ship_date']); ?></div>
                        </td>
                        <td class="editable">
                            <input type="date" name="est_date" value="<?php echo htmlspecialchars($shipment['est_date']); ?>" class="edit-input">
                            <div class="display-text"><?php echo htmlspecialchars($shipment['est_date']); ?></div>
                        </td>
                        <td>
                            <select class="status-select" name="status" data-id="<?php echo $shipment['id']; ?>" onchange="updateStatus(this)">
                                <option value="pending" <?php echo $shipment['status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="ontheway" <?php echo $shipment['status'] === 'ontheway' ? 'selected' : ''; ?>>En Camino</option>
                                <option value="arrived" <?php echo $shipment['status'] === 'arrived' ? 'selected' : ''; ?>>Llegada</option>
                                <option value="delayed" <?php echo $shipment['status'] === 'delayed' ? 'selected' : ''; ?>>Retraso</option>
                                <option value="delivered" <?php echo $shipment['status'] === 'delivered' ? 'selected' : ''; ?>>Entregado</option>
                            </select>
                        </td>
                        <td class="editable">
                            <input type="number" name="advance_payment" value="<?php echo htmlspecialchars($shipment['advance_payment']); ?>" step="0.01" min="0" class="edit-input">
                            <div class="display-text"><?php echo number_format($shipment['advance_payment'], 2, '.', ','); ?> XAF</div>
                        </td>
                        <td class="editable">
                            <div class="display-text"><?php echo number_format($shipment['sale_price'] - $shipment['advance_payment'], 2, '.', ','); ?> XAF</div>
                        </td>
                        <td>
                            <a href="generate_pdf.php?id=<?php echo $shipment['id']; ?>" target="_blank" class="action-btn pdf-btn" title="Generar PDF"><i class="fas fa-file-pdf"></i></a>
                            <button class="action-btn save-btn" onclick="saveShipment(this)" title="Guardar cambios" style="display: none;"><i class="fas fa-save"></i></button>
                            <button class="action-btn edit-btn" onclick="makeEditable(this)" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="action-btn delete-btn" onclick="deleteShipment(<?php echo $shipment['id']; ?>)" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <!-- Gestión de administradores movida a Registrar Socio -->


<?php include 'includes/footer.php'; ?>