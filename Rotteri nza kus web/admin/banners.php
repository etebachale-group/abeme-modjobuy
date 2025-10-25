<?php
// Anti-cache para asegurar siempre versión más reciente del panel
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/functions.php';

// ----------------------------------------------
// Helpers utilitarios locales (refactor limpieza)
// ----------------------------------------------
function banner_log($msg){
    static $enabled = true; // podría togglearse con una constante
    if (!$enabled) return;
    $logDir = __DIR__ . '/../var';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    $file = $logDir . '/log_banner_uploads.log';
    $line = '['.date('Y-m-d H:i:s').'] '.$msg."\n";
    @file_put_contents($file, $line, FILE_APPEND);
}
function send_json($data, $status=200){
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return true;
}
function ensure_banners_dir(){
    $dir = realpath(__DIR__ . '/../uploads/banners');
    if ($dir) return $dir;
    $fallback = __DIR__ . '/../uploads/banners';
    if (!is_dir($fallback)) { @mkdir($fallback, 0775, true); }
    return realpath($fallback) ?: $fallback;
}
function fetch_all_banners(PDO $pdo){
    try {
        $rs = $pdo->query("SELECT * FROM homepage_banners ORDER BY sort_order ASC, created_at DESC");
        return $rs? $rs->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch(Throwable $e){ banner_log('Fetch banners error: '.$e->getMessage()); return []; }
}

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
$err = null; // Inicializar para evitar Notice si no se asigna en flujo POST

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
    if (!function_exists('getimagesize')) return; // GD no disponible
    [$w,$h,$type] = @getimagesize($file); if (!$w || !$h) return;
    $maxW=1600; $maxH=900; if ($w<=$maxW && $h<=$maxH) return;
    // Verificar funciones según tipo
    $can = [
        IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg'),
        IMAGETYPE_PNG  => function_exists('imagecreatefrompng'),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
    ];
    if (empty($can[$type])) return;
    $ratio = min($maxW/$w, $maxH/$h); $nw = (int)floor($w*$ratio); $nh=(int)floor($h*$ratio);
    switch ($type){
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($file); break;
        case IMAGETYPE_PNG: $src = @imagecreatefrompng($file); break;
        case IMAGETYPE_WEBP: $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : null; break;
        default: $src = null;
    }
    if (!$src) return;
    $dst = imagecreatetruecolor($nw,$nh);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP){
        imagealphablending($dst,false); imagesavealpha($dst,true);
        $transparent = imagecolorallocatealpha($dst, 0,0,0,127); imagefilledrectangle($dst,0,0,$nw,$nh,$transparent);
    }
    if (!function_exists('imagecopyresampled')) { imagedestroy($src); imagedestroy($dst); return; }
    imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
    if ($type === IMAGETYPE_JPEG && function_exists('imagejpeg')){ @imagejpeg($dst,$file,82); }
    elseif ($type === IMAGETYPE_PNG && function_exists('imagepng')){ @imagepng($dst,$file,6); }
    elseif ($type === IMAGETYPE_WEBP && function_exists('imagewebp')){ @imagewebp($dst,$file,82); }
    imagedestroy($src); imagedestroy($dst);
}

// Handle create/update/delete actions
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
// Detectar si es una petición AJAX (fetch/XHR) para responder JSON
$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (isset($_POST['ajax']) && $_POST['ajax']=='1') || (isset($_GET['ajax']) && $_GET['ajax']=='1');

// (logging helper movido arriba con mejoras menores)

if ($method === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        if ($isAjax) { send_json(['ok'=>false,'error'=>'CSRF token inválido'], 400); exit; }
        die('Token inválido');
    }
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
        $err = $err ?? null; // asegurar variable
        // ----------------------
        // Manejo de subida imagen
        // ----------------------
        try {
            if (!empty($_FILES['image_file']['name'])) {
                $f = $_FILES['image_file'];
                // Mapear errores comunes de upload para mensaje más claro
                $uploadErrors = [
                    UPLOAD_ERR_OK => 'OK',
                    UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize en php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'El archivo excede MAX_FILE_SIZE del formulario',
                    UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                    UPLOAD_ERR_NO_FILE => 'No se envió archivo',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal en el servidor',
                    UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco (permisos)',
                    UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
                ];
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $msg = $uploadErrors[$f['error']] ?? ('Error desconocido de subida código '.$f['error']);
                    throw new Exception('Error al subir la imagen: ' . $msg);
                }
                if (!is_uploaded_file($f['tmp_name'])) {
                    throw new Exception('El archivo no fue subido mediante HTTP POST');
                }
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                $mime = @mime_content_type($f['tmp_name']);
                if (!$mime || !isset($allowed[$mime])) {
                    throw new Exception('Formato no permitido (solo JPG, PNG, WEBP)');
                }
                if ($f['size'] > 3*1024*1024) throw new Exception('La imagen supera 3MB');
                $ext = $allowed[$mime];
                // Asegurar existencia de directorio destino (crear recursivamente si falta)
                $destDir = ensure_banners_dir();
                // Validar que el directorio es escribible
                if (!is_writable($destDir)) {
                    throw new Exception('El directorio de destino no es escribible: ' . $destDir);
                }
                $name = 'banner_' . date('Ymd_His') . '_' . substr(md5(($f['name'] ?? '') . microtime()),0,6) . '.' . $ext;
                $dest = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
                if (!@move_uploaded_file($f['tmp_name'], $dest)) {
                    throw new Exception('No se pudo mover el archivo subido al destino final.');
                }
                resize_if_large($dest); // Redimensionar si es muy grande
                $image_url = 'uploads/banners/' . $name; // Ruta relativa almacenada
            } else {
                // Validar ruta manual si es local (no http) y no viene vacía
                if ($image_url && !preg_match('#^https?://#i',$image_url)) {
                    if (!rk_banner_exists($image_url)) {
                        throw new Exception('La ruta de imagen indicada no existe en el servidor.');
                    }
                }
            }
        } catch (Throwable $e) { $err = $e->getMessage(); banner_log('Upload error: '.$err); }
        // Si hubo error no avanzamos a DB
        if (!empty($err)) {
            if ($isAjax) { send_json(['ok'=>false,'error'=>$err]); exit; }
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO homepage_banners (title, subtitle, image_url, cta_text, cta_url, is_active, sort_order) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$title,$subtitle,$image_url,$cta_text,$cta_url,$is_active,$sort_order]);
                    banner_log('Insert banner OK: '.$image_url);
                } else {
                    $stmt = $pdo->prepare("UPDATE homepage_banners SET title=?, subtitle=?, image_url=?, cta_text=?, cta_url=?, is_active=?, sort_order=? WHERE id=?");
                    $stmt->execute([$title,$subtitle,$image_url,$cta_text,$cta_url,$is_active,$sort_order,$id]);
                    if (!empty($old) && isset($old['image_url']) && $old['image_url'] !== $image_url){ safe_unlink_url($old['image_url']); }
                    banner_log('Update banner ID '.$id.' OK: '.$image_url);
                }
                if ($isAjax) { send_json(['ok'=>true,'banners'=>fetch_all_banners($pdo)]); exit; }
                header('Location: banners.php?ok=1'); exit;
            } catch (Throwable $e) { $err = $e->getMessage(); banner_log('DB error: '.$err); }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $old = [];
            try{ $st=$pdo->prepare("SELECT * FROM homepage_banners WHERE id=?"); $st->execute([$id]); $old = $st->fetch(PDO::FETCH_ASSOC) ?: []; }catch(Throwable $e2){}
            $pdo->prepare("DELETE FROM homepage_banners WHERE id=?")->execute([$id]);
            if (!empty($old['image_url'])) safe_unlink_url($old['image_url']);
            if ($isAjax) { send_json(['ok'=>true,'deleted'=>$id,'banners'=>fetch_all_banners($pdo)]); exit; }
            header('Location: banners.php?ok=1'); exit;
        } catch (Throwable $e) { $err = $e->getMessage(); }
    }
}

// Fetch banners
$banners = fetch_all_banners($pdo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Banners - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/modern.css">
    <style>
        .admin-container { max-width:1000px; margin:0 auto; padding:20px; }
        .admin-header { background:#2c3e50; color:#fff; padding:16px; border-radius:8px; margin-bottom:16px; }
    /* Admin nav menu styles intentionally removed to use default browser styles */
        .grid { display:grid; grid-template-columns: repeat(auto-fill,minmax(260px,1fr)); gap:16px; }
    .card { border:1px solid rgba(255,255,255,.08); border-radius:10px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,.25); background: linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85)); color:#e5e7eb; }
        .card img { width:100%; height:160px; object-fit:cover; }
        .card-body { padding:12px; }
        .row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .btn { padding:8px 12px; border:none; border-radius:6px; font-weight:600; cursor:pointer; }
        .btn-primary { background:#3498db; color:#fff; }
        .btn-danger { background:#e74c3c; color:#fff; }
        .btn-secondary { background:#95a5a6; color:#fff; }
        form.inline { display:inline; }
    .form { background: linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85)); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:12px; margin-bottom:18px; color:#e5e7eb; }
    .form .field { margin-bottom:10px; }
    .form label { display:block; font-weight:600; margin-bottom:6px; }
    .form input[type=text], .form input[type=url], .form input[type=number] { width:100%; padding:8px; border:1px solid rgba(255,255,255,.2); border-radius:6px; background: rgba(255,255,255,.06); color:#e5e7eb; }
    </style>
    <script>
        // UI Version marker (cambiar si se modifican scripts)
        window.BANNERS_UI_VERSION = '1.0.1';
        console.log('Banners UI version', window.BANNERS_UI_VERSION);
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
    <?php include __DIR__ . '/../includes/layout_header.php'; ?>
    <div class="admin-container">
        <div class="admin-header"><h1>Gestionar Banners</h1></div>
        <?php include __DIR__ . '/../includes/admin_navbar.php'; ?>
    <?php if (!empty($err)): ?><div style="color:#e74c3c; padding:8px 0;">Error: <?php echo htmlspecialchars($err); ?></div><script>console.error('Banner error: <?php echo addslashes($err); ?>');</script><?php endif; ?>
        <?php if (!empty($_GET['ok'])): ?><div style="color:#27ae60; padding:8px 0;">Guardado correctamente.</div><?php endif; ?>

        <div class="form">
            <h3 id="formTitle">Crear Banner</h3>
            <form id="bannerForm" method="post" enctype="multipart/form-data" onsubmit="return submitBannerForm(event)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="id" value="">
                <div class="field"><label>Título</label><input type="text" name="title" maxlength="200"></div>
                <div class="field"><label>Subtítulo</label><input type="text" name="subtitle" maxlength="300"></div>
                <div class="field"><label>URL de Imagen</label><input type="url" name="image_url" placeholder="https://... o uploads/banners/archivo.jpg (opcional si subes archivo)" maxlength="600"></div>
                <div style="font-size:.75rem; opacity:.8; margin-top:-6px; margin-bottom:8px;">Si utilizas el campo "Subir Imagen" no completes la URL. Para imágenes locales ya existentes usa el formato relativo: uploads/banners/mi_imagen.jpg (sin ../). Para externas, pega la URL completa con https://</div>
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
    <script>
    async function submitBannerForm(e){
        e.preventDefault();
        const form = e.target;
        const fd = new FormData(form);
        fd.append('ajax','1');
        const btn = form.querySelector('button[type="submit"]');
        if(btn){ btn.disabled = true; btn.dataset.old = btn.innerHTML; btn.innerHTML='Guardando...'; }
        try{
            const res = await fetch(form.action || 'banners.php', { method:'POST', body: fd });
            const text = await res.text();
            let data = null; try { data = JSON.parse(text); } catch(parseErr){ console.error('Respuesta no JSON:', text.slice(0,400)); }
            if(!data){ throw new Error('Respuesta inválida del servidor'); }
            if(!data.ok){
                alert('Error: ' + (data.error || 'Desconocido'));
                return false;
            }
            renderBanners(data.banners || []);
            resetForm();
        }catch(err){
            alert('Fallo en la petición: '+ err.message);
        }finally{
            if(btn){ btn.disabled=false; btn.innerHTML=btn.dataset.old || 'Guardar'; }
        }
        return false;
    }
    function renderBanners(list){
        const grid = document.querySelector('.grid');
        if(!grid) return;
        grid.innerHTML = list.map(b => {
            const img = b.image_url ? `<img src="${toAdminURL(b.image_url)}" alt="banner">` : '';
            return `<div class="card">${img}<div class="card-body">`
                + `<h4 style="margin:0 0 6px;">${escapeHtml(b.title||'Sin título')}</h4>`
                + `<div style="font-size:.9rem; color:#555; margin-bottom:6px;">${escapeHtml(b.subtitle||'')}</div>`
                + `<div class="row" style="margin-bottom:8px;"><span>CTA: ${escapeHtml(b.cta_text||'—')}</span>`
                + (b.cta_url?`<a href="${escapeAttr(b.cta_url)}" target="_blank" style="margin-left:auto;">Abrir</a>`:'')
                + `</div>`
                + `<div class="row"><span>${b.is_active==1?'Activo':'Inactivo'}</span><span style="margin-left:auto;">Orden: ${parseInt(b.sort_order||0)}</span></div>`
                + `<div class="row" style="margin-top:10px; gap:8px;">`
                + `<button class="btn btn-primary" onclick="fillEdit(${parseInt(b.id)})"><i class="fas fa-edit"></i> Editar</button>`
                + `<form class="inline" method="post" onsubmit="return deleteBanner(event, ${parseInt(b.id)})"><input type="hidden" name="csrf_token" value="${escapeAttr('<?php echo $csrf; ?>')}"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${parseInt(b.id)}"><button class="btn btn-danger" type="submit"><i class="fas fa-trash"></i> Eliminar</button></form>`
                + `</div></div></div>`
                + `<script type=\"application/json\" id=\"banner-data-${parseInt(b.id)}\">${JSON.stringify(b).replace(/</g,'\\u003c')}</script>`;
        }).join('');
    }
    async function deleteBanner(ev,id){
        ev.preventDefault();
        if(!confirm('¿Eliminar banner?')) return false;
        const form = ev.target;
        const fd = new FormData(form);
        fd.append('ajax','1');
        try {
            const res = await fetch('banners.php', {method:'POST', body: fd});
            const text = await res.text();
            let data = null; try { data = JSON.parse(text); } catch(e){ console.error('No JSON en delete:', text.slice(0,400)); }
            if(!data){ alert('Respuesta inválida del servidor'); return false; }
            if(!data.ok){ alert('Error al eliminar: '+(data.error||'')); return false; }
            renderBanners(data.banners||[]);
        } catch(err){ alert('Fallo al eliminar: '+err.message); }
        return false;
    }
    // Escapa caracteres peligrosos para evitar inyección HTML XSS al renderizar datos de la base.
    function escapeHtml(s){
        return String(s).replace(/[&<>"']/g, c => ({
            '&':'&amp;',
            '<':'&lt;',
            '>':'&gt;',
            '"':'&quot;',
            "'":'&#39;'
        })[c]);
    }
    function escapeAttr(s){ return escapeHtml(s); }
    function toAdminURL(u){ if(!u) return ''; if(/^https?:\/\//i.test(u)) return u; if(u.startsWith('../')) return u; return '../'+u.replace(/^\/+/, ''); }
    </script>
</body>
</html>
<script>
(function(){
    const toggle=document.querySelector('.menu-toggle');
    const nav=document.querySelector('.nav');
    toggle?.addEventListener('click', ()=> nav?.classList.toggle('open'));
    document.querySelectorAll('.nav .nav-menu a').forEach(a=> a.addEventListener('click', ()=> nav?.classList.remove('open')));
})();
</script>