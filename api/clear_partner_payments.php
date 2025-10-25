<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/partner_earnings.php';

header('Content-Type: application/json; charset=utf-8');

try {
    requireAuthApi();
    $role = $_SESSION['role'] ?? 'user';
    if ($role !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Solo super admin']);
        exit;
    }

    // Optional: partner_name to clear only that partner; otherwise clear all
    $partner = isset($_POST['partner_name']) ? trim((string)$_POST['partner_name']) : (isset($_GET['partner_name']) ? trim((string)$_GET['partner_name']) : (isset($_GET['partner']) ? trim((string)$_GET['partner']) : ''));
    // Optional: status filter: pending | confirmed | all (default all)
    $status = isset($_POST['status']) ? strtolower(trim((string)$_POST['status'])) : (isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : 'all');
    if (!in_array($status, ['pending','confirmed','all'], true)) { $status = 'all'; }

    // If table doesn't exist, nothing to clear
    $tableExists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'partner_payments'");
        $tableExists = (bool)$stmt->fetchColumn();
    } catch (Exception $e) { $tableExists = false; }

    $deleted = 0;
    if ($tableExists) {
        if ($partner !== '') {
            if ($status === 'all') {
                $stmt = $pdo->prepare("DELETE FROM partner_payments WHERE partner_name = ?");
                $stmt->execute([$partner]);
            } else {
                $confirmedVal = ($status === 'confirmed') ? 1 : 0;
                $stmt = $pdo->prepare("DELETE FROM partner_payments WHERE partner_name = ? AND confirmed = ?");
                $stmt->execute([$partner, $confirmedVal]);
            }
            $deleted = $stmt->rowCount();
            // Recompute only this partner
            updatePartnerBenefits($partner);
        } else {
            if ($status === 'all') {
                // Count first for reporting
                try { $cnt = $pdo->query("SELECT COUNT(*) FROM partner_payments")->fetchColumn(); $deleted = (int)$cnt; } catch (Exception $ignored) {}
                $pdo->exec("TRUNCATE TABLE partner_payments");
            } else {
                $confirmedVal = ($status === 'confirmed') ? 1 : 0;
                try { $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM partner_payments WHERE confirmed = ?"); $cntStmt->execute([$confirmedVal]); $deleted = (int)$cntStmt->fetchColumn(); } catch (Exception $ignored) {}
                $stmt = $pdo->prepare("DELETE FROM partner_payments WHERE confirmed = ?");
                $stmt->execute([$confirmedVal]);
            }
            // Recompute all partners
            try {
                $rows = $pdo->query("SELECT partner_name FROM partner_benefits")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($rows as $p) { updatePartnerBenefits($p); }
            } catch (Exception $ignored) {}
        }
    }

    echo json_encode(['success' => true, 'message' => 'Historial de pagos eliminado', 'deleted' => $deleted, 'scope' => $partner !== '' ? $partner : 'all', 'status' => $status]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
