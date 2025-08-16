<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$error = '';
$shipment = null;

// Solo procesar la búsqueda si se proporciona un código
if (isset($_GET['code']) && !empty($_GET['code'])) {
    $code = $_GET['code'];
    $shipment = findShipmentByCode($pdo, $code);
    
    if (!$shipment) {
        $error = 'No se encontró ningún envío con el código proporcionado';
    }
}

// Si es una petición AJAX, devolver JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    
    if (empty($_GET['code'])) {
        echo json_encode([
            'error' => 'Por favor, ingresa un código de seguimiento'
        ]);
    } else if ($shipment) {
        // Agregar el badge de estado
        $shipment['status_badge'] = getStatusBadge($shipment['status']);
        echo json_encode($shipment);
    } else {
        echo json_encode([
            'error' => $error
        ]);
    }
    exit;
}

// Si no es AJAX, mostrar la página normal
include 'includes/header.php';
?>

<div class="tracking-container">
    <h1>Rastrear Envío</h1>
    
    <form method="GET" action="" class="tracking-form">
        <div class="form-group">
            <label for="code">Código de Seguimiento:</label>
            <div class="input-group">
                <input type="text" id="code" name="code" class="form-input" 
                       value="<?php echo htmlspecialchars($_GET['code'] ?? ''); ?>" 
                       placeholder="Ejemplo: ABM-123456"
                       pattern="ABM-[0-9]{6}"
                       title="El código debe tener el formato ABM-123456"
                       required>
            </div>
            <small class="form-help">Ingrese el código en el formato ABM-XXXXXX</small>
        </div>
        <button type="submit" class="btn">
            <i class="fas fa-search"></i> Rastrear Envío
        </button>
    </form>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($shipment): ?>
        <div class="shipment-details">
            <h2>Detalles del Envío</h2>
            <div class="shipment-info">
                <div class="info-group">
                    <label>Código:</label>
                    <span><?php echo htmlspecialchars($shipment['code']); ?></span>
                </div>
                <div class="info-group">
                    <label>Estado:</label>
                    <span class="status-badge status-<?php echo htmlspecialchars($shipment['status']); ?>">
                        <?php
                        $status_labels = [
                            'pending' => 'Pendiente',
                            'ontheway' => 'En Camino',
                            'arrived' => 'Llegada',
                            'delayed' => 'Retraso',
                            'delivered' => 'Entregado'
                        ];
                        echo $status_labels[$shipment['status']] ?? $shipment['status'];
                        ?>
                    </span>
                </div>
                <div class="info-group">
                    <label>Remitente:</label>
                    <span><?php echo htmlspecialchars($shipment['sender_name']); ?></span>
                </div>
                <div class="info-group">
                    <label>Destinatario:</label>
                    <span><?php echo htmlspecialchars($shipment['receiver_name']); ?></span>
                </div>
                <div class="info-group">
                    <label>Producto:</label>
                    <span><?php echo htmlspecialchars($shipment['product']); ?></span>
                </div>
                <div class="info-group">
                    <label>Peso:</label>
                    <span><?php echo htmlspecialchars($shipment['weight']); ?> kg</span>
                </div>
                <div class="info-group">
                    <label>Fecha de Envío:</label>
                    <span><?php echo date('d/m/Y', strtotime($shipment['ship_date'])); ?></span>
                </div>
                <div class="info-group">
                    <label>Fecha Estimada de Entrega:</label>
                    <span><?php echo date('d/m/Y', strtotime($shipment['est_date'])); ?></span>
                </div>
                <div class="info-group">
                    <label>Precio Total:</label>
                    <span><?php echo number_format($shipment['sale_price'], 0, ',', '.'); ?> XAF</span>
                </div>
            </div>

            <div class="shipment-timeline">
                <div class="timeline-track">
                    <?php
                    $statuses = ['pending', 'ontheway', 'arrived', 'delivered'];
                    $current_status_index = array_search($shipment['status'], $statuses);
                    
                    foreach ($statuses as $index => $status):
                        $status_class = $index <= $current_status_index ? 'completed' : '';
                    ?>
                        <div class="timeline-point <?php echo $status_class; ?>">
                            <div class="point"></div>
                            <div class="label">
                                <?php echo $status_labels[$status]; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.tracking-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.tracking-container h1 {
    text-align: center;
    color: #333;
    margin-bottom: 2rem;
    font-size: 2.5rem;
}

.tracking-form {
    background: #fff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
    text-align: center;
}

.tracking-form .form-group {
    margin-bottom: 1.5rem;
}

.tracking-form label {
    display: block;
    font-size: 1.1rem;
    color: #555;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.tracking-form .form-input {
    width: 100%;
    max-width: 400px;
    padding: 12px 20px;
    font-size: 1.1rem;
    border: 2px solid #ddd;
    border-radius: 8px;
    transition: all 0.3s ease;
    margin: 0 auto;
}

.tracking-form .form-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
    outline: none;
}

.tracking-form .btn {
    background-color: #007bff;
    color: white;
    padding: 12px 40px;
    font-size: 1.1rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 1rem;
}

.tracking-form .btn:hover {
    background-color: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.tracking-form .btn:active {
    transform: translateY(0);
}

.shipment-details {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.shipment-info {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.info-group {
    margin-bottom: 1rem;
}

.info-group label {
    font-weight: bold;
    display: block;
    margin-bottom: 0.5rem;
    color: #666;
}

.info-group span {
    font-size: 1.1rem;
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: bold;
    color: white;
}

.status-pending { background-color: #ffc107; }
.status-ontheway { background-color: #17a2b8; }
.status-arrived { background-color: #28a745; }
.status-delayed { background-color: #dc3545; }
.status-delivered { background-color: #28a745; }

.form-help {
    display: block;
    margin-top: 0.5rem;
    color: #666;
    font-size: 0.9rem;
}

.input-group {
    position: relative;
    display: flex;
    justify-content: center;
}

@media (max-width: 480px) {
    .tracking-container h1 {
        font-size: 2rem;
    }

    .tracking-form {
        padding: 1.5rem;
    }

    .tracking-form .form-input {
        font-size: 1rem;
        padding: 10px 15px;
    }

    .tracking-form .btn {
        width: 100%;
        padding: 10px 20px;
    }
}

.shipment-timeline {
    margin-top: 2rem;
    padding: 20px 0;
}

.timeline-track {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    margin: 40px 0;
}

.timeline-track::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background: #ddd;
    transform: translateY(-50%);
}

.timeline-point {
    position: relative;
    z-index: 1;
    text-align: center;
    flex: 1;
}

.timeline-point .point {
    width: 20px;
    height: 20px;
    background: #fff;
    border: 2px solid #ddd;
    border-radius: 50%;
    margin: 0 auto;
}

.timeline-point .label {
    margin-top: 10px;
    font-size: 0.9rem;
    color: #666;
}

.timeline-point.completed .point {
    background: #28a745;
    border-color: #28a745;
}

.timeline-point.completed .label {
    color: #28a745;
    font-weight: bold;
}

.alert {
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 4px;
}

.alert-danger {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
</style>

<?php include 'includes/footer.php'; ?>