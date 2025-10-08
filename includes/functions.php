
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
            role VARCHAR(50) NOT NULL DEFAULT 'user',
            partner_name VARCHAR(100) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1
        )");
        
        // Crear usuario administrador por defecto
        // Crear admin por defecto
        $email = 'admin@admin.com';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$email, $password]);

    // Sembrar super admin solicitado si no existe
    $supEmail = 'etebachalegroup@gmail.com';
    $supPass = 'mX7#Aq!D9v^H5tPz@w3*LuG2s$RkJ8yBn%fC1eQxZo6T!MhKjVr4pW0Nd^Ub';
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$supEmail]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmt2 = $pdo->prepare("INSERT INTO users (email, password, role, active) VALUES (?, ?, 'super_admin', 1)");
            $stmt2->execute([$supEmail, password_hash($supPass, PASSWORD_DEFAULT)]);
        } else {
            // Asegurar que tenga rol super_admin
            $stmt3 = $pdo->prepare("UPDATE users SET role = 'super_admin', active = 1 WHERE email = ?");
            $stmt3->execute([$supEmail]);
        }

    // Nota: No desactivar otros usuarios automáticamente; mantener activos los usuarios existentes.
        
        return true;
    }
    // Asegurar columnas necesarias
    try {
        $cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('partner_name', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN partner_name VARCHAR(100) NULL");
        }
        if (!in_array('role', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user'");
        }
        if (!in_array('active', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
        }
        // Migración puntual: si existe el super admin anterior, actualizarlo al nuevo email/contraseña
        try {
            $oldEmail = 'admin.ops+llfavj@ecuacelebs.com';
            $newEmail = 'etebachalegroup@gmail.com';
            $newPass = 'mX7#Aq!D9v^H5tPz@w3*LuG2s$RkJ8yBn%fC1eQxZo6T!MhKjVr4pW0Nd^Ub';
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$oldEmail]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stmtU = $pdo->prepare("UPDATE users SET email = ?, password = ?, role = 'super_admin', active = 1 WHERE id = ?");
                $stmtU->execute([$newEmail, password_hash($newPass, PASSWORD_DEFAULT), $row['id']]);
            }
        } catch (Exception $ie) { /* ignore */ }

        // Asegurar que el super admin objetivo exista y tenga credenciales correctas
        try {
            $targetEmail = 'etebachalegroup@gmail.com';
            $targetPass = 'mX7#Aq!D9v^H5tPz@w3*LuG2s$RkJ8yBn%fC1eQxZo6T!MhKjVr4pW0Nd^Ub';
            $stmt = $pdo->prepare("SELECT id, role, active, password FROM users WHERE email = ?");
            $stmt->execute([$targetEmail]);
            if (!($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                // Crear si no existe
                $stmtI = $pdo->prepare("INSERT INTO users (email, password, role, partner_name, active) VALUES (?, ?, 'super_admin', NULL, 1)");
                $stmtI->execute([$targetEmail, password_hash($targetPass, PASSWORD_DEFAULT)]);
            } else {
                // Actualizar si la contraseña difiere o el rol/activo no está correcto
                $needPass = !password_verify($targetPass, $row['password'] ?? '');
                $needRole = ($row['role'] ?? 'user') !== 'super_admin';
                $needActive = (string)($row['active'] ?? '1') !== '1';
                if ($needPass || $needRole || $needActive) {
                    $sql = "UPDATE users SET ";
                    $params = [];
                    if ($needPass) { $sql .= "password = ?, "; $params[] = password_hash($targetPass, PASSWORD_DEFAULT); }
                    if ($needRole) { $sql .= "role = 'super_admin', "; }
                    if ($needActive) { $sql .= "active = 1, "; }
                    // trim trailing comma
                    $sql = rtrim($sql, ", ") . " WHERE id = ?";
                    $params[] = $row['id'];
                    $stmtU = $pdo->prepare($sql);
                    $stmtU->execute($params);
                }
            }
            // Nota: Evitar desactivar usuarios no-super_admin automáticamente.
        } catch (Exception $ie2) { /* ignore */ }
    // No se requiere migración especial para super_admin; se usará el campo role existente.
    } catch (Exception $e) {
        // ignore
    }
    return false;
}

function authenticateUser($pdo, $email, $password) {
    $stmt = $pdo->prepare("SELECT id, email, password, role, partner_name FROM users WHERE email = ? AND (active IS NULL OR active = 1)");
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

// ============================
// Permisos por usuario (granulares)
// ============================
// Estructura: Tabla user_permissions con pares (user_id, permission)
// Notas:
//  - Los super_admin tienen todos los permisos implícitos.
//  - Para admins/users, se consultan los permisos almacenados.

function ensureUserPermissionsTable(PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission VARCHAR(64) NOT NULL,
            UNIQUE KEY uniq_user_perm (user_id, permission)
        )");
    } catch (Exception $e) {
        // ignore
    }
}

/**
 * Obtener lista de permisos para un usuario dado.
 * @return string[] Array de permisos (claves)
 */
function getUserPermissions(PDO $pdo, int $userId): array {
    if ($userId <= 0) return [];
    ensureUserPermissionsTable($pdo);
    try {
        $stmt = $pdo->prepare("SELECT permission FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map('strval', $rows)));
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Reemplaza los permisos de un usuario por el conjunto dado.
 */
function setUserPermissions(PDO $pdo, int $userId, array $permissions): bool {
    if ($userId <= 0) return false;
    ensureUserPermissionsTable($pdo);
    // Normalizar y deduplicar
    $perms = [];
    foreach ($permissions as $p) {
        $p = trim((string)$p);
        if ($p !== '') { $perms[$p] = true; }
    }
    $perms = array_keys($perms);
    try {
        $startedTx = method_exists($pdo, 'inTransaction') && $pdo->inTransaction() ? false : true;
        if ($startedTx) { $pdo->beginTransaction(); }
        $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$userId]);
        if (!empty($perms)) {
            $ins = $pdo->prepare("INSERT INTO user_permissions (user_id, permission) VALUES (?, ?)");
            foreach ($perms as $p) { $ins->execute([$userId, $p]); }
        }
        if ($startedTx && method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->commit(); }
        return true;
    } catch (Throwable $e) {
        if (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Exception $ie) {} }
        return false;
    }
}

/**
 * Verificar si el usuario tiene un permiso específico.
 * Los super_admin tienen acceso a todo.
 */
function userHasPermission(PDO $pdo, int $userId, string $permission): bool {
    if ($userId <= 0) return false;
    // Bypass para super_admin
    $role = $_SESSION['role'] ?? 'user';
    if ($role === 'super_admin') return true;
    ensureUserPermissionsTable($pdo);
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = ? AND permission = ? LIMIT 1");
        $stmt->execute([$userId, $permission]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Azúcar sintáctico: permiso para el usuario actual (sesión requerida)
 */
function currentUserHasPermission(PDO $pdo, string $permission): bool {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    return userHasPermission($pdo, $uid, $permission);
}

// ============================
// Publicidad: Carrusel de Banners
// ============================

function getActiveAds($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, image_path, title, link_url FROM ad_banners WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getAllAds($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM ad_banners ORDER BY sort_order ASC, id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function createAd($pdo, array $data) {
    $sql = "INSERT INTO ad_banners (image_path, title, link_url, is_active, sort_order) VALUES (:image_path, :title, :link_url, :is_active, :sort_order)";
    $stmt = $pdo->prepare($sql);
    $payload = [
        ':image_path' => $data['image_path'] ?? '',
        ':title' => $data['title'] ?? null,
        ':link_url' => $data['link_url'] ?? null,
        ':is_active' => isset($data['is_active']) ? (int)!!$data['is_active'] : 1,
        ':sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
    ];
    if ($payload[':image_path'] === '') return false;
    try {
        $ok = $stmt->execute($payload);
        return $ok ? (int)$pdo->lastInsertId() : false;
    } catch (Exception $e) {
        return false;
    }
}

function toggleAdActive($pdo, int $id, bool $active) {
    try {
        $stmt = $pdo->prepare("UPDATE ad_banners SET is_active = ? WHERE id = ?");
        return $stmt->execute([$active ? 1 : 0, $id]);
    } catch (Exception $e) { return false; }
}

function deleteAd($pdo, int $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM ad_banners WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (Exception $e) { return false; }
}

function reorderAd($pdo, int $id, int $sort_order) {
    try {
        $stmt = $pdo->prepare("UPDATE ad_banners SET sort_order = ? WHERE id = ?");
        return $stmt->execute([$sort_order, $id]);
    } catch (Exception $e) { return false; }
}

function updateAd($pdo, int $id, array $data) {
    // Only allow updating these fields (no image change here)
    $fields = [];
    $params = [];
    if (array_key_exists('title', $data)) { $fields[] = 'title = ?'; $params[] = (string)$data['title']; }
    if (array_key_exists('link_url', $data)) { $fields[] = 'link_url = ?'; $params[] = (string)$data['link_url']; }
    if (array_key_exists('is_active', $data)) { $fields[] = 'is_active = ?'; $params[] = (int)!!$data['is_active']; }
    if (array_key_exists('sort_order', $data)) { $fields[] = 'sort_order = ?'; $params[] = (int)$data['sort_order']; }
    if (empty($fields)) return true; // nothing to update
    $params[] = $id;
    $sql = 'UPDATE ad_banners SET ' . implode(', ', $fields) . ' WHERE id = ?';
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) { return false; }
}

function updateAdImage($pdo, int $id, string $image_path) {
    if ($id <= 0 || $image_path === '') return false;
    try {
        $stmt = $pdo->prepare("UPDATE ad_banners SET image_path = ? WHERE id = ?");
        return $stmt->execute([$image_path, $id]);
    } catch (Exception $e) { return false; }
}

function getAdById($pdo, int $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ad_banners WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) { return null; }
}
?>