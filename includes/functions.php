
<?php
// Obtener todos los envíos activos (no entregados)
function getAllShipments($pdo) {
    $stmt = $pdo->query("SELECT * FROM shipments WHERE status != 'delivered' ORDER BY created_at DESC, ship_date DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener todos los envíos incluyendo entregados (para reportes)
function getAllShipmentsIncludingDelivered($pdo) {
    $stmt = $pdo->query("SELECT * FROM shipments ORDER BY created_at DESC, ship_date DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar envío por código

function findShipmentByCode($pdo, $code) {
    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Buscar envío por ID
function getShipmentById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Crear nuevo envío
function generateShipmentCode($pdo) {
    do {
        // Genera un código con el formato ABM-XXXXXX
        $number = mt_rand(100000, 999999);
        $code = 'ABM-' . $number;
        $stmt = $pdo->prepare("SELECT id FROM shipments WHERE code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    
    return $code;
}

function generateGroupCode($date) {
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $year = date('y', $timestamp);
    
    // Array de meses en español
    $meses = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];
    
    $mes = $meses[intval(date('n', $timestamp))];
    
    // Formato: mes(en letras)-dia-año
    return $mes . '-' . $day . '-' . $year;
}

// Obtener envíos agrupados por fecha
function getShipmentsByGroup($pdo) {
    // Asegurarse de que todos los group_codes existan en shipment_groups
    $pdo->query("
        INSERT IGNORE INTO shipment_groups (group_code)
        SELECT DISTINCT group_code FROM shipments 
        WHERE group_code IS NOT NULL 
        AND group_code NOT IN (SELECT group_code FROM shipment_groups)
    ");

    // Actualizar automáticamente el estado de archivado de los grupos
    $pdo->query("
        UPDATE shipment_groups sg
        SET is_archived = 1
        WHERE NOT EXISTS (
            SELECT 1 FROM shipments s 
            WHERE s.group_code = sg.group_code 
            AND s.status != 'delivered'
        )
    ");

    $stmt = $pdo->query("
        SELECT 
            s.group_code,
            DATE_FORMAT(s.ship_date, '%d/%m/%Y') as formatted_date,
            COUNT(*) as total_shipments,
            SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN s.status = 'ontheway' THEN 1 ELSE 0 END) as ontheway,
            SUM(CASE WHEN s.status = 'arrived' THEN 1 ELSE 0 END) as arrived,
            SUM(CASE WHEN s.status = 'delay' THEN 1 ELSE 0 END) as delayed_count,
            SUM(CASE WHEN s.status = 'delivered' THEN 1 ELSE 0 END) as delivered
        FROM shipments s
        LEFT JOIN shipment_groups sg ON s.group_code = sg.group_code
        WHERE s.status != 'delivered'
        GROUP BY s.group_code, s.ship_date
        HAVING COUNT(*) > 0
        ORDER BY s.ship_date DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener envíos de un grupo específico
function getShipmentsByGroupCode($pdo, $groupCode) {
    $stmt = $pdo->prepare("
        SELECT s.* 
        FROM shipments s
        LEFT JOIN shipment_groups sg ON s.group_code = sg.group_code
        WHERE s.group_code = ? 
        AND s.status != 'delivered'
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$groupCode]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function createShipment($pdo, $data) {
    try {
        if (!isset($data['code'])) {
            $data['code'] = generateShipmentCode($pdo);
        }
        
        // Validar y establecer un estado predeterminado si es necesario
        if (!isset($data['status']) || empty($data['status'])) {
            $data['status'] = 'pending';
        }
        
        $stmt = $pdo->prepare("INSERT INTO shipments (code, group_code, sender_name, sender_phone, receiver_name, receiver_phone, product, weight, shipping_cost, sale_price, advance_payment, profit, ship_date, est_date, status)
                                VALUES (:code, :group_code, :sender_name, :sender_phone, :receiver_name, :receiver_phone, :product, :weight, :shipping_cost, :sale_price, :advance_payment, :profit, :ship_date, :est_date, :status)");
        
        if ($stmt->execute($data)) {
            return $pdo->lastInsertId();
        } else {
            // Throw an exception if execute fails, to be caught by the catch block
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Error al ejecutar la inserción: " . ($errorInfo[2] ?? 'Unknown error'));
        }
    } catch (PDOException $e) {
        // Temporarily rethrow the original exception for debugging
        throw $e;
    } catch (Exception $e) {
        // Log other exceptions
        error_log("General Error in createShipment: " . $e->getMessage());
        // Rethrow the exception to be handled by the caller
        throw $e;
    }
}

// Actualizar estado de envío
function updateShipmentStatus($pdo, $id, $status) {
    // Validar que el estado no esté vacío y sea válido
    $validStatus = ['pending', 'ontheway', 'arrived', 'delayed', 'delivered'];
    
    if (empty($status) || !in_array($status, $validStatus)) {
        $status = 'pending'; // Usar 'pending' como valor predeterminado
    }
    
    // Obtener el estado actual del envío
    $stmt = $pdo->prepare("SELECT status FROM shipments WHERE id = ?");
    $stmt->execute([$id]);
    $currentStatus = $stmt->fetchColumn();
    
    // Actualizar el estado del envío
    $stmt = $pdo->prepare("UPDATE shipments SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $id]);
    
    // Si el estado cambia a "delivered", actualizar los beneficios
    if ($result && $status === 'delivered' && $currentStatus !== 'delivered') {
        updateBenefitsOnDelivery($pdo, $id);
    }
    
    return $result;
}

// Actualizar envío
function updateShipment($pdo, $id, $data) {
    try {
        // Calcular el precio de venta basado en el peso
        if (isset($data['weight'])) {
            $price_per_kg = 6500; // Precio por kilo en XAF (Franco CFA de África Central)
            $weight = floatval($data['weight']);
            $data['sale_price'] = $weight * $price_per_kg;
        }

        // Validar y establecer un estado predeterminado si es necesario
        if (!isset($data['status']) || empty($data['status'])) {
            $data['status'] = 'pending';
        }

        // Validar que el estado sea válido
        $validStatus = ['pending', 'ontheway', 'arrived', 'delayed', 'delivered'];
        if (!in_array($data['status'], $validStatus)) {
            $data['status'] = 'pending';
        }

        $sql = "UPDATE shipments SET
                sender_name = :sender_name,
                sender_phone = :sender_phone,
                receiver_name = :receiver_name,
                receiver_phone = :receiver_phone,
                product = :product,
                weight = :weight,
                sale_price = :sale_price,
                advance_payment = :advance_payment,
                ship_date = :ship_date,
                est_date = :est_date,
                status = :status
                WHERE id = :id";
        
        $data['id'] = $id;
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($data);
        
        if ($result) {
            // Obtener los datos actualizados
            $stmt = $pdo->prepare("SELECT * FROM shipments WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    } catch (PDOException $e) {
        error_log("Error updating shipment: " . $e->getMessage());
        return false;
    }
}

// Eliminar envío
function deleteShipment($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM shipments WHERE id = ?");
        $result = $stmt->execute([$id]);
        if (!$result) {
            error_log("Error deleting shipment: " . print_r($stmt->errorInfo(), true));
            return false;
        }
        return true;
    } catch (PDOException $e) {
        error_log("Exception deleting shipment: " . $e->getMessage());
        return false;
    }
}

// Validar usuario
function createAdminIfNotExists($pdo) {
    // Verificar si la tabla users existe
    try {
        $stmt = $pdo->query("SELECT 1 FROM users LIMIT 1");
    } catch (PDOException $e) {
        // La tabla no existe, crearla
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'user'
        )");
        
        // Crear usuario administrador por defecto
        $email = 'admin@admin.com';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$email, $password]);
        
        return true;
    }
    return false;
}

function authenticateUser($pdo, $email, $password) {
    $stmt = $pdo->prepare("SELECT id, email, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']); // Don't store password in session
        return $user;
    }
    
    return false;
}

// Obtener el badge de estado para mostrar
function getStatusBadge($status) {
    $statusMap = [
        'pending' => ['class' => 'status-pending', 'text' => 'Pendiente'],
        'ontheway' => ['class' => 'status-ontheway', 'text' => 'En Camino'],
        'arrived' => ['class' => 'status-arrived', 'text' => 'Llegada'],
        'delayed' => ['class' => 'status-delayed', 'text' => 'Retraso'],
        'delivered' => ['class' => 'status-delivered', 'text' => 'Entregado']
    ];
    
    // Si el estado es vacío o no existe en el mapa, usar 'pending' como valor predeterminado
    if (empty($status) || !isset($statusMap[$status])) {
        $status = 'pending';
    }
    
    return '<span class="status '.$statusMap[$status]['class'].'">'.$statusMap[$status]['text'].'</span>';
}

// --- Funciones de Administración de Usuarios ---

// Obtener todos los administradores
function getAllAdmins($pdo) {
    $stmt = $pdo->query("SELECT id, email, created_at FROM users");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Crear nuevo administrador
function createAdmin($pdo, $email, $password) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    return $stmt->execute([$email, $hashed_password]);
}

// Eliminar administrador
function deleteAdmin($pdo, $id) {
    // Prevenir la eliminación del usuario principal (ID 1)
    if ($id == 1) {
        return false;
    }
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute([$id]);
}

function getRecentUndeliveredShipments($pdo, $sortDirection = 'DESC', $page = 1, $perPage = 10) {
    // Calcular el offset
    $offset = ($page - 1) * $perPage;
    
    // Obtener el total de registros
    $countStmt = $pdo->query("SELECT COUNT(*) FROM shipments WHERE status != 'delivered'");
    $totalRecords = $countStmt->fetchColumn();
    
    // Obtener los envíos para la página actual
    $stmt = $pdo->prepare("
        SELECT code, weight, ship_date, sale_price, advance_payment, status
        FROM shipments
        WHERE status != 'delivered'
        ORDER BY ship_date " . ($sortDirection === 'ASC' ? 'ASC' : 'DESC') . "
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return [
        'shipments' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $totalRecords,
        'pages' => ceil($totalRecords / $perPage),
        'current_page' => $page
    ];
}

function getShipmentByCode($pdo, $code) {
    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para agregar un gasto o ingreso
function addExpense($pdo, $description, $amount, $paid_by, $date, $operation_type) {
    try {
        // Insertar el registro en la tabla de gastos (o ingresos)
        $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, paid_by, date, operation_type) VALUES (?, ?, ?, ?, ?)");
        $insert_success = $stmt->execute([$description, $amount, $paid_by, $date, $operation_type]);

        if (!$insert_success) {
            error_log("Error inserting expense: " . print_r($stmt->errorInfo(), true));
            return false;
        }

        $update_success = true; // Flag to track success of subsequent updates

        if ($operation_type === 'subtract') {
            // Restar del beneficio total del sistema
            $stmt = $pdo->prepare("UPDATE system_metrics SET metric_value = metric_value - ? WHERE metric_name = 'total_accumulated_benefits'");
            $update_success = $stmt->execute([$amount]);
            if (!$update_success) {
                error_log("Error updating system_metrics (subtract): " . print_r($stmt->errorInfo(), true));
                return false;
            }

            // Sumar al beneficio del socio que puso el dinero (como reembolso/contribución)
            $stmt = $pdo->prepare("UPDATE partner_benefits SET total_expenses = total_expenses + ?, current_balance = current_balance + ? WHERE partner_name = ?");
            $update_success = $stmt->execute([$amount, $amount, $paid_by]);
            if (!$update_success) {
                error_log("Error updating partner_benefits (subtract): " . print_r($stmt->errorInfo(), true));
                return false;
            }
        } elseif ($operation_type === 'add') {
            // Sumar al beneficio total del sistema
            $stmt = $pdo->prepare("UPDATE system_metrics SET metric_value = metric_value + ? WHERE metric_name = 'total_accumulated_benefits'");
            $update_success = $stmt->execute([$amount]);
            if (!$update_success) {
                error_log("Error updating system_metrics (add): " . print_r($stmt->errorInfo(), true));
                return false;
            }
        } elseif ($operation_type === 'adjust') {
            // Restar solo del beneficio total del sistema (ajuste)
            $stmt = $pdo->prepare("UPDATE system_metrics SET metric_value = metric_value - ? WHERE metric_name = 'total_accumulated_benefits'");
            $update_success = $stmt->execute([$amount]);
            if (!$update_success) {
                error_log("Error updating system_metrics (adjust): " . print_r($stmt->errorInfo(), true));
                return false;
            }
        }

        return true; // All operations successful
    } catch (PDOException $e) {
        error_log("PDOException in addExpense: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("General Exception in addExpense: " . $e->getMessage());
        return false;
    }
}

// Función para actualizar beneficios cuando un envío es entregado
function updateBenefitsOnDelivery($pdo, $shipment_id) {
    try {
        // Obtener el envío
        $stmt = $pdo->prepare("SELECT * FROM shipments WHERE id = ?");
        $stmt->execute([$shipment_id]);
        $shipment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shipment) {
            return false;
        }
        
        // Calcular el beneficio (asumimos un 20% del precio de venta)
        $benefit = $shipment['sale_price'] * 0.20;

        // --- NEW CODE: Update system-wide total benefits ---
        $stmt = $pdo->prepare("UPDATE system_metrics SET metric_value = metric_value + ? WHERE metric_name = 'total_accumulated_benefits'");
        $stmt->execute([$benefit]);
        // --- END NEW CODE ---

        // Definir los socios y sus porcentajes
        $partners = [
            'FERNANDO CHALE' => 18,
            'MARIA CARMEN NSUE' => 18,
            'GENEROSA ABEME' => 30,
            'MARIA ISABEL' => 8,
            'CAJA' => 16,
            'FONDOS DE SOCIOS' => 10
        ];
        
        // Distribuir el beneficio entre los socios
        foreach ($partners as $partner => $percentage) {
            $partner_benefit = $benefit * ($percentage / 100);
            
            // Actualizar los beneficios del socio
            $stmt = $pdo->prepare("UPDATE partner_benefits SET total_benefits = total_benefits + ?, current_balance = current_balance + ? WHERE partner_name = ?");
            $stmt->execute([$partner_benefit, $partner_benefit, $partner]);
            
            // Registrar en el historial de beneficios
            $stmt = $pdo->prepare("INSERT INTO benefit_history (partner_name, shipment_id, amount, type, date) VALUES (?, ?, ?, 'benefit', ?)");
            $stmt->execute([$partner, $shipment_id, $partner_benefit, date('Y-m-d')]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating benefits: " . $e->getMessage());
        return false;
    }
}

// Función para obtener los beneficios de los socios
function getPartnerBenefits($pdo) {
    try {
        // Primero actualizamos las ganancias totales
        $pdo->query("CALL update_partner_total_earnings()");
        
        // Luego obtenemos los datos ordenados por total_earnings
        $stmt = $pdo->query("SELECT * FROM partner_benefits ORDER BY total_earnings DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting partner benefits: " . $e->getMessage());
        return [];
    }
}

// Función para obtener el historial de beneficios
function getBenefitHistory($pdo, $partner_name = null) {
    if ($partner_name) {
        $stmt = $pdo->prepare("SELECT * FROM benefit_history WHERE partner_name = ? ORDER BY date DESC, created_at DESC");
        $stmt->execute([$partner_name]);
    } else {
        $stmt = $pdo->query("SELECT * FROM benefit_history ORDER BY date DESC, created_at DESC");
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener los gastos
function getExpenses($pdo) {
    $stmt = $pdo->query("SELECT * FROM expenses ORDER BY date DESC, created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>