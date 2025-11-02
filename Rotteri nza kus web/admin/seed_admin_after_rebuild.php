<?php
/**
 * seed_admin_after_rebuild.php
 * ------------------------------------------------------------------
 * Uso: Ejecutar UNA vez después de que el sistema haya reconstruido la DB
 *       en la base fallback (rotteri_nza_kus_rebuild) para crear un admin
 *       si aún no existe ninguno.
 *
 * Seguridad: temporal; bórralo tras usarlo.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

try {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
} catch (Throwable $e) { $dbName = null; }

// Comprobar si ya hay admin
try {
    $st = $pdo->query("SELECT id,email FROM users WHERE role='admin' LIMIT 1");
    $existing = $st->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        echo json_encode(['success'=>true,'message'=>'Ya existe un admin','admin'=>$existing,'db'=>$dbName]);
        exit;
    }
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'No se pudo consultar usuarios','error'=>$e->getMessage()]);
    exit;
}

$email = 'admin_fallback@localhost';
$pass  = 'CambioInmediato123!';
$first = 'Admin';
$last  = 'Fallback';

try {
    $pdo->beginTransaction();
    $username = substr(preg_replace('/[^a-z0-9_]+/i','', explode('@',$email)[0]),0,30) ?: ('admin'.time());
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>10]);
    $ins = $pdo->prepare('INSERT INTO users (username,email,password,first_name,last_name,role) VALUES (?,?,?,?,?,"admin")');
    $ins->execute([$username,$email,$hash,$first,$last]);
    $uid = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO admins (user_id, company_name) VALUES (?,?)')->execute([$uid,'Rebuild']);
    $aid = (int)$pdo->lastInsertId();
    $pdo->commit();
    echo json_encode([
        'success'=>true,
        'message'=>'Admin creado en DB reconstruida',
        'credentials'=>['email'=>$email,'password'=>$pass],
        'user_id'=>$uid,
        'admin_id'=>$aid,
        'db'=>$dbName
    ]);
    // Auto destrucción opcional
    @unlink(__FILE__);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Throwable $ignore) {} }
    echo json_encode(['success'=>false,'message'=>'Fallo al crear admin','error'=>$e->getMessage(),'db'=>$dbName]);
}
?>
