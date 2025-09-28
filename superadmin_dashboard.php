<?php
session_start();
require_once 'db_config.php';

// 1. Proteger la página y verificar el rol de superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$error = '';

// 2. Procesar acciones POST (Crear barbería, Crear admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Crear nueva barbería
    if (isset($_POST['crear_barberia'])) {
        $nombre_barberia = $mysqli->real_escape_string($_POST['nombre_barberia']);
        if (!empty($nombre_barberia)) {
            $sql = "INSERT INTO barberias (nombre) VALUES (?)";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("s", $nombre_barberia);
                if ($stmt->execute()) {
                    $mensaje = "Barbería creada con éxito.";
                } else {
                    $error = "Error al crear la barbería: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $error = "El nombre de la barbería no puede estar vacío.";
        }
    }

    // Crear nuevo administrador
    if (isset($_POST['crear_admin'])) {
        $nombre_admin = $mysqli->real_escape_string($_POST['nombre_admin']);
        $email_admin = $mysqli->real_escape_string($_POST['email_admin']);
        $password_admin = $_POST['password_admin'];
        $barberia_id_admin = (int)$_POST['barberia_id_admin'];

        if (!empty($nombre_admin) && !empty($email_admin) && !empty($password_admin) && $barberia_id_admin > 0) {
            // Hashear la contraseña
            $hashed_password = password_hash($password_admin, PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios (nombre, email, password, rol, barberia_id) VALUES (?, ?, ?, 'admin', ?)";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("sssi", $nombre_admin, $email_admin, $hashed_password, $barberia_id_admin);
                if ($stmt->execute()) {
                    $mensaje = "Administrador creado con éxito.";
                } else {
                    // Manejar error de email duplicado
                    if ($stmt->errno == 1062) {
                        $error = "Ya existe un usuario con ese correo electrónico.";
                    } else {
                        $error = "Error al crear el administrador: " . $stmt->error;
                    }
                }
                $stmt->close();
            }
        } else {
            $error = "Todos los campos para crear un administrador son obligatorios.";
        }
    }
}

// 3. Procesar acciones GET (Habilitar/Deshabilitar barbería)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $barberia_id = (int)$_GET['id'];

    if ($_GET['action'] === 'toggle_status') {
        // Obtenemos el estado actual y lo invertimos
        $sql = "UPDATE barberias SET activa = NOT activa WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $barberia_id);
            $stmt->execute();
            $stmt->close();
            header("Location: superadmin_dashboard.php");
            exit();
        }
    }
}


// 4. Obtener datos para mostrar en la página
// Lista de todas las barberías
$barberias_query = $mysqli->query("SELECT id, nombre, activa, fecha_creacion FROM barberias ORDER BY nombre");

// Lista de todos los administradores
$admins_query = $mysqli->query("
    SELECT u.id, u.nombre, u.email, u.fecha_creacion, b.nombre as nombre_barberia
    FROM usuarios u
    JOIN barberias b ON u.barberia_id = b.id
    WHERE u.rol = 'admin'
    ORDER BY u.nombre
");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_styles.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>Panel de Superadministrador</h1>
            <div class="user-info">
                <span>Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></strong></span>
                <a href="logout.php" class="btn btn-logout">Cerrar Sesión</a>
            </div>
        </header>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <main class="dashboard-content">
            <!-- Sección de Gestión de Barberías -->
            <section class="card">
                <div class="card-header">
                    <h2>Gestionar Barberías</h2>
                </div>
                <div class="card-body">
                    <div class="management-section">
                        <div class="form-container">
                            <h3>Crear Nueva Barbería</h3>
                            <form action="superadmin_dashboard.php" method="POST">
                                <div class="form-group">
                                    <label for="nombre_barberia">Nombre de la Barbería</label>
                                    <input type="text" id="nombre_barberia" name="nombre_barberia" required>
                                </div>
                                <button type="submit" name="crear_barberia" class="btn btn-primary">Crear Barbería</button>
                            </form>
                        </div>
                        <div class="table-container">
                            <h3>Lista de Barberías</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th>Fecha Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($barberia = $barberias_query->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($barberia['nombre']); ?></td>
                                        <td>
                                            <?php if ($barberia['activa']): ?>
                                                <span class="status status-active">Activa</span>
                                            <?php else: ?>
                                                <span class="status status-inactive">Inactiva</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($barberia['fecha_creacion'])); ?></td>
                                        <td>
                                            <a href="?action=toggle_status&id=<?php echo $barberia['id']; ?>" class="btn btn-secondary">
                                                <?php echo $barberia['activa'] ? 'Deshabilitar' : 'Habilitar'; ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Sección de Gestión de Administradores -->
            <section class="card">
                <div class="card-header">
                    <h2>Gestionar Administradores</h2>
                </div>
                <div class="card-body">
                     <div class="management-section">
                        <div class="form-container">
                            <h3>Crear Nuevo Administrador</h3>
                            <form action="superadmin_dashboard.php" method="POST">
                                <div class="form-group">
                                    <label for="nombre_admin">Nombre Completo</label>
                                    <input type="text" id="nombre_admin" name="nombre_admin" required>
                                </div>
                                <div class="form-group">
                                    <label for="email_admin">Correo Electrónico</label>
                                    <input type="email" id="email_admin" name="email_admin" required>
                                </div>
                                <div class="form-group">
                                    <label for="password_admin">Contraseña</label>
                                    <input type="password" id="password_admin" name="password_admin" required>
                                </div>
                                <div class="form-group">
                                    <label for="barberia_id_admin">Asignar a Barbería</label>
                                    <select id="barberia_id_admin" name="barberia_id_admin" required>
                                        <option value="">Seleccione una barbería...</option>
                                        <?php
                                        // Resetear el puntero para volver a usar la query
                                        $barberias_query->data_seek(0);
                                        while ($barberia = $barberias_query->fetch_assoc()):
                                            if ($barberia['activa']): // Solo mostrar barberías activas
                                        ?>
                                        <option value="<?php echo $barberia['id']; ?>"><?php echo htmlspecialchars($barberia['nombre']); ?></option>
                                        <?php
                                            endif;
                                        endwhile;
                                        ?>
                                    </select>
                                </div>
                                <button type="submit" name="crear_admin" class="btn btn-primary">Crear Administrador</button>
                            </form>
                        </div>
                        <div class="table-container">
                            <h3>Lista de Administradores</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Barbería Asignada</th>
                                        <th>Fecha Creación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($admin = $admins_query->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($admin['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['nombre_barberia']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($admin['fecha_creacion'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
<?php
$mysqli->close();
?>