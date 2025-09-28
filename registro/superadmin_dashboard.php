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
                    $_SESSION['mensaje'] = "Barbería creada con éxito.";
                } else {
                    $_SESSION['error'] = "Error al crear la barbería: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $_SESSION['error'] = "El nombre de la barbería no puede estar vacío.";
        }
        header("Location: superadmin_dashboard.php#barberias");
        exit();
    }

    // Crear nuevo administrador
    if (isset($_POST['crear_admin'])) {
        $nombre_admin = $mysqli->real_escape_string($_POST['nombre_admin']);
        $email_admin = $mysqli->real_escape_string($_POST['email_admin']);
        $password_admin = $_POST['password_admin'];
        $barberia_id_admin = (int)$_POST['barberia_id_admin'];

        if (!empty($nombre_admin) && !empty($email_admin) && !empty($password_admin) && $barberia_id_admin > 0) {
            $hashed_password = password_hash($password_admin, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (nombre, email, password, rol, barberia_id) VALUES (?, ?, ?, 'admin', ?)";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("sssi", $nombre_admin, $email_admin, $hashed_password, $barberia_id_admin);
                if ($stmt->execute()) {
                    $_SESSION['mensaje'] = "Administrador creado con éxito.";
                } else {
                    if ($stmt->errno == 1062) {
                        $_SESSION['error'] = "Ya existe un usuario con ese correo electrónico.";
                    } else {
                        $_SESSION['error'] = "Error al crear el administrador: " . $stmt->error;
                    }
                }
                $stmt->close();
            }
        } else {
            $_SESSION['error'] = "Todos los campos para crear un administrador son obligatorios.";
        }
        header("Location: superadmin_dashboard.php#admins");
        exit();
    }
}

// 3. Procesar acciones GET (Habilitar/Deshabilitar barbería)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $barberia_id = (int)$_GET['id'];
    if ($_GET['action'] === 'toggle_status') {
        $sql = "UPDATE barberias SET activa = NOT activa WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $barberia_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: superadmin_dashboard.php#barberias");
        exit();
    }
}

// Mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// 4. Obtener datos para mostrar en la página
$barberias_query = $mysqli->query("SELECT id, nombre, activa, fecha_creacion FROM barberias ORDER BY nombre");
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
    <title>Super Admin Dashboard | BarberShop Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo-section">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div>
                    <h1>Panel de Superadministrador</h1>
                    <p class="header-subtitle">Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></strong></p>
                </div>
            </div>
            <a href="logout.php" class="btn-primary" style="background: #c82333; border-color: #c82333;">Cerrar Sesión</a>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <a href="#barberias" class="nav-link" data-section="barberias">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line>
                </svg>
                <span class="nav-text">Gestionar Barberías</span>
            </a>
            <a href="#admins" class="nav-link" data-section="admins">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span class="nav-text">Gestionar Admins</span>
            </a>
        </nav>
    </aside>

    <div class="container" id="main-container">
        <?php if ($mensaje): ?>
            <div class="alert success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <section class="card" id="barberias-section">
            <div class="card-header"><h2>Gestionar Barberías</h2></div>
            <div style="padding: 2rem;">
                <h3 class="section-subtitle">Crear Nueva Barbería</h3>
                <form action="superadmin_dashboard.php" method="POST">
                    <div class="form-group">
                        <label for="nombre_barberia">Nombre de la Barbería</label>
                        <input type="text" id="nombre_barberia" name="nombre_barberia" required placeholder="Ej: Barbería Central">
                    </div>
                    <button type="submit" name="crear_barberia" class="btn-primary">Crear Barbería</button>
                </form>
            </div>
            <div style="padding: 0 2rem 2rem;">
                <h3 class="section-subtitle">Lista de Barberías</h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Nombre</th><th>Estado</th><th>Fecha Creación</th><th>Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($barberias_query->num_rows > 0): ?>
                                <?php $barberias_query->data_seek(0); while ($barberia = $barberias_query->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($barberia['nombre']); ?></td>
                                    <td>
                                        <span style="font-weight:bold; color: <?php echo $barberia['activa'] ? '#28a745' : '#dc3545'; ?>;">
                                            <?php echo $barberia['activa'] ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($barberia['fecha_creacion'])); ?></td>
                                    <td class="actions-cell">
                                        <a href="?action=toggle_status&id=<?php echo $barberia['id']; ?>" class="btn-secondary" style="text-decoration:none;"><?php echo $barberia['activa'] ? 'Deshabilitar' : 'Habilitar'; ?></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="empty-state">No hay barberías registradas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card" id="admins-section">
            <div class="card-header"><h2>Gestionar Administradores</h2></div>
            <div style="padding: 2rem;">
                <h3 class="section-subtitle">Crear Nuevo Administrador</h3>
                <form action="superadmin_dashboard.php" method="POST">
                    <div class="form-grid">
                        <div class="form-group"><label for="nombre_admin">Nombre Completo</label><input type="text" id="nombre_admin" name="nombre_admin" required></div>
                        <div class="form-group"><label for="email_admin">Correo Electrónico</label><input type="email" id="email_admin" name="email_admin" required></div>
                        <div class="form-group"><label for="password_admin">Contraseña</label><input type="password" id="password_admin" name="password_admin" required></div>
                        <div class="form-group">
                            <label for="barberia_id_admin">Asignar a Barbería</label>
                            <select id="barberia_id_admin" name="barberia_id_admin" required>
                                <option value="">Seleccione una barbería...</option>
                                <?php $barberias_query->data_seek(0); while ($barberia = $barberias_query->fetch_assoc()): if ($barberia['activa']): ?>
                                <option value="<?php echo $barberia['id']; ?>"><?php echo htmlspecialchars($barberia['nombre']); ?></option>
                                <?php endif; endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="crear_admin" class="btn-primary">Crear Administrador</button>
                </form>
            </div>
             <div style="padding: 0 2rem 2rem;">
                <h3 class="section-subtitle">Lista de Administradores</h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Nombre</th><th>Email</th><th>Barbería Asignada</th><th>Fecha Creación</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($admins_query->num_rows > 0): ?>
                                <?php while ($admin = $admins_query->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($admin['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['nombre_barberia']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($admin['fecha_creacion'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="empty-state">No hay administradores registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
        const sections = document.querySelectorAll('section.card');

        function showSection(sectionId) {
            sections.forEach(s => s.style.display = 'none');
            navLinks.forEach(l => l.classList.remove('active'));

            const sectionToShow = document.getElementById(sectionId);
            if (sectionToShow) {
                sectionToShow.style.display = 'block';
                const correspondingLink = document.querySelector(`.nav-link[data-section="${sectionId.replace('-section','')}"]`);
                if(correspondingLink) correspondingLink.classList.add('active');
            }
        }

        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sectionId = this.dataset.section + '-section';
                history.pushState(null, '', '#' + this.dataset.section);
                showSection(sectionId);
            });
        });

        function handleHashChange() {
            const hash = window.location.hash.substring(1);
            const sectionId = (hash || 'barberias') + '-section';
            showSection(sectionId);
        }

        window.addEventListener('hashchange', handleHashChange);
        handleHashChange(); // Initial load
    });
    </script>
</body>
</html>
<?php
$mysqli->close();
?>