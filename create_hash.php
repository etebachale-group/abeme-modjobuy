<?php
// Elige una contraseña segura y que recuerdes
$password = 'Ferche@6849';

// Genera el hash para la contraseña
$hash = password_hash($password, PASSWORD_DEFAULT);

// Muestra el hash para que puedas copiarlo
echo 'Copia este hash y pégalo en la columna "password" de tu usuario en la base de datos:<br><br>';
echo $hash;
?>
