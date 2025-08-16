<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Verificar que el usuario esté autenticado
requireAuth();

// Definir los socios
$partners = [
    'FERNANDO CHALE',
    'MARIA CARMEN NSUE',
    'GENEROSA ABEME',
    'MARIA ISABEL',
    'CAJA',
    'FONDOS DE SOCIOS'
];

// Procesar formulario de nuevo gasto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $description = $_POST['description'];
    $amount = floatval($_POST['amount']);
    $paid_by = $_POST['paid_by'];
    $date = $_POST['date'];
    $operation_type = $_POST['operation_type'] ?? 'subtract'; // Default to subtract if not set
    
    if (addExpense($pdo, $description, $amount, $paid_by, $date, $operation_type)) {
        $success = "Gasto registrado correctamente.";
    } else {
        $error = "Error al registrar el gasto.";
    }
    
    // Recargar la página para mostrar los cambios
    header("Location: expenses.php?success=" . urlencode($success));
    exit;
}

// Obtener gastos recientes
$expenses = getExpenses($pdo);

// Obtener beneficios de los socios
$partnerBenefits = getPartnerBenefits($pdo);

include 'includes/header.php';
?>

<div class="container">
    <h1>Gestión de Gastos</h1>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Formulario para añadir gasto -->
    <div class="card">
        <h2>Añadir Nuevo Gasto</h2>
        <form method="POST">
            <input type="hidden" name="add_expense" value="1">
            
            <div class="form-group">
                <label>Tipo de Operación *</label><br>
                <input type="radio" id="operation_subtract" name="operation_type" value="subtract" checked>
                <label for="operation_subtract">Restar (Gasto)</label><br>
                <input type="radio" id="operation_add" name="operation_type" value="add">
                <label for="operation_add">Añadir (Ingreso)</label><br>
                <input type="radio" id="operation_adjust" name="operation_type" value="adjust">
                <label for="operation_adjust">Ajustes (Restar solo del total)</label>
            </div>
            
            <div class="form-group">
                <label for="description">Descripción del Gasto *</label>
                <input type="text" id="description" name="description" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="amount">Monto (XAF) *</label>
                <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="paid_by">Pagado por *</label>
                <select id="paid_by" name="paid_by" class="form-control" required>
                    <option value="">Seleccione un socio</option>
                    <?php foreach ($partners as $partner): ?>
                        <option value="<?php echo htmlspecialchars($partner); ?>"><?php echo htmlspecialchars($partner); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="date">Fecha *</label>
                <input type="date" id="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Registrar Gasto</button>
        </form>
    </div>
    
    <!-- Resumen de beneficios -->
    <div class="card">
        <h2>Resumen de Beneficios</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Socio</th>
                        <th>Gastos Totales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($partnerBenefits as $benefit): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($benefit['partner_name']); ?></td>
                            <td>XAF <?php echo number_format($benefit['total_expenses'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Historial de gastos -->
    <div class="card">
        <h2>Historial de Gastos</h2>
        <div class="table-responsive">
            <?php if (empty($expenses)): ?>
                <p>No hay gastos registrados.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo de Operación</th>
                            <th>Monto</th>
                            <th>Descripción</th>
                            <th>Socio (si aplica)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($expense['date']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($expense['operation_type'])); ?></td>
                                <td>XAF <?php echo number_format($expense['amount'], 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                <td>
                                    <?php
                                    if (isset($expense['operation_type']) && $expense['operation_type'] === 'subtract') {
                                        echo htmlspecialchars($expense['paid_by']);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>