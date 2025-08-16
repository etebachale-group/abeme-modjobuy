<?php
// Simular envío de correo (en un sistema real usarías PHPMailer o similar)
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';

// Procesar archivo adjunto si existe
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file_name = $_FILES['attachment']['name'];
    $file_tmp = $_FILES['attachment']['tmp_name'];
    $file_type = $_FILES['attachment']['type'];
    $file_size = $_FILES['attachment']['size'];
    
    // Mover el archivo a una carpeta de uploads
    $upload_dir = 'uploads/';
    $upload_path = $upload_dir . basename($file_name);
    move_uploaded_file($file_tmp, $upload_path);
}

// Aquí iría el código para enviar el correo a dreammotivationig@gmail.com
// Por simplicidad, solo redirigimos con un mensaje de éxito

session_start();
$_SESSION['contact_success'] = "Gracias por contactarnos! Tu mensaje ha sido enviado a dreammotivationig@gmail.com";

header('Location: index.php#contact');
exit;
?>