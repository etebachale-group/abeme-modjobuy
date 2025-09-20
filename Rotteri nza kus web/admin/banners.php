<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

// Defensive: ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS homepage_banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NULL,
        subtitle VARCHAR(300) NULL,
        image_url VARCHAR(600) NULL,
        cta_text VARCHAR(120) NULL,
        cta_url VARCHAR(600) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $ignore) {}

// CSRF token
$csrf = csrf_token();

// Helpers for file paths and image processing
function banners_uploads_dir(){ return realpath(__DIR__ . '/../uploads/banners'); }
function get_full_path_from_url($u){
    if (!$u || preg_match('#^https?://#i',$u)) return null;
    $u = str_replace('\\','/',$u);
    $u = preg_replace('#^\./#','',$u);
    if (strpos($u,'../')===0) { $u = substr($u,3); }
    $base = realpath(__DIR__ . '/..');
    if (!$base) return null;
    $full = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($u,'/'));
    return file_exists($full) ? realpath($full) : $full;
}
function is_under_uploads($full){
    $root = banners_uploads_dir(); if (!$full || !$root) return false;
    $f = str_replace('\\','/',$full); $r = str_replace('\\','/',$root);
    return strpos($f, rtrim($r,'/')) === 0;
}
function safe_unlink_url($u){ $full = get_full_path_from_url($u); if ($full && is_under_uploads($full) && file_exists($full)) { @unlink($full); } }
function to_admin_url($u){
    if (!$u) return '';
    if (preg_match('#^https?://#i',$u)) return $u;
    // Stored paths are root-relative (e.g., 'uploads/banners/x.jpg'). From admin/, prefix '../'.
    if (strpos($u,'../')===0) return $u; // already relative to admin
    return '../' . ltrim($u,'/');
}
function resize_if_large($file){
    if (!file_exists($file)) return;
    [$w,$h,$type] = @getimagesize($file); if (!$w || !$h) return;
    $maxW=1600; $maxH=900; if ($w<=$maxW && $h<=$maxH) return;
    $ratio = min($maxW/$w, $maxH/$h); $nw = (int)floor($w*$ratio); $nh=(int)floor($h*$ratio);
    switch ($type){
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($file); break;
        case IMAGETYPE_PNG: $src = @imagecreatefrompng($file); break;
        case IMAGETYPE_WEBP: $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : null; break;
        default: $src = null;
    }
    if (!$src) return;
    $dst = imagecreatetruecolor($nw,$nh);
    // Preserve transparency for PNG/WEBP
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP){
        imagealphablending($dst,false); imagesavealpha($dst,true);
        $transparent = imagecolorallocatealpha($dst, 0,0,0,127); imagefilledrectangle($dst,0,0,$nw,$nh,$transparent);
    }
    imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
    if ($type === IMAGETYPE_JPEG){ @imagejpeg($dst,$file,82); }
    elseif ($type === IMAGETYPE_PNG){ @imagepng($dst,$file,6); }
    elseif ($type === IMAGETYPE_WEBP && function_exists('imagewebp')){ @imagewebp($dst,$file,82); }
    imagedestroy($src); imagedestroy($dst);
}

// Handle create/update/delete actions
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) { die('Token inválido'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $old = [];
        if ($action === 'update' && $id>0){ try{ $st=$pdo->prepare("SELECT * FROM homepage_banners WHERE id=?"); $st->execute([$id]); $old = $st->fetch(PDO::FETCH_ASSOC) ?: []; }catch(Throwable $e){} }
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $cta_text = trim($_POST['cta_text'] ?? '');
        $cta_url = trim($_POST['cta_url'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        // Upload handling (optional)
        try {
            if (!empty($_FILES['image_file']['name']) && is_uploaded_file($_FILES['image_file']['tmp_name'])){
                $f = $_FILES['image_file'];
                if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Error al subir el archivo');
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                $mime = mime_content_type($f['tmp_name']);
                if (!isset($allowed[$mime])) throw new Exception('Formato no permitido (solo JPG, PNG, WEBP)');
                if ($f['size'] > 3*1024*1024) throw new Exception('La imagen supera 3MB');
                $ext = $allowed[$mime];
                $name = 'banner_' . date('Ymd_His') . '_' . substr(md5($f['name'] . microtime()),0,6) . '.' . $ext;
                $destDir = realpath(__DIR__ . '/../uploads/banners');
                if (!$destDir) { $destDir = __DIR__ . '/../uploads/banners'; @mkdir($destDir, 0775, true); }
                $dest = $destDir . DIRECTORY_SEPARATOR . $name;
                if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception('No se pudo mover el archivo subido');
                // Resize to save space if very large
                resize_if_large($dest);
                // Store path root-relative for frontend (admin will prefix '../' when rendering)
                $image_url = 'uploads/banners/' . $name;
            }
        } catch (Throwable $e) { $err = $e->getMessage(); }
        try {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO homepage_banners (title, subtitle, image_url, cta_text, cta_url, is_active, sort_order) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$title,$subtitle,$image_url,$cta_text,$cta_url,$is_active,$sort_order]);
            } else {
                $stmt = $pdo->prepare("UPDATE homepage_banners SET title=?, subtitle=?, image_url=?, cta_text=?, cta_url=?, is_active=?, sort_order=? WHERE id=?");
                $stmt->execute([$title,$subtitle,$image_url,$cta_text,$cta_url,$is_active,$sort_order,$id]);
                // If image path changed, clean old file under uploads/banners
                if (!empty($old) && isset($old['image_url']) && $old['image_url'] !== $image_url){ safe_unlink_url($old['image_url']); }
            }
            header('Location: banners.php?ok=1'); exit;
        } catch (Throwable $e) { $err = $e->getMessage(); }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $old = [];
            try{ $st=$pdo->prepare("SELECT * FROM homepage_banners WHERE id=?"); $st->execute([$id]); $old = $st->fetch(PDO::FETCH_ASSOC) ?: []; }catch(Throwable $e2){}
            $pdo->prepare("DELETE FROM homepage_banners WHERE id=?")->execute([$id]);
            if (!empty($old['image_url'])) safe_unlink_url($old['image_url']);
            header('Location: banners.php?ok=1'); exit;
        } catch (Throwable $e) { $err = $e->getMessage(); }
    }
}

// Fetch banners
$banners = [];
try {
    $stmt = $pdo->query("SELECT * FROM homepage_banners ORDER BY sort_order ASC, created_at DESC");
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $banners = []; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Banners - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-container { max-width:1000px; margin:0 auto; padding:20px; }
        .admin-header { background:#2c3e50; color:#fff; padding:16px; border-radius:8px; margin-bottom:16px; }
        .admin-nav { background:#34495e; color:#fff; padding:10px 16px; border-radius:6px; margin-bottom:16px; }
        .admin-nav a { color:#fff; margin-right:12px; text-decoration:none; }
        .grid { display:grid; grid-template-columns: repeat(auto-fill,minmax(260px,1fr)); gap:16px; }
        .card { border:1px solid #eee; border-radius:10px; overflow:hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.04); background:#fff; }
        .card img { width:100%; height:160px; object-fit:cover; }
        .card-body { padding:12px; }
        .row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .btn { padding:8px 12px; border:none; border-radius:6px; font-weight:600; cursor:pointer; }
        .btn-primary { background:#3498db; color:#fff; }
        .btn-danger { background:#e74c3c; color:#fff; }
        .btn-secondary { background:#95a5a6; color:#fff; }
        form.inline { display:inline; }
        .form { background:#fff; border:1px solid #eee; border-radius:10px; padding:12px; margin-bottom:18px; }
        .form .field { margin-bottom:10px; }
        .form label { display:block; font-weight:600; margin-bottom:6px; }
        .form input[type=text], .form input[type=url], .form input[type=number] { width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; }
    </style>
    <script>
        function fillEdit(id){
            const item = JSON.parse(document.getElementById('banner-data-'+id).textContent);
            for (const k of ['id','title','subtitle','image_url','cta_text','cta_url','sort_order']){
                const el = document.querySelector('#bannerForm [name="'+k+'"]');
                if (el) el.value = item[k] || '';
            }
            document.querySelector('#bannerForm [name="is_active"]').checked = String(item.is_active)==='1';
            document.querySelector('#bannerForm [name="action"]').value = 'update';
            document.getElementById('formTitle').textContent = 'Editar Banner';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        function resetForm(){
            document.getElementById('bannerForm').reset();
            document.querySelector('#bannerForm [name="id"]').value = '';
            document.querySelector('#bannerForm [name="action"]').value = 'create';
            document.getElementById('formTitle').textContent = 'Crear Banner';
        }
    </script>
    </head>
<body>
    <div class="admin-container">
        <div class="admin-header"><h1>Gestionar Banners</h1></div>
        <div class="admin-nav">
            <a href="index.php">Productos</a>
            <a href="orders.php">Pedidos</a>
            <a href="banners.php" class="active">Banners</a>
            <a href="settings.php">Configuración</a>
        </div>
        <?php if (!empty($err)): ?><div style="color:#e74c3c; padding:8px 0;">Error: <?php echo htmlspecialchars($err); ?></div><?php endif; ?>
        <?php if (!empty($_GET['ok'])): ?><div style="color:#27ae60; padding:8px 0;">Guardado correctamente.</div><?php endif; ?>

        <div class="form">
            <h3 id="formTitle">Crear Banner</h3>
            <form id="bannerForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="id" value="">
                <div class="field"><label>Título</label><input type="text" name="title" maxlength="200"></div>
                <div class="field"><label>Subtítulo</label><input type="text" name="subtitle" maxlength="300"></div>
                <div class="field"><label>URL de Imagen</label><input type="url" name="image_url" placeholder="https://... o ../uploads/banners/archivo.jpg" maxlength="600"></div>
                <div class="field"><label>Subir Imagen</label><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"></div>
                <div class="field"><label>Texto del Botón</label><input type="text" name="cta_text" maxlength="120" placeholder="Ver oferta"></div>
                <div class="field"><label>Enlace del Botón</label><input type="url" name="cta_url" placeholder="https://..." maxlength="600"></div>
                <div class="row">
                    <label style="display:flex; align-items:center; gap:6px;"><input type="checkbox" name="is_active" checked> Activo</label>
                    <div style="margin-left:auto; display:flex; align-items:center; gap:6px;">
                        <label>Orden</label>
                        <input type="number" name="sort_order" value="0" style="width:100px">
                    </div>
                </div>
                <div class="row" style="margin-top:10px;">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Guardar</button>
                    <button class="btn btn-secondary" type="button" onclick="resetForm()"><i class="fas fa-undo"></i> Limpiar</button>
                </div>
            </form>
        </div>

        <div class="grid">
            <?php foreach ($banners as $b): ?>
            <div class="card">
                <?php if (!empty($b['image_url'])): ?><img src="<?php echo htmlspecialchars(to_admin_url($b['image_url'])); ?>" alt="banner"><?php endif; ?>
                <div class="card-body">
                    <h4 style="margin:0 0 6px;"><?php echo htmlspecialchars($b['title'] ?: 'Sin título'); ?></h4>
                    <div style="font-size:.9rem; color:#555; margin-bottom:6px;"><?php echo htmlspecialchars($b['subtitle'] ?: ''); ?></div>
                    <div class="row" style="margin-bottom:8px;">
                        <span>CTA: <?php echo htmlspecialchars($b['cta_text'] ?: '—'); ?></span>
                        <?php if (!empty($b['cta_url'])): ?><a href="<?php echo htmlspecialchars($b['cta_url']); ?>" target="_blank" style="margin-left:auto;">Abrir</a><?php endif; ?>
                    </div>
                    <div class="row">
                        <span><?php echo $b['is_active'] ? 'Activo' : 'Inactivo'; ?></span>
                        <span style="margin-left:auto;">Orden: <?php echo (int)$b['sort_order']; ?></span>
                    </div>
                    <div class="row" style="margin-top:10px; gap:8px;">
                        <button class="btn btn-primary" onclick="fillEdit(<?php echo (int)$b['id']; ?>)"><i class="fas fa-edit"></i> Editar</button>
                        <form class="inline" method="post" onsubmit="return confirm('¿Eliminar banner?')">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                            <button class="btn btn-danger" type="submit"><i class="fas fa-trash"></i> Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
            <script type="application/json" id="banner-data-<?php echo (int)$b['id']; ?>"><?php echo json_encode($b, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?></script>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
