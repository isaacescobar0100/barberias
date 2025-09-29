<?php
// 1. INICIAR SESIÓN Y PROTEGER LA PÁGINA
session_start();
// La configuración de la BD ahora está en la raíz del proyecto.
// Usamos __DIR__ para una ruta más robusta.
require_once __DIR__ . '/../db_config.php';

// Verificar si el usuario está logueado y es un administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    // Si no, redirigir a la página de login que está en la carpeta raíz
    header("Location: ../login.php");
    exit();
}

// Obtener el ID de la barbería del administrador logueado
$barberia_id = $_SESSION['user_barberia_id'];

// Si un admin no tiene barbería asignada, no puede hacer nada.
if (empty($barberia_id)) {
    die("Error: No tienes una barbería asignada. Contacta al superadministrador.");
}

// 2. PROCESAR ACCIONES POST (AHORA CON FILTRO DE barberia_id)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Registrar nuevo corte
    if (isset($_POST['registrar_corte'])) {
        $barbero_id = $mysqli->real_escape_string($_POST['barbero_id']);
        $servicio_id = $mysqli->real_escape_string($_POST['servicio_id']);
        $costo = $mysqli->real_escape_string($_POST['costo']);
        $propina = $mysqli->real_escape_string($_POST['propina'] ?: 0);

        $sql_insert = "INSERT INTO servicios (barberia_id, barbero_id, tipo_servicio_id, costo_total, propina) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = $mysqli->prepare($sql_insert)) {
            $stmt->bind_param("iiidd", $barberia_id, $barbero_id, $servicio_id, $costo, $propina);
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = '<div class="alert success">¡Servicio registrado con éxito!</div>';
            } else {
                $_SESSION['mensaje'] = '<div class="alert error">Error al registrar: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Agregar barbero
    if (isset($_POST['agregar_barbero'])) {
        $nombre = $mysqli->real_escape_string($_POST['nombre_barbero']);
        $telefono = $mysqli->real_escape_string($_POST['telefono_barbero'] ?: null);

        $sql_barbero = "INSERT INTO barberos (barberia_id, nombre, telefono) VALUES (?, ?, ?)";
        if ($stmt = $mysqli->prepare($sql_barbero)) {
            $stmt->bind_param("iss", $barberia_id, $nombre, $telefono);
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = '<div class="alert success">¡Barbero agregado exitosamente!</div>';
            } else {
                $_SESSION['mensaje'] = '<div class="alert error">Error al agregar barbero: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?view=barberos");
        exit();
    }

    // Agregar tipo de servicio
    if (isset($_POST['agregar_tipo_servicio'])) {
        $nombre_servicio = $mysqli->real_escape_string($_POST['nombre_servicio']);
        $precio_base = $mysqli->real_escape_string($_POST['precio_base']);

        $sql_tipo_servicio = "INSERT INTO tipos_servicio (barberia_id, nombre_servicio, precio_base) VALUES (?, ?, ?)";
        if ($stmt = $mysqli->prepare($sql_tipo_servicio)) {
            $stmt->bind_param("isd", $barberia_id, $nombre_servicio, $precio_base);
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = '<div class="alert success">¡Tipo de servicio agregado exitosamente!</div>';
            } else {
                $_SESSION['mensaje'] = '<div class="alert error">Error al agregar el tipo de servicio: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?view=tipos_servicio");
        exit();
    }
}

// 3. PROCESAR ACCIONES AJAX (AHORA CON FILTRO DE barberia_id)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // Eliminar servicio
    if ($_GET['action'] == 'delete' && $id > 0) {
        $sql = "DELETE FROM servicios WHERE id = ? AND barberia_id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ii", $id, $barberia_id);
            $success = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit();
    }

    // Actualizar servicio
    if ($_GET['action'] == 'update' && $id > 0) {
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = "UPDATE servicios SET barbero_id = ?, tipo_servicio_id = ?, costo_total = ?, propina = ? WHERE id = ? AND barberia_id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("iiddii", $data['barbero_id'], $data['servicio_id'], $data['costo'], $data['propina'], $id, $barberia_id);
            $success = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => $success]);
        }
        exit();
    }

    // Eliminar barbero
    if ($_GET['action'] == 'delete_barbero' && $id > 0) {
        $check_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM servicios WHERE barbero_id = ? AND barberia_id = ?");
        $check_stmt->bind_param("ii", $id, $barberia_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar un barbero con servicios registrados']);
        } else {
            $sql = "DELETE FROM barberos WHERE id = ? AND barberia_id = ?";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("ii", $id, $barberia_id);
                $success = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $success]);
            }
        }
        exit();
    }

    // Actualizar barbero
    if ($_GET['action'] == 'update_barbero' && $id > 0) {
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = "UPDATE barberos SET nombre = ?, telefono = ? WHERE id = ? AND barberia_id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ssii", $data['nombre'], $data['telefono'], $id, $barberia_id);
            $success = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => $success]);
        }
        exit();
    }

    // Actualizar Tipo de Servicio
    if ($_GET['action'] == 'update_tipo_servicio' && $id > 0) {
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = "UPDATE tipos_servicio SET nombre_servicio = ?, precio_base = ? WHERE id = ? AND barberia_id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("sdii", $data['nombre'], $data['precio'], $id, $barberia_id);
            $success = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => $success]);
        }
        exit();
    }

    // Eliminar Tipo de Servicio
    if ($_GET['action'] == 'delete_tipo_servicio' && $id > 0) {
        $check_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM servicios WHERE tipo_servicio_id = ? AND barberia_id = ?");
        $check_stmt->bind_param("ii", $id, $barberia_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Este tipo de servicio ya ha sido usado y no puede ser eliminado.']);
        } else {
            $sql = "DELETE FROM tipos_servicio WHERE id = ? AND barberia_id = ?";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("ii", $id, $barberia_id);
                $success = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $success]);
            }
        }
        exit();
    }
}


// 4. GENERAR REPORTE EXCEL (con filtro de barberia_id)
if (isset($_GET['export'])) {
    $periodo = $_GET['export'];
    $fecha_inicio = '';
    $fecha_fin = date('Y-m-d 23:59:59');
    $nombre_archivo = '';

    switch($periodo) {
        case 'dia': $fecha_inicio = date('Y-m-d 00:00:00'); $nombre_archivo = 'reporte_dia_' . date('Y-m-d'); break;
        case 'semana': $fecha_inicio = date('Y-m-d 00:00:00', strtotime('-7 days')); $nombre_archivo = 'reporte_semana_' . date('Y-m-d'); break;
        case 'mes': $fecha_inicio = date('Y-m-01 00:00:00'); $nombre_archivo = 'reporte_mes_' . date('Y-m'); break;
        case 'ano': $fecha_inicio = date('Y-01-01 00:00:00'); $nombre_archivo = 'reporte_ano_' . date('Y'); break;
    }

    $sql = "SELECT s.fecha_registro, b.nombre AS barbero, ts.nombre_servicio AS servicio, s.costo_total, s.propina, (s.costo_total + s.propina) AS total
            FROM servicios s JOIN barberos b ON s.barbero_id = b.id JOIN tipos_servicio ts ON s.tipo_servicio_id = ts.id
            WHERE s.barberia_id = ? AND s.fecha_registro BETWEEN ? AND ?
            ORDER BY s.fecha_registro DESC";

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $nombre_archivo . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body><table border="1">';
    echo '<tr style="background-color:#005A9C; color:white; font-weight:bold;"><th>Fecha</th><th>Hora</th><th>Barbero</th><th>Servicio</th><th>Costo (COP)</th><th>Propina (COP)</th><th>Total (COP)</th></tr>';

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("iss", $barberia_id, $fecha_inicio, $fecha_fin);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_general = 0;
        while ($row = $result->fetch_assoc()) {
            $fecha = new DateTime($row['fecha_registro']);
            echo '<tr>';
            echo '<td>' . $fecha->format('d/m/Y') . '</td>';
            echo '<td>' . $fecha->format('H:i') . '</td>';
            echo '<td>' . htmlspecialchars($row['barbero']) . '</td>';
            echo '<td>' . htmlspecialchars($row['servicio']) . '</td>';
            echo '<td>$' . number_format($row['costo_total'], 0, ',', '.') . '</td>';
            echo '<td>$' . number_format($row['propina'], 0, ',', '.') . '</td>';
            echo '<td>$' . number_format($row['total'], 0, ',', '.') . '</td>';
            echo '</tr>';
            $total_general += $row['total'];
        }
        echo '<tr style="background-color:#007BFF; color:white; font-weight:bold;"><td colspan="6">TOTAL GENERAL</td><td>$' . number_format($total_general, 0, ',', '.') . '</td></tr>';
        $stmt->close();
    }
    echo '</table></body></html>';
    exit();
}


// 5. OBTENER DATOS PARA LA VISTA (con filtro de barberia_id)
$barberos_stmt = $mysqli->prepare("SELECT id, nombre FROM barberos WHERE barberia_id = ? ORDER BY nombre");
$barberos_stmt->bind_param("i", $barberia_id);
$barberos_stmt->execute();
$barberos_query = $barberos_stmt->get_result();

$servicios_stmt = $mysqli->prepare("SELECT id, nombre_servicio, precio_base FROM tipos_servicio WHERE barberia_id = ? ORDER BY nombre_servicio");
$servicios_stmt->bind_param("i", $barberia_id);
$servicios_stmt->execute();
$servicios_query = $servicios_stmt->get_result();

// Filtro de fecha para el historial
$filtro_fecha = isset($_GET['filtro']) ? $_GET['filtro'] : 'hoy';
$where_fecha = "AND DATE(s.fecha_registro) = CURDATE()";
switch($filtro_fecha) {
    case 'semana': $where_fecha = "AND s.fecha_registro >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
    case 'mes': $where_fecha = "AND MONTH(s.fecha_registro) = MONTH(CURRENT_DATE()) AND YEAR(s.fecha_registro) = YEAR(CURRENT_DATE())"; break;
    case 'todos': $where_fecha = ""; break;
}

$historial_sql = "SELECT s.id, s.costo_total, s.propina, s.fecha_registro, s.barbero_id, s.tipo_servicio_id, b.nombre AS nombre_barbero, ts.nombre_servicio
    FROM servicios s JOIN barberos b ON s.barbero_id = b.id JOIN tipos_servicio ts ON s.tipo_servicio_id = ts.id
    WHERE s.barberia_id = ? $where_fecha ORDER BY s.fecha_registro DESC LIMIT 100";
$historial_stmt = $mysqli->prepare($historial_sql);
$historial_stmt->bind_param("i", $barberia_id);
$historial_stmt->execute();
$historial_query = $historial_stmt->get_result();

$stats_sql = "SELECT COUNT(*) as total_servicios, SUM(costo_total + propina) as total_ganado, AVG(costo_total) as promedio_servicio, SUM(propina) as total_propinas
    FROM servicios WHERE DATE(fecha_registro) = CURDATE() AND barberia_id = ?";
$stats_stmt = $mysqli->prepare($stats_sql);
$stats_stmt->bind_param("i", $barberia_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$stats_barbero_sql = "SELECT b.nombre, COUNT(s.id) as servicios, SUM(s.costo_total + s.propina) as total
    FROM servicios s JOIN barberos b ON s.barbero_id = b.id
    WHERE DATE(s.fecha_registro) = CURDATE() AND s.barberia_id = ? GROUP BY b.id ORDER BY total DESC";
$stats_barbero_stmt = $mysqli->prepare($stats_barbero_sql);
$stats_barbero_stmt->bind_param("i", $barberia_id);
$stats_barbero_stmt->execute();
$stats_barbero_query = $stats_barbero_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | BarberShop Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo-section">
                <button class="hamburger-btn" id="hamburgerBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8" cy="7" r="4"/></svg>
                </div>
                <div>
                    <h1>Panel de Barbería</h1>
                    <p class="header-subtitle">Bienvenido, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?></p>
                </div>
            </div>
            <div class="user-info-header">
                <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <a href="#" class="nav-link active" data-section="dashboard"><span>Dashboard</span></a>
            <a href="#" class="nav-link" data-section="registro"><span>Registrar Servicio</span></a>
            <a href="#" class="nav-link" data-section="barberos"><span>Barberos</span></a>
            <a href="#" class="nav-link" data-section="tipos_servicio"><span>Tipos de Servicio</span></a>
            <a href="#" class="nav-link" data-section="historial"><span>Historial</span></a>
            <a href="#" class="nav-link" data-section="reportes"><span>Reportes</span></a>
        </nav>
    </aside>

    <div class="container" id="main-container">
        <?php if (isset($_SESSION['mensaje'])) { echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); } ?>

        <section id="dashboard-view">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Ganancia Hoy</h3>
                <p class="stat-number">$<?= number_format($stats['total_ganado'] ?? 0, 0, ',', '.') ?></p>
            </div>
            <div class="stat-card">
                <h3>Servicios Hoy</h3>
                <p class="stat-number"><?= $stats['total_servicios'] ?? 0 ?></p>
            </div>
            <div class="stat-card">
                <h3>Promedio Servicio</h3>
                <p class="stat-number">$<?= number_format($stats['promedio_servicio'] ?? 0, 0, ',', '.') ?></p>
            </div>
            <div class="stat-card">
                <h3>Propinas Hoy</h3>
                <p class="stat-number">$<?= number_format($stats['total_propinas'] ?? 0, 0, ',', '.') ?></p>
            </div>
        </div>

        <section class="card" id="rendimiento-section">
            <div class="card-header">
                <h2>Rendimiento por Barbero (Hoy)</h2>
            </div>
            <div class="barberos-stats">
                <?php while($barbero_stat = $stats_barbero_query->fetch_assoc()): ?>
                <div class="barbero-stat-item">
                    <div class="barbero-name"><?= htmlspecialchars($barbero_stat['nombre']) ?></div>
                    <div class="barbero-services"><?= $barbero_stat['servicios'] ?> servicios</div>
                    <div class="barbero-total">$<?= number_format($barbero_stat['total'], 0, ',', '.') ?></div>
                </div>
                <?php endwhile; ?>
            </div>
        </section>
        </section> <!-- Cierre de dashboard-view -->

        <section class="card" id="barberos-section">
            <div class="card-header">
                <h2>Gestión de Barberos</h2>
            </div>
            <div style="padding: 2rem;">
                <h3>Agregar Nuevo Barbero</h3>
                <form action="index.php" method="POST" id="barberoForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre del Barbero</label>
                            <input type="text" name="nombre_barbero" required>
                        </div>
                        <div class="form-group">
                            <label>Teléfono (Opcional)</label>
                            <input type="tel" name="telefono_barbero">
                        </div>
                    </div>
                    <button type="submit" name="agregar_barbero" class="btn-primary">Agregar Barbero</button>
                </form>
            </div>
            <div style="padding: 0 2rem 2rem;">
                <h3>Barberos Registrados</h3>
                <div class="barberos-list">
                    <?php
                    $barberos_lista_stmt = $mysqli->prepare("SELECT id, nombre, telefono FROM barberos WHERE barberia_id = ? ORDER BY nombre");
                    $barberos_lista_stmt->bind_param("i", $barberia_id);
                    $barberos_lista_stmt->execute();
                    $barberos_lista = $barberos_lista_stmt->get_result();
                    while($barbero = $barberos_lista->fetch_assoc()):
                    ?>
                        <div class="barbero-card" data-id="<?= $barbero['id'] ?>">
                            <div class="barbero-info">
                                <h4><?= htmlspecialchars($barbero['nombre']) ?></h4>
                                <?php if($barbero['telefono']): ?>
                                    <p>
                                        <svg class="phone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                        </svg>
                                        <?= htmlspecialchars($barbero['telefono']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="barbero-actions">
                                <button onclick="editarBarbero(<?= $barbero['id'] ?>, '<?= htmlspecialchars($barbero['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($barbero['telefono'] ?? '', ENT_QUOTES) ?>')" class="btn-action btn-edit">Editar</button>
                                <button onclick="eliminarBarbero(<?= $barbero['id'] ?>)" class="btn-action btn-delete">Eliminar</button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>

        <section class="card" id="tipos_servicio-section">
            <div class="card-header"><h2>Gestión de Tipos de Servicio</h2></div>
            <div style="padding: 2rem;">
                <h3>Agregar Nuevo Tipo de Servicio</h3>
                <form action="index.php" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre_servicio">Nombre del Servicio</label>
                            <input type="text" id="nombre_servicio" name="nombre_servicio" required placeholder="Ej: Corte Premium">
                        </div>
                        <div class="form-group">
                            <label for="precio_base">Precio Base (COP)</label>
                            <input type="number" id="precio_base" name="precio_base" step="1000" min="0" required placeholder="Ej: 25000">
                        </div>
                    </div>
                    <button type="submit" name="agregar_tipo_servicio" class="btn-primary">Agregar Tipo de Servicio</button>
                </form>
            </div>
            <div style="padding: 0 2rem 2rem;">
                <h3>Tipos de Servicio Registrados</h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre del Servicio</th>
                                <th>Precio Base</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $tipos_servicio_query = $mysqli->prepare("SELECT id, nombre_servicio, precio_base FROM tipos_servicio WHERE barberia_id = ? ORDER BY nombre_servicio");
                            $tipos_servicio_query->bind_param("i", $barberia_id);
                            $tipos_servicio_query->execute();
                            $tipos_servicio_result = $tipos_servicio_query->get_result();
                            while ($tipo_servicio = $tipos_servicio_result->fetch_assoc()):
                            ?>
                            <tr data-id="<?= $tipo_servicio['id'] ?>">
                                <td><?= htmlspecialchars($tipo_servicio['nombre_servicio']) ?></td>
                                <td>$<?= number_format($tipo_servicio['precio_base'], 0, ',', '.') ?></td>
                                <td class="actions-cell">
                                    <button onclick="editarTipoServicio(<?= $tipo_servicio['id'] ?>, '<?= htmlspecialchars($tipo_servicio['nombre_servicio'], ENT_QUOTES) ?>', '<?= $tipo_servicio['precio_base'] ?>')" class="btn-action btn-edit">Editar</button>
                                    <button onclick="eliminarTipoServicio(<?= $tipo_servicio['id'] ?>)" class="btn-action btn-delete">Eliminar</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card" id="registro-section">
            <div class="card-header">
                <h2>Registrar Nuevo Servicio</h2>
            </div>
            <form action="index.php" method="POST" id="registroForm" style="padding: 2rem;">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Barbero</label>
                        <select name="barbero_id" required>
                            <option value="">Seleccione...</option>
                            <?php $barberos_query->data_seek(0); while ($barbero = $barberos_query->fetch_assoc()): ?>
                                <option value="<?= $barbero['id']; ?>"><?= htmlspecialchars($barbero['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Servicio</label>
                        <select id="servicio_id" name="servicio_id" required>
                            <option value="">Seleccione...</option>
                            <?php $servicios_query->data_seek(0); while ($servicio = $servicios_query->fetch_assoc()): ?>
                                <option value="<?= $servicio['id']; ?>" data-precio="<?= $servicio['precio_base']; ?>">
                                    <?= htmlspecialchars($servicio['nombre_servicio']) . ' - $' . number_format($servicio['precio_base'], 0, ',', '.'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Costo Total (COP)</label>
                        <input type="number" id="costo" name="costo" step="1000" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Propina (COP)</label>
                        <input type="number" name="propina" step="1000" min="0" value="0">
                    </div>
                </div>
                <button type="submit" name="registrar_corte" class="btn-primary">Registrar Servicio</button>
            </form>
        </section>

        <section class="card" id="reportes-section">
            <div class="card-header">
                <h2>Descargar Reportes</h2>
            </div>
            <div class="reportes-grid">
                <a href="?export=dia" class="reporte-btn"><span>Reporte del Día</span></a>
                <a href="?export=semana" class="reporte-btn"><span>Reporte Semanal</span></a>
                <a href="?export=mes" class="reporte-btn"><span>Reporte Mensual</span></a>
                <a href="?export=ano" class="reporte-btn"><span>Reporte Anual</span></a>
            </div>
        </section>

        <section class="card" id="historial-section">
            <div class="card-header">
                <h2>Historial de Servicios</h2>
                <div class="filter-buttons">
                    <a href="?filtro=hoy" class="filter-btn <?= $filtro_fecha == 'hoy' ? 'active' : '' ?>">Hoy</a>
                    <a href="?filtro=semana" class="filter-btn <?= $filtro_fecha == 'semana' ? 'active' : '' ?>">Semana</a>
                    <a href="?filtro=mes" class="filter-btn <?= $filtro_fecha == 'mes' ? 'active' : '' ?>">Mes</a>
                    <a href="?filtro=todos" class="filter-btn <?= $filtro_fecha == 'todos' ? 'active' : '' ?>">Todos</a>
                </div>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Barbero</th>
                            <th>Servicio</th>
                            <th>Costo</th>
                            <th>Propina</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($registro = $historial_query->fetch_assoc()): ?>
                            <tr data-id="<?= $registro['id'] ?>">
                                <td data-label="Fecha"><?= date('d/m/Y H:i', strtotime($registro['fecha_registro'])) ?></td>
                                <td data-label="Barbero" data-barbero-id="<?= $registro['barbero_id'] ?>"><?= htmlspecialchars($registro['nombre_barbero']) ?></td>
                                <td data-label="Servicio" data-servicio-id="<?= $registro['tipo_servicio_id'] ?>"><?= htmlspecialchars($registro['nombre_servicio']) ?></td>
                                <td data-label="Costo" data-costo="<?= $registro['costo_total'] ?>">$<?= number_format($registro['costo_total'], 0, ',', '.') ?></td>
                                <td data-label="Propina" data-propina="<?= $registro['propina'] ?>">$<?= number_format($registro['propina'], 0, ',', '.') ?></td>
                                <td data-label="Total">$<?= number_format($registro['costo_total'] + $registro['propina'], 0, ',', '.') ?></td>
                                <td data-label="Acciones" class="actions-cell">
                                    <button onclick="editarServicio(<?= $registro['id'] ?>)" class="btn-action btn-edit">Editar</button>
                                    <button onclick="eliminarServicio(<?= $registro['id'] ?>)" class="btn-action btn-delete">Eliminar</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Modals -->
    <div id="editModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Editar Servicio</h3><span class="close" onclick="cerrarModal('editModal')">&times;</span></div><div class="modal-body"><form id="editForm"><input type="hidden" id="edit_id"><div class="form-group"><label>Barbero</label><select id="edit_barbero" required><?php $barberos_query->data_seek(0); while ($barbero = $barberos_query->fetch_assoc()): ?><option value="<?= $barbero['id']; ?>"><?= htmlspecialchars($barbero['nombre']); ?></option><?php endwhile; ?></select></div><div class="form-group"><label>Servicio</label><select id="edit_servicio" required><?php $servicios_query->data_seek(0); while ($servicio = $servicios_query->fetch_assoc()): ?><option value="<?= $servicio['id']; ?>"><?= htmlspecialchars($servicio['nombre_servicio']); ?></option><?php endwhile; ?></select></div><div class="form-group"><label>Costo</label><input type="number" id="edit_costo" required></div><div class="form-group"><label>Propina</label><input type="number" id="edit_propina"></div><button type="submit" class="btn-primary">Guardar</button></form></div></div></div>
    <div id="editBarberoModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Editar Barbero</h3><span class="close" onclick="cerrarModal('editBarberoModal')">&times;</span></div><div class="modal-body"><form id="editBarberoForm"><input type="hidden" id="edit_barbero_id"><div class="form-group"><label>Nombre</label><input type="text" id="edit_barbero_nombre" required></div><div class="form-group"><label>Teléfono</label><input type="tel" id="edit_barbero_telefono"></div><button type="submit" class="btn-primary">Guardar</button></form></div></div></div>
    <div id="editTipoServicioModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Editar Tipo de Servicio</h3><span class="close" onclick="cerrarModal('editTipoServicioModal')">&times;</span></div><div class="modal-body"><form id="editTipoServicioForm"><input type="hidden" id="edit_tipo_servicio_id"><div class="form-group"><label>Nombre del Servicio</label><input type="text" id="edit_tipo_servicio_nombre" required></div><div class="form-group"><label>Precio Base (COP)</label><input type="number" id="edit_tipo_servicio_precio" step="1000" min="0" required></div><button type="submit" class="btn-primary">Guardar Cambios</button></form></div></div></div>
    <div id="confirmModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Confirmar</h3><span class="close" onclick="hideConfirmModal()">&times;</span></div><div class="modal-body"><p id="confirmModalText"></p><div class="modal-actions"><button id="cancelBtn" class="btn-secondary">Cancelar</button><button id="confirmBtn" class="btn-primary">Confirmar</button></div></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');

    // Nueva estructura de secciones para el dashboard
    const sections = {
        dashboard: [document.getElementById('dashboard-view')],
        registro: [document.getElementById('registro-section')],
        barberos: [document.getElementById('barberos-section')],
        tipos_servicio: [document.getElementById('tipos_servicio-section')],
        historial: [document.getElementById('historial-section')],
        reportes: [document.getElementById('reportes-section')]
    };
    const allSections = Object.values(sections).flat();

    if (hamburgerBtn && sidebar) {
        hamburgerBtn.addEventListener('click', () => sidebar.classList.toggle('show'));
    }

    function showSection(sectionName) {
        navLinks.forEach(l => l.classList.toggle('active', l.dataset.section === sectionName));
        allSections.forEach(card => { if(card) card.style.display = 'none'; });
        if (sections[sectionName]) {
            sections[sectionName].forEach(s => { if(s) s.style.display = 'block'; });
        }
    }

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const sectionName = this.dataset.section;
            showSection(sectionName);
            if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    });

    // Vista por defecto es el dashboard
    const urlParams = new URLSearchParams(window.location.search);
    let sectionToLoad = 'dashboard';
    if (urlParams.get('view') === 'barberos') sectionToLoad = 'barberos';
    else if (urlParams.get('view') === 'tipos_servicio') sectionToLoad = 'tipos_servicio';
    else if (urlParams.get('filtro')) sectionToLoad = 'historial';
    showSection(sectionToLoad);

    document.getElementById('servicio_id').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        document.getElementById('costo').value = selected.dataset.precio || '';
    });

    // Lógica de los formularios de edición
    document.getElementById('editForm').addEventListener('submit', e => { e.preventDefault(); const id = document.getElementById('edit_id').value; const data = { barbero_id: document.getElementById('edit_barbero').value, servicio_id: document.getElementById('edit_servicio').value, costo: document.getElementById('edit_costo').value, propina: document.getElementById('edit_propina').value }; fetch(`index.php?action=update&id=${id}`, { method: 'POST', body: JSON.stringify(data) }).then(res => res.json()).then(d => d.success ? location.reload() : alert('Error')); });
    document.getElementById('editBarberoForm').addEventListener('submit', e => { e.preventDefault(); const id = document.getElementById('edit_barbero_id').value; const data = { nombre: document.getElementById('edit_barbero_nombre').value, telefono: document.getElementById('edit_barbero_telefono').value }; fetch(`index.php?action=update_barbero&id=${id}`, { method: 'POST', body: JSON.stringify(data) }).then(res => res.json()).then(d => d.success ? location.reload() : alert('Error')); });
    document.getElementById('editTipoServicioForm').addEventListener('submit', e => { e.preventDefault(); const id = document.getElementById('edit_tipo_servicio_id').value; const data = { nombre: document.getElementById('edit_tipo_servicio_nombre').value, precio: document.getElementById('edit_tipo_servicio_precio').value }; fetch(`index.php?action=update_tipo_servicio&id=${id}`, { method: 'POST', body: JSON.stringify(data) }).then(res => res.json()).then(d => d.success ? location.reload() : alert('Error al actualizar')); });
});

// Funciones globales para modales
let onConfirmCallback = null;
function showConfirmModal(text, callback) { document.getElementById('confirmModalText').textContent = text; onConfirmCallback = callback; document.getElementById('confirmModal').classList.add('show'); }
function hideConfirmModal() { document.getElementById('confirmModal').classList.remove('show'); }
document.getElementById('confirmBtn').addEventListener('click', () => { if(onConfirmCallback) onConfirmCallback(); hideConfirmModal(); });
document.getElementById('cancelBtn').addEventListener('click', hideConfirmModal);
function cerrarModal(modalId) { document.getElementById(modalId).classList.remove('show'); }
window.onclick = e => { if (e.target.classList.contains('modal')) e.target.classList.remove('show'); };
function eliminarServicio(id) { showConfirmModal('¿Seguro?', () => { fetch(`index.php?action=delete&id=${id}`).then(res => res.json()).then(d => d.success ? location.reload() : alert('Error')); }); }
function eliminarBarbero(id) { showConfirmModal('¿Seguro?', () => { fetch(`index.php?action=delete_barbero&id=${id}`).then(res => res.json()).then(d => d.success ? location.reload() : alert(d.message || 'Error')); }); }
function eliminarTipoServicio(id) { showConfirmModal('¿Seguro que quieres eliminar este tipo de servicio?', () => { fetch(`index.php?action=delete_tipo_servicio&id=${id}`).then(res => res.json()).then(d => d.success ? location.reload() : alert(d.message || 'Error al eliminar')); }); }
function editarServicio(id) { const r = document.querySelector(`tr[data-id="${id}"]`); document.getElementById('edit_id').value = id; document.getElementById('edit_barbero').value = r.querySelector('[data-barbero-id]').dataset.barberoId; document.getElementById('edit_servicio').value = r.querySelector('[data-servicio-id]').dataset.servicioId; document.getElementById('edit_costo').value = r.querySelector('[data-costo]').dataset.costo; document.getElementById('edit_propina').value = r.querySelector('[data-propina]').dataset.propina; document.getElementById('editModal').classList.add('show'); }
function editarBarbero(id, nombre, telefono) { document.getElementById('edit_barbero_id').value = id; document.getElementById('edit_barbero_nombre').value = nombre; document.getElementById('edit_barbero_telefono').value = telefono; document.getElementById('editBarberoModal').classList.add('show'); }
function editarTipoServicio(id, nombre, precio) { document.getElementById('edit_tipo_servicio_id').value = id; document.getElementById('edit_tipo_servicio_nombre').value = nombre; document.getElementById('edit_tipo_servicio_precio').value = precio; document.getElementById('editTipoServicioModal').classList.add('show'); }
</script>
</body>
</html>
<?php $mysqli->close(); ?>