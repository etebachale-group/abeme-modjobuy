<?php
// Configuración de la API de WhatsApp (ULTRAMSG)
// 1. Regístrate en https://ultramsg.com/ para obtener una prueba gratuita.
// 2. Escanea el código QR para vincular tu número de WhatsApp.
// 3. Pega tu Instance ID y Token aquí abajo.

define('WHATSAPP_INSTANCE_ID', 'TU_INSTANCE_ID'); // Pega tu Instance ID aquí
define('WHATSAPP_TOKEN', 'TU_TOKEN');           // Pega tu Token aquí

// URL base de tu aplicación
// Asegúrate de que esta URL sea accesible desde internet para que WhatsApp pueda descargar el PDF.
// Si estás en un entorno local como XAMPP, necesitarás usar un servicio como ngrok para exponer tu servidor.
define('APP_URL', 'http://localhost/abeme-modjobuy');

?>