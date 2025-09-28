<?php
// Configuración de la base de datos
define('DB_SERVER', 'localhost'); // Usualmente 'localhost'
define('DB_USERNAME', 'u256037680_barbers');   // Tu nombre de usuario de MySQL
define('DB_PASSWORD', 'Barbers0100*');       // Tu contraseña de MySQL
define('DB_NAME', 'u256037680_berbers'); // El nombre de la base de datos que creamos

// Conexión a la base de datos
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar la conexión
if ($mysqli->connect_error) {
    die("Error de conexión a la base de datos: " . $mysqli->connect_error);
}

// Opcional: Establecer el juego de caracteres a utf8
$mysqli->set_charset("utf8");
?>