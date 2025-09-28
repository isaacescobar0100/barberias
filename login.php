<?php
session_start();
require_once 'db_config.php'; // Asumo que este archivo existirá

// Si el usuario ya está logueado, redirigir según su rol
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_rol'] === 'superadmin') {
        header("Location: superadmin_dashboard.php");
        exit();
    } else {
        // Redirigir al dashboard de admin normal (puedes cambiar el nombre del archivo)
        header("Location: admin_dashboard.php");
        exit();
    }
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $mysqli->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = "Por favor, ingrese su correo y contraseña.";
    } else {
        $sql = "SELECT id, nombre, email, password, rol, barberia_id FROM usuarios WHERE email = ?";

        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();

                // Verificar la contraseña
                if (password_verify($password, $user['password'])) {
                    // Verificar si la barbería del admin está activa
                    if ($user['rol'] === 'admin') {
                        $barberia_sql = "SELECT activa FROM barberias WHERE id = ?";
                        if($barberia_stmt = $mysqli->prepare($barberia_sql)) {
                            $barberia_stmt->bind_param("i", $user['barberia_id']);
                            $barberia_stmt->execute();
                            $barberia_result = $barberia_stmt->get_result()->fetch_assoc();
                            if(!$barberia_result || $barberia_result['activa'] == 0) {
                                $error_message = "Esta barbería ha sido desactivada. Contacte al superadministrador.";
                                goto end_login_process; // Salir del proceso de login
                            }
                        }
                    }

                    // Iniciar sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nombre'] = $user['nombre'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_rol'] = $user['rol'];
                    $_SESSION['user_barberia_id'] = $user['barberia_id'];

                    // Redirigir según el rol
                    if ($user['rol'] === 'superadmin') {
                        header("Location: superadmin_dashboard.php");
                        exit();
                    } else {
                        header("Location: registro/index.php"); // Redirigir al panel de admin principal
                        exit();
                    }
                } else {
                    $error_message = "La contraseña es incorrecta.";
                }
            } else {
                $error_message = "No se encontró un usuario con ese correo electrónico.";
            }
            $stmt->close();
        } else {
            $error_message = "Error en la preparación de la consulta.";
        }
    }
}
end_login_process:
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | BarberShop Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login_styles.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <svg class="logo-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="6" y="2" width="12" height="20" rx="2"/>
                    <path d="M6 8h12M6 14h12"/>
                    <circle cx="12" cy="5" r="1"/>
                    <circle cx="12" cy="11" r="1"/>
                    <circle cx="12" cy="17" r="1"/>
                </svg>
                <h1>BarberShop Pro</h1>
                <p>Sistema de Gestión</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-banner">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" placeholder="superadmin@example.com" required>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-login">Iniciar Sesión</button>
            </form>
            <div class="login-footer">
                <p>¿No tienes acceso? Contacta al administrador.</p>
            </div>
        </div>
    </div>
</body>
</html>