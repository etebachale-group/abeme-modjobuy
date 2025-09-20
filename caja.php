<?php
// CAJA wallet page rebuilt to mirror partner wallet logic using shared helpers.
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/partner_earnings.php';
require_once 'includes/wallet.php';

requireAuth();
$role = $_SESSION['role'] ?? 'user';
if ($role !== 'super_admin') {
  // Permitir acceso si tiene permiso granular
  if (!function_exists('currentUserHasPermission') || !currentUserHasPermission($pdo, 'access_caja')) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
  }
}

$partnerName = 'CAJA';
$isSuperAdmin = true;

// --- Local helpers (same as partner_wallet) ---
function ensure_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_benefits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_name VARCHAR(100) NOT NULL UNIQUE,
        percentage DECIMAL(5,2) NOT NULL,
        total_earnings DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    try {
        $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
    } catch (Exception $e) {
        try {
            $cols = $pdo->query("DESCRIBE partner_benefits")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('wallet_balance', $cols)) {
                $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
            }
        } catch (Exception $ignored) {}
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_wallet_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_name VARCHAR(100) NOT NULL,
        type ENUM('deposit','withdraw') NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        previous_balance DECIMAL(15,2) NOT NULL,
        new_balance DECIMAL(15,2) NOT NULL,
        method VARCHAR(32) NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    try {
        $cols = $pdo->query("DESCRIBE partner_wallet_transactions")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('method', $cols)) {
            $pdo->exec("ALTER TABLE partner_wallet_transactions ADD COLUMN method VARCHAR(32) NULL AFTER new_balance");
        }
    } catch (Exception $ignored) {}
}

function ensure_partner(PDO $pdo, string $partnerName): void {
    $stmt = $pdo->prepare("INSERT IGNORE INTO partner_benefits (partner_name, percentage) VALUES (?, ?)");
    $stmt->execute([$partnerName, 0.00]);
    updatePartnerBenefits($partnerName);
}

function fetch_balances(PDO $pdo, string $partnerName): array {
    $stmt = $pdo->prepare("SELECT percentage, total_earnings, current_balance, wallet_balance FROM partner_benefits WHERE partner_name = ?");
    $stmt->execute([$partnerName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['percentage'=>0,'total_earnings'=>0,'current_balance'=>0,'wallet_balance'=>0];
}

function list_transactions(PDO $pdo, string $partnerName, array $filters, int $page = 1, int $perPage = 20): array {
    $wf = ['partner_name = ?'];
    $wp = [$partnerName];
    if (!empty($filters['from'])) { $wf[] = 'created_at >= ?'; $wp[] = $filters['from'] . ' 00:00:00'; }
    if (!empty($filters['to']))   { $wf[] = 'created_at <= ?'; $wp[] = $filters['to']   . ' 23:59:59'; }
    if (!empty($filters['type']) && in_array($filters['type'], ['deposit','withdraw'], true)) { $wf[] = 'type = ?'; $wp[] = $filters['type']; }
    if (array_key_exists('method', $filters) && $filters['method'] !== '') { $wf[] = 'method = ?'; $wp[] = $filters['method']; }
    if ($filters['min'] !== '' && is_numeric($filters['min'])) { $wf[] = 'amount >= ?'; $wp[] = (float)$filters['min']; }
    if ($filters['max'] !== '' && is_numeric($filters['max'])) { $wf[] = 'amount <= ?'; $wp[] = (float)$filters['max']; }
    if ($filters['q'] !== '') { $wf[] = '(notes LIKE ?)'; $wp[] = '%' . $filters['q'] . '%'; }

    $whereSql = implode(' AND ', $wf);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM partner_wallet_transactions WHERE $whereSql");
    $countStmt->execute($wp);
    $totalRows = (int)$countStmt->fetchColumn();
    $pages = max(1, (int)ceil($totalRows / max(1, $perPage)));
    $offset = (max(1,$page)-1) * $perPage;

    $stmt = $pdo->prepare("SELECT * FROM partner_wallet_transactions WHERE $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($wp);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [$rows, $totalRows, $pages];
}

// Ensure structures and partner row
ensure_schema($pdo);
ensure_partner($pdo, $partnerName);

// Handle actions (POST) using wallet helpers
$flash = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    try {
        if ($action === 'deposit') {
            if (!$isSuperAdmin) { throw new Exception('No autorizado'); }
            if ($amount <= 0) { throw new Exception('Monto inválido'); }
            // Same logic as partner: reduce pendiente y aumentar monedero
            wallet_deposit($partnerName, $amount, 'admin_deposit', $notes, true, true);
            $flash = ['type'=>'success','msg'=>'Depósito realizado'];
            header('Location: caja.php?ok=1');
            exit;
        } elseif ($action === 'withdraw') {
            if (!$isSuperAdmin) { throw new Exception('No autorizado'); }
            if ($amount <= 0) { throw new Exception('Monto inválido'); }
            $res = wallet_withdraw($partnerName, $amount, 'admin_withdraw', $notes);
            if (empty($res['success'])) { throw new Exception($res['message'] ?? 'No se pudo retirar'); }
            $tx = (string)($res['transaction_id'] ?? '');
            $ticketUrl = $tx !== '' ? ('api/generate_withdraw_ticket.php?tx=' . urlencode($tx)) : '';
            $msg = 'Retiro realizado';
            if ($ticketUrl) { $msg .= ' · '; $msg .= '<a href="'.$ticketUrl.'" target="_blank">Descargar ticket</a>'; }
            $flash = ['type'=>'success','msg'=>$msg];
        }
    } catch (Throwable $e) {
        $flash = ['type'=>'error','msg'=>$e->getMessage()];
    }
}

// Query current balances and transactions
$data = fetch_balances($pdo, $partnerName);
$filters = [
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'type' => $_GET['type'] ?? '',
    'method' => $_GET['method'] ?? '',
    'min' => $_GET['min'] ?? '',
    'max' => $_GET['max'] ?? '',
    'q' => $_GET['q'] ?? '',
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
[$walletTx, $totalRows, $pages] = list_transactions($pdo, $partnerName, $filters, $page, $perPage);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Monedero de CAJA</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/benefits-app.css">
  <link rel="stylesheet" href="css/panel-ui.css">
</head>
<body>
  <?php include 'includes/header.php'; ?>
  <main class="main-content wallet-page">
    <section class="dashboard-section">
      <div class="wallet-hero">
        <div class="hero-top">
          <h1><i class="fas fa-vault"></i> Monedero • CAJA</h1>
          <div class="hero-actions">
            <a class="btn btn-dark" href="benefits.php"><i class="fas fa-arrow-left"></i> Beneficios</a>
          </div>
        </div>
        <div class="balances">
          <div class="wallet-card accent">
            <div class="label">Saldo Monedero</div>
            <div class="value">XAF <?php echo number_format((float)$data['wallet_balance'],2,',','.'); ?></div>
          </div>
          <div class="wallet-card">
            <div class="label">Pendiente por Cobrar</div>
            <div class="value">XAF <?php echo number_format((float)$data['current_balance'],2,',','.'); ?></div>
          </div>
          <div class="wallet-card info">
            <div class="label">Ganancias Totales</div>
            <div class="value">XAF <?php echo number_format((float)$data['total_earnings'],2,',','.'); ?></div>
          </div>
          <div class="wallet-card">
            <div class="label">Porcentaje</div>
            <div class="value"><?php echo number_format((float)$data['percentage'],2,',','.'); ?>%</div>
          </div>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="notice <?php echo $flash['type']==='error'?'error':'success'; ?>" style="margin:10px 0;">
          <?php if ($flash['type']==='success') { echo $flash['msg']; } else { echo htmlspecialchars($flash['msg']); } ?>
        </div>
      <?php endif; ?>

      <div class="card-modern">
        <h2>Acciones</h2>
        <div class="quick-actions" style="display:grid; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); gap:16px;">
          <form method="post" class="action-card">
            <input type="hidden" name="action" value="deposit">
            <label class="label" for="depositAmount">Depositar al monedero</label>
            <input class="form-input-dark" type="number" step="0.01" min="0.01" name="amount" id="depositAmount" placeholder="0" required>
            <input class="form-input-dark" type="text" name="notes" placeholder="Notas (opcional)" style="margin-top:6px;">
            <button class="btn btn-accent" type="submit" style="margin-top:8px;"><i class="fas fa-circle-down"></i> Depositar</button>
            <div class="form-help">Mueve desde Pendiente al Monedero.</div>
          </form>

          <div class="action-card">
            <label class="label">Historial de retiros</label>
            <p style="font-size:13px; color:#bbb; margin:6px 0 10px;">Elimina todos los retiros registrados de este monedero y restaura esos montos al saldo.</p>
            <button id="btnClearWithdrawals" class="btn btn-dark" type="button"><i class="fas fa-eraser"></i> Eliminar retiros</button>
          </div>

          <form method="post" class="action-card">
            <input type="hidden" name="action" value="withdraw">
            <label class="label" for="withdrawAmount">Retirar del monedero</label>
            <input class="form-input-dark" type="number" step="0.01" min="0.01" name="amount" id="withdrawAmount" placeholder="0" required <?php echo ((float)$data['wallet_balance']<=0)?'disabled':''; ?>>
            <input class="form-input-dark" type="text" name="notes" placeholder="Notas (opcional)" style="margin-top:6px;" <?php echo ((float)$data['wallet_balance']<=0)?'disabled':''; ?>>
            <button class="btn btn-info-dark" type="submit" style="margin-top:8px;" <?php echo ((float)$data['wallet_balance']<=0)?'disabled':''; ?>><i class="fas fa-money-bill-wave"></i> Retirar</button>
            <?php if ((float)$data['wallet_balance']<=0): ?><div class="form-help">No hay fondos en el monedero.</div><?php endif; ?>
          </form>
        </div>
      </div>

      <div class="card-modern">
        <h2>Movimientos</h2>
        <form method="get" class="filters" style="display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:12px; align-items:end; margin:10px 0 4px;">
          <input type="hidden" name="partner" value="<?php echo htmlspecialchars($partnerName); ?>">
          <div><label class="label">Desde</label><input type="date" name="from" class="form-input-dark" value="<?php echo htmlspecialchars($filters['from']); ?>"></div>
          <div><label class="label">Hasta</label><input type="date" name="to" class="form-input-dark" value="<?php echo htmlspecialchars($filters['to']); ?>"></div>
          <div><label class="label">Tipo</label>
            <select name="type" class="form-input-dark">
              <option value="" <?php echo $filters['type']===''?'selected':''; ?>>Todos</option>
              <option value="deposit" <?php echo $filters['type']==='deposit'?'selected':''; ?>>Depósito</option>
              <option value="withdraw" <?php echo $filters['type']==='withdraw'?'selected':''; ?>>Retiro</option>
            </select>
          </div>
          <div><label class="label">Método</label>
            <select name="method" class="form-input-dark">
              <option value="" <?php echo $filters['method']===''?'selected':''; ?>>Todos</option>
              <option value="admin_deposit" <?php echo $filters['method']==='admin_deposit'?'selected':''; ?>>Depósito (Admin)</option>
              <option value="admin_withdraw" <?php echo $filters['method']==='admin_withdraw'?'selected':''; ?>>Retiro (Admin)</option>
            </select>
          </div>
          <div><label class="label">Mín (XAF)</label><input type="number" step="0.01" name="min" class="form-input-dark" value="<?php echo htmlspecialchars($filters['min']); ?>"></div>
          <div><label class="label">Máx (XAF)</label><input type="number" step="0.01" name="max" class="form-input-dark" value="<?php echo htmlspecialchars($filters['max']); ?>"></div>
          <div style="grid-column: 1 / -1;"><label class="label">Texto (en notas)</label><input type="text" name="q" class="form-input-dark" value="<?php echo htmlspecialchars($filters['q']); ?>" placeholder="Buscar en notas"></div>
          <div class="btn-group">
            <button class="btn btn-dark" type="submit"><i class="fas fa-filter"></i> Aplicar</button>
            <a class="btn btn-dark" href="caja.php"><i class="fas fa-undo"></i> Limpiar</a>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table-dark">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Método</th><th>Monto</th><th>Anterior</th><th>Nuevo</th><th>Notas</th><th>Acciones</th></tr></thead>
            <tbody>
              <?php if (empty($walletTx)): ?>
                <tr><td colspan="8"><div class="empty-state">Sin movimientos</div></td></tr>
              <?php else: foreach ($walletTx as $t): ?>
                <tr class="row-<?php echo $t['type']==='withdraw'?'withdraw':'deposit'; ?>">
                  <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($t['created_at']))); ?></td>
                  <td><?php echo $t['type'] === 'withdraw' ? 'Retiro' : 'Depósito'; ?></td>
                  <td><?php echo htmlspecialchars($t['method'] ?? ''); ?></td>
                  <td>XAF <?php echo number_format((float)$t['amount'],2,',','.'); ?></td>
                  <td>XAF <?php echo number_format((float)$t['previous_balance'],2,',','.'); ?></td>
                  <td>XAF <?php echo number_format((float)$t['new_balance'],2,',','.'); ?></td>
                  <td><?php echo htmlspecialchars($t['notes'] ?? ''); ?></td>
                  <td>
                    <?php if (($t['type'] ?? '') === 'withdraw'): ?>
                      <a href="<?php echo 'api/generate_withdraw_ticket.php?tx='.(int)$t['id']; ?>" class="btn btn-dark" target="_blank" title="Descargar ticket"><i class="fas fa-file-pdf"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages > 1): ?>
          <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
            <?php for($i=1;$i<=$pages;$i++): $q = $_GET; $q['page']=$i; ?>
              <a class="btn btn-dark<?php echo $i===$page?' active':''; ?>" href="<?php echo htmlspecialchars('caja.php?'.http_build_query($q)); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <script>
    (function(){
      const btn = document.getElementById('btnClearWithdrawals');
      if (!btn) return;
      btn.addEventListener('click', async function(){
        if (!confirm('¿Eliminar todo el historial de retiros de este monedero y restaurar los fondos?')) return;
        const params = new URLSearchParams();
        params.append('partner_name', <?php echo json_encode($partnerName); ?>);
        try {
          const res = await fetch('api/remove_partner_withdrawals.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},
            body: params.toString(),
            credentials: 'same-origin',
            cache: 'no-cache'
          });
          let data;
          try { data = await res.json(); }
          catch { const t = await res.text(); throw new Error('HTTP ' + res.status + ' ' + res.statusText + ' | ' + t.slice(0,200)); }
          if (res.ok && data.success) {
            alert('Retiros eliminados');
            location.reload();
          } else {
            throw new Error(data.message || 'No se pudo eliminar');
          }
        } catch (err) {
          alert('Error: ' + (err.message || 'Error de red'));
        }
      });
    })();
  </script>
</body>
</html>
