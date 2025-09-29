<?php
// =================================================================
// ARCHIVO DE CONFIGURACIÓN DE LA BASE DE DATOS
// =================================================================

// Utiliza aquí las credenciales de tu base de datos.
// Estos son los datos que leí de tu archivo anterior.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u256037680_barbers');
define('DB_PASSWORD', 'Barbers0100*');
// Ojo: En tu imagen la BD se llama 'u256037680_barbers', pero en el
// archivo de config anterior era 'u256037680_berbers'.
// Usaré 'u256037680_barbers' como en la imagen. Corrige si es necesario.
define('DB_NAME', 'u256037680_berbers');

// Crear la conexión a la base de datos con MySQLi
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar si la conexión falló
if ($mysqli->connect_error) {
    // Usar die() es simple para desarrollo, pero en producción
    // es mejor registrar el error y mostrar una página amigable.
    die("Error de conexión a la base de datos: " . $mysqli->connect_error);
}

// Establecer el juego de caracteres a UTF-8 para soportar tildes y caracteres especiales
if (!$mysqli->set_charset("utf8mb4")) {
    // Si falla, puedes registrar el error, aunque es raro que falle.
    // printf("Error cargando el conjunto de caracteres utf8mb4: %s\n", $mysqli->error);
}

// Nota: No cierres la conexión aquí ($mysqli->close()).
// Se debe cerrar al final de los scripts que la incluyen.
?>