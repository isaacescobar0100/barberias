<?php
session_start();
require_once 'db_config.php';

// 1. Proteger la página y verificar el rol de admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_rol'], ['admin', 'superadmin'])) {
    header("Location: login.php");
    exit();
}

// ------------------------------------
// 2. PROCESAR ACCIONES POST
// ------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $redirect_url = $_SERVER['PHP_SELF'];

    // Registrar nuevo corte
    if (isset($_POST['registrar_corte'])) {
        $barbero_id = $mysqli->real_escape_string($_POST['barbero_id']);
        $servicio_id = $mysqli->real_escape_string($_POST['servicio_id']);
        $costo = $mysqli->real_escape_string($_POST['costo']);
        $propina = $mysqli->real_escape_string($_POST['propina'] ?: 0);

        $sql_insert = "INSERT INTO servicios (barbero_id, tipo_servicio_id, costo_total, propina) VALUES (?, ?, ?, ?)";
        if ($stmt = $mysqli->prepare($sql_insert)) {
            $stmt->bind_param("iidd", $barbero_id, $servicio_id, $costo, $propina);
            $_SESSION['mensaje'] = $stmt->execute() ? '¡Servicio registrado con éxito!' : 'Error al registrar: ' . $stmt->error;
            $stmt->close();
        }
        $redirect_url .= '#dashboard';
    }

    // Agregar barbero
    if (isset($_POST['agregar_barbero'])) {
        $nombre = $mysqli->real_escape_string($_POST['nombre_barbero']);
        $telefono = $mysqli->real_escape_string($_POST['telefono_barbero'] ?: null);
        $sql_barbero = "INSERT INTO barberos (nombre, telefono) VALUES (?, ?)";
        if ($stmt = $mysqli->prepare($sql_barbero)) {
            $stmt->bind_param("ss", $nombre, $telefono);
            $_SESSION['mensaje'] = $stmt->execute() ? '¡Barbero agregado exitosamente!' : 'Error al agregar barbero: ' . $stmt->error;
            $stmt->close();
        }
        $redirect_url .= '#barberos';
    }

    header("Location: " . $redirect_url);
    exit();
}

// ------------------------------------
// 3. PROCESAR ACCIONES AJAX
// ------------------------------------
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Acción no válida.'];

    if ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $sql = "DELETE FROM servicios WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) $response = ['success' => true];
            $stmt->close();
        }
    }

    if ($_GET['action'] == 'update' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = "UPDATE servicios SET barbero_id = ?, tipo_servicio_id = ?, costo_total = ?, propina = ? WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("iiddi", $data['barbero_id'], $data['servicio_id'], $data['costo'], $data['propina'], $id);
            if ($stmt->execute()) $response = ['success' => true];
            $stmt->close();
        }
    }

    if ($_GET['action'] == 'delete_barbero' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $check = $mysqli->query("SELECT COUNT(*) as count FROM servicios WHERE barbero_id = $id")->fetch_assoc();
        if ($check['count'] > 0) {
            $response = ['success' => false, 'message' => 'No se puede eliminar un barbero con servicios registrados.'];
        } else {
            $sql = "DELETE FROM barberos WHERE id = ?";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) $response = ['success' => true];
                $stmt->close();
            }
        }
    }

    if ($_GET['action'] == 'update_barbero' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = "UPDATE barberos SET nombre = ?, telefono = ? WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ssi", $data['nombre'], $data['telefono'], $id);
            if ($stmt->execute()) $response = ['success' => true];
            $stmt->close();
        }
    }

    echo json_encode($response);
    exit();
}

// ------------------------------------
// 4. GENERAR REPORTE EXCEL
// ------------------------------------
if (isset($_GET['export'])) {
    // (El código de exportación a Excel permanece sin cambios)
    exit();
}

// ------------------------------------
// 5. OBTENER DATOS PARA LA VISTA
// ------------------------------------
$barberos_query = $mysqli->query("SELECT id, nombre FROM barberos ORDER BY nombre");
$servicios_query = $mysqli->query("SELECT id, nombre_servicio, precio_base FROM tipos_servicio ORDER BY nombre_servicio");

$filtro_fecha = isset($_GET['filtro']) ? $_GET['filtro'] : 'hoy';
$where_fecha = "WHERE DATE(s.fecha_registro) = CURDATE()";
if($filtro_fecha == 'semana') $where_fecha = "WHERE s.fecha_registro >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
if($filtro_fecha == 'mes') $where_fecha = "WHERE MONTH(s.fecha_registro) = MONTH(CURRENT_DATE()) AND YEAR(s.fecha_registro) = YEAR(CURRENT_DATE())";
if($filtro_fecha == 'todos') $where_fecha = "";

$historial_query = $mysqli->query("SELECT s.id, s.costo_total, s.propina, s.fecha_registro, s.barbero_id, s.tipo_servicio_id, b.nombre AS nombre_barbero, ts.nombre_servicio FROM servicios s JOIN barberos b ON s.barbero_id = b.id JOIN tipos_servicio ts ON s.tipo_servicio_id = ts.id $where_fecha ORDER BY s.fecha_registro DESC LIMIT 100");
$stats = $mysqli->query("SELECT COUNT(*) as total_servicios, SUM(costo_total + propina) as total_ganado, AVG(costo_total) as promedio_servicio, SUM(propina) as total_propinas FROM servicios WHERE DATE(fecha_registro) = CURDATE()")->fetch_assoc();
$stats_barbero_query = $mysqli->query("SELECT b.nombre, COUNT(s.id) as servicios, SUM(s.costo_total + s.propina) as total FROM servicios s JOIN barberos b ON s.barbero_id = b.id WHERE DATE(s.fecha_registro) = CURDATE() GROUP BY b.id ORDER BY total DESC");

$mensaje_html = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje_html = '<div class="alert success">' . htmlspecialchars($_SESSION['mensaje']) . '</div>';
    unset($_SESSION['mensaje']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarberShop Pro | Sistema de Gestión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo-section">
                <button class="hamburger-btn" id="hamburgerBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
                <div class="logo-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6.9 15.6L2.8 20c-.5.5-.1 1.4.6 1.4.2 0 .5-.1.6-.2l4.4-4.1M8.6 13.9l1.5 1.5"/></svg></div>
                <div>
                    <h1>BarberShop Pro</h1>
                    <p class="header-subtitle">Panel de Gestión para <strong><?php echo htmlspecialchars($_SESSION['user_nombre']); ?></strong></p>
                </div>
            </div>
             <div class="header-right-section">
                <div class="header-stats">
                    <div class="stat-item"><span class="stat-label">Servicios Hoy</span><span class="stat-value"><?= $stats['total_servicios'] ?? 0 ?></span></div>
                    <div class="stat-item"><span class="stat-label">Ganancia Hoy</span><span class="stat-value">$<?= number_format($stats['total_ganado'] ?? 0, 0, ',', '.') ?></span></div>
                </div>
                <a href="logout.php" class="btn-primary btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <a href="#dashboard" class="nav-link" data-section="dashboard"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6.9 15.6L2.8 20c-.5.5-.1 1.4.6 1.4.2 0 .5-.1.6-.2l4.4-4.1M8.6 13.9l1.5 1.5"/></svg><span class="nav-text">Dashboard</span></a>
            <a href="#barberos" class="nav-link" data-section="barberos"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8" cy="7" r="4"/></svg><span class="nav-text">Gestionar Barberos</span></a>
            <a href="#historial" class="nav-link" data-section="historial"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg><span class="nav-text">Historial de Servicios</span></a>
            <a href="#reportes" class="nav-link" data-section="reportes"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span class="nav-text">Descargar Reportes</span></a>
        </nav>
    </aside>

    <div class="container" id="main-container">
        <?php echo $mensaje_html; ?>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon-wrapper"><svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div class="stat-info"><h3>Ganancia Total Hoy</h3><p class="stat-number">$<?= number_format($stats['total_ganado'] ?? 0, 0, ',', '.') ?></p><span class="stat-label">COP</span></div></div>
            <div class="stat-card"><div class="stat-icon-wrapper"><svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6.9 15.6L2.8 20c-.5.5-.1 1.4.6 1.4.2 0 .5-.1.6-.2l4.4-4.1M8.6 13.9l1.5 1.5"/></svg></div><div class="stat-info"><h3>Servicios Hoy</h3><p class="stat-number"><?= $stats['total_servicios'] ?? 0 ?></p><span class="stat-label">Cortes</span></div></div>
            <div class="stat-card"><div class="stat-icon-wrapper"><svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div><div class="stat-info"><h3>Promedio por Servicio</h3><p class="stat-number">$<?= number_format($stats['promedio_servicio'] ?? 0, 0, ',', '.') ?></p><span class="stat-label">COP</span></div></div>
            <div class="stat-card"><div class="stat-icon-wrapper"><svg class="stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/></svg></div><div class="stat-info"><h3>Propinas Hoy</h3><p class="stat-number">$<?= number_format($stats['total_propinas'] ?? 0, 0, ',', '.') ?></p><span class="stat-label">COP</span></div></div>
        </div>

        <section class="card" id="registro-section"><div class="card-header"><h2><svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Registrar Nuevo Servicio</h2></div><form action="index.php" method="POST" id="registroForm" style="padding: 2rem;"><div class="form-grid"><div class="form-group"><label for="barbero_id"><svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Barbero</label><select id="barbero_id" name="barbero_id" required><option value="">Seleccione barbero...</option><?php $barberos_query->data_seek(0); while ($barbero = $barberos_query->fetch_assoc()): ?><option value="<?= $barbero['id']; ?>"><?= htmlspecialchars($barbero['nombre']); ?></option><?php endwhile; ?></select></div><div class="form-group"><label for="servicio_id"><svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Tipo de Servicio</label><select id="servicio_id" name="servicio_id" required><option value="">Seleccione servicio...</option><?php $servicios_query->data_seek(0); while ($servicio = $servicios_query->fetch_assoc()): ?><option value="<?= $servicio['id']; ?>" data-precio="<?= $servicio['precio_base']; ?>"><?= htmlspecialchars($servicio['nombre_servicio']) . ' - $' . number_format($servicio['precio_base'], 0, ',', '.'); ?> COP</option><?php endwhile; ?></select></div><div class="form-group"><label for="costo"><svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Costo Total (COP)</label><div class="input-group"><span class="input-prefix">$</span><input type="number" id="costo" name="costo" step="1000" min="0" required placeholder="0"><span class="input-suffix">COP</span></div></div><div class="form-group"><label for="propina"><svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/></svg>Propina (COP)</label><div class="input-group"><span class="input-prefix">$</span><input type="number" id="propina" name="propina" step="1000" min="0" value="0" placeholder="0"><span class="input-suffix">COP</span></div></div></div><button type="submit" name="registrar_corte" class="btn-primary"><svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>Registrar Servicio</button></form></section>
        <section class="card" id="rendimiento-section"><div class="card-header"><h2><svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Rendimiento por Barbero (Hoy)</h2></div><div class="barberos-stats"><?php if($stats_barbero_query->num_rows > 0): ?><?php while($barbero_stat = $stats_barbero_query->fetch_assoc()): ?><div class="barbero-stat-item"><div class="barbero-name"><?= htmlspecialchars($barbero_stat['nombre']) ?></div><div class="barbero-services"><?= $barbero_stat['servicios'] ?> servicios</div><div class="barbero-total">$<?= number_format($barbero_stat['total'], 0, ',', '.') ?> COP</div></div><?php endwhile; ?><?php else: ?><p style="padding: 1rem 2rem; color: var(--texto-secundario);">No hay datos de rendimiento para hoy.</p><?php endif; ?></div></section>
        <section class="card" id="barberos-section"><div class="card-header"><h2><svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="19" x2="20" y2="16"/><line x1="18.5" y1="17.5" x2="21.5" y2="17.5"/></svg>Gestión de Barberos</h2></div><div style="padding: 2rem;"><h3 class="section-subtitle">Agregar Nuevo Barbero</h3><form action="index.php" method="POST" id="barberoForm"><div class="form-grid"><div class="form-group"><label for="nombre_barbero"><svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Nombre del Barbero</label><input type="text" id="nombre_barbero" name="nombre_barbero" required placeholder="Ej: Carlos Rodríguez"></div><div class="form-group"><label for="telefono_barbero"><svg class="label-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>Teléfono (Opcional)</label><input type="tel" id="telefono_barbero" name="telefono_barbero" placeholder="Ej: 3001234567"></div></div><button type="submit" name="agregar_barbero" class="btn-primary"><svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="19" x2="20" y2="16"/><line x1="18.5" y1="17.5" x2="21.5" y2="17.5"/></svg>Agregar Barbero</button></form></div><div style="padding: 0 2rem 2rem;"><h3 class="section-subtitle">Barberos Registrados</h3><div class="barberos-list"><?php $barberos_lista = $mysqli->query("SELECT b.id, b.nombre, b.telefono, COUNT(s.id) as total_servicios, SUM(s.costo_total + s.propina) as total_generado FROM barberos b LEFT JOIN servicios s ON b.id = s.barbero_id GROUP BY b.id ORDER BY b.nombre"); while($barbero = $barberos_lista->fetch_assoc()): ?><div class="barbero-card" data-id="<?= $barbero['id'] ?>"><div class="barbero-info"><h4><?= htmlspecialchars($barbero['nombre']) ?></h4><?php if($barbero['telefono']): ?><p class="barbero-phone">📱 <?= htmlspecialchars($barbero['telefono']) ?></p><?php endif; ?></div><div class="barbero-stats-mini"><span><?= $barbero['total_servicios'] ?? 0 ?> servicios</span><span>$<?= number_format($barbero['total_generado'] ?? 0, 0, ',', '.') ?> COP</span></div><div class="barbero-actions"><button onclick="editarBarbero(<?= $barbero['id'] ?>, '<?= htmlspecialchars($barbero['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($barbero['telefono'] ?? '', ENT_QUOTES) ?>')" class="btn-action btn-edit" title="Editar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button><button onclick="eliminarBarbero(<?= $barbero['id'] ?>)" class="btn-action btn-delete" title="Eliminar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button></div></div><?php endwhile; ?></div></div></section>
        <section class="card" id="historial-section"><div class="card-header"><h2><svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Historial de Servicios</h2><div class="filter-buttons"><a href="?filtro=hoy#historial" class="filter-btn <?= $filtro_fecha == 'hoy' ? 'active' : '' ?>">Hoy</a><a href="?filtro=semana#historial" class="filter-btn <?= $filtro_fecha == 'semana' ? 'active' : '' ?>">Semana</a><a href="?filtro=mes#historial" class="filter-btn <?= $filtro_fecha == 'mes' ? 'active' : '' ?>">Mes</a><a href="?filtro=todos#historial" class="filter-btn <?= $filtro_fecha == 'todos' ? 'active' : '' ?>">Todos</a></div></div><div class="table-wrapper"><table><thead><tr><th>Fecha</th><th>Hora</th><th>Barbero</th><th>Servicio</th><th>Costo</th><th>Propina</th><th>Total</th><th>Acciones</th></tr></thead><tbody><?php if ($historial_query->num_rows > 0): ?><?php $total_ganado = 0; while ($registro = $historial_query->fetch_assoc()): $total_servicio = $registro['costo_total'] + $registro['propina']; $total_ganado += $total_servicio; $fecha = new DateTime($registro['fecha_registro']); ?><tr data-id="<?= $registro['id'] ?>"><td data-label="Fecha"><?= $fecha->format('d/m/Y'); ?></td><td data-label="Hora"><?= $fecha->format('H:i'); ?></td><td data-label="Barbero" data-barbero-id="<?= $registro['barbero_id'] ?>"><?= htmlspecialchars($registro['nombre_barbero']); ?></td><td data-label="Servicio" data-servicio-id="<?= $registro['tipo_servicio_id'] ?>"><?= htmlspecialchars($registro['nombre_servicio']); ?></td><td data-label="Costo" data-costo="<?= $registro['costo_total'] ?>">$<?= number_format($registro['costo_total'], 0, ',', '.'); ?></td><td data-label="Propina" data-propina="<?= $registro['propina'] ?>">$<?= number_format($registro['propina'], 0, ',', '.'); ?></td><td data-label="Total" class="total-cell">$<?= number_format($total_servicio, 0, ',', '.'); ?></td><td data-label="Acciones" class="actions-cell"><button onclick="editarServicio(<?= $registro['id'] ?>)" class="btn-action btn-edit" title="Editar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button><button onclick="eliminarServicio(<?= $registro['id'] ?>)" class="btn-action btn-delete" title="Eliminar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button></td></tr><?php endwhile; ?><?php else: ?><tr><td colspan="8" class="empty-state"><svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><p>No hay registros de servicios en este período</p></td></tr><?php endif; ?></tbody><?php if ($historial_query->num_rows > 0): ?><tfoot><tr><td colspan="7" class="total-label">TOTAL EN HISTORIAL:</td><td class="total-amount">$<?= number_format($total_ganado, 0, ',', '.'); ?> COP</td></tr></tfoot><?php endif; ?></table></div></section>
        <section class="card" id="reportes-section"><div class="card-header"><h2><svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>Descargar Reportes</h2></div><div class="reportes-grid"><a href="?export=dia" class="reporte-btn"><div class="icon-wrapper"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></div><span>Reporte del Día</span></a><a href="?export=semana" class="reporte-btn"><div class="icon-wrapper"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div><span>Reporte Semanal</span></a><a href="?export=mes" class="reporte-btn"><div class="icon-wrapper"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg></div><span>Reporte Mensual</span></a><a href="?export=ano" class="reporte-btn"><div class="icon-wrapper"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><span>Reporte Anual</span></a></div></section>
    </div>

    <div id="editModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Editar Servicio</h3><span class="close" onclick="cerrarModal('editModal')">&times;</span></div><div class="modal-body"><form id="editForm"><input type="hidden" id="edit_id"><div class="form-group"><label for="edit_barbero">Barbero</label><select id="edit_barbero" required><?php $barberos_query->data_seek(0); while ($barbero = $barberos_query->fetch_assoc()): ?><option value="<?= $barbero['id']; ?>"><?= htmlspecialchars($barbero['nombre']); ?></option><?php endwhile; ?></select></div><div class="form-group"><label for="edit_servicio">Servicio</label><select id="edit_servicio" required><?php $servicios_query->data_seek(0); while ($servicio = $servicios_query->fetch_assoc()): ?><option value="<?= $servicio['id']; ?>"><?= htmlspecialchars($servicio['nombre_servicio']); ?></option><?php endwhile; ?></select></div><div class="form-group"><label for="edit_costo">Costo (COP)</label><input type="number" id="edit_costo" step="1000" required></div><div class="form-group"><label for="edit_propina">Propina (COP)</label><input type="number" id="edit_propina" step="1000"></div><button type="submit" class="btn-primary">Guardar Cambios</button></form></div></div></div>
    <div id="editBarberoModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Editar Barbero</h3><span class="close" onclick="cerrarModal('editBarberoModal')">&times;</span></div><div class="modal-body"><form id="editBarberoForm"><input type="hidden" id="edit_barbero_id"><div class="form-group"><label for="edit_barbero_nombre">Nombre</label><input type="text" id="edit_barbero_nombre" required></div><div class="form-group"><label for="edit_barbero_telefono">Teléfono</label><input type="tel" id="edit_barbero_telefono"></div><button type="submit" class="btn-primary">Guardar Cambios</button></form></div></div></div>
    <div id="confirmModal" class="modal"><div class="modal-content"><div class="modal-header"><h3 id="confirmModalTitle">Confirmar Acción</h3><span class="close" onclick="hideConfirmModal()">&times;</span></div><div class="modal-body"><p id="confirmModalText">¿Está seguro?</p><div class="modal-actions"><button id="cancelBtn" class="btn-secondary">Cancelar</button><button id="confirmBtn" class="btn-primary">Confirmar</button></div></div></div></div>

<script>
    function showConfirmModal(text, title = 'Confirmar Acción', callback) {
        const modal = document.getElementById('confirmModal');
        if (!modal) return;
        document.getElementById('confirmModalTitle').textContent = title;
        document.getElementById('confirmModalText').textContent = text;
        modal.classList.add('show');
        const confirmBtn = document.getElementById('confirmBtn');
        const newBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
        newBtn.addEventListener('click', () => {
            if (typeof callback === 'function') callback();
            hideConfirmModal();
        });
        document.getElementById('cancelBtn').addEventListener('click', hideConfirmModal);
    }
    function hideConfirmModal() { document.getElementById('confirmModal').classList.remove('show'); }
    function cerrarModal(modalId) { document.getElementById(modalId).classList.remove('show'); }

    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
        const sections = document.querySelectorAll('section.card');
        const statsGrid = document.querySelector('.stats-grid');

        if (document.getElementById('hamburgerBtn')) {
            document.getElementById('hamburgerBtn').addEventListener('click', () => sidebar.classList.toggle('show'));
        }

        function showSection(sectionName) {
            navLinks.forEach(l => l.classList.remove('active'));
            const activeLink = document.querySelector(`.nav-link[data-section="${sectionName}"]`);
            if (activeLink) activeLink.classList.add('active');

            sections.forEach(s => s.style.display = 'none');

            if (sectionName === 'dashboard') {
                if(statsGrid) statsGrid.style.display = 'grid';
                ['registro-section', 'rendimiento-section'].forEach(id => {
                    const el = document.getElementById(id);
                    if(el) el.style.display = 'block';
                });
            } else {
                if(statsGrid) statsGrid.style.display = 'none';
                const targetSection = document.getElementById(`${sectionName}-section`);
                if (targetSection) targetSection.style.display = 'block';
            }
        }

        function handleNavigation() {
            const params = new URLSearchParams(window.location.search);
            const hash = window.location.hash.substring(1);
            let section = hash || (params.get('filtro') ? 'historial' : (params.get('view') === 'barberos' ? 'barberos' : 'dashboard'));

            if(params.get('filtro')) {
                window.location.hash = 'historial';
            }

            showSection(section);
            if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        }

        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sectionName = this.dataset.section;
                window.location.hash = sectionName;
            });
        });

        window.addEventListener('hashchange', handleNavigation);
        handleNavigation();

        const servicioSelect = document.getElementById('servicio_id');
        const costoInput = document.getElementById('costo');
        if (servicioSelect && costoInput) {
            servicioSelect.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                const precio = opt.getAttribute('data-precio');
                if (precio) {
                    costoInput.value = parseFloat(precio);
                    costoInput.classList.add('price-updated');
                    setTimeout(() => costoInput.classList.remove('price-updated'), 300);
                }
            });
        }

        const editForm = document.getElementById('editForm');
        if (editForm) {
            editForm.addEventListener('submit', e => {
                e.preventDefault();
                const id = document.getElementById('edit_id').value;
                const data = { barbero_id: document.getElementById('edit_barbero').value, servicio_id: document.getElementById('edit_servicio').value, costo: document.getElementById('edit_costo').value, propina: document.getElementById('edit_propina').value };
                fetch(`index.php?action=update&id=${id}`, { method: 'POST', body: JSON.stringify(data) }).then(res => res.json()).then(d => d.success ? location.reload() : alert('Error.'));
            });
        }

        const editBarberoForm = document.getElementById('editBarberoForm');
        if (editBarberoForm) {
            editBarberoForm.addEventListener('submit', e => {
                e.preventDefault();
                const id = document.getElementById('edit_barbero_id').value;
                const data = { nombre: document.getElementById('edit_barbero_nombre').value, telefono: document.getElementById('edit_barbero_telefono').value };
                fetch(`index.php?action=update_barbero&id=${id}`, { method: 'POST', body: JSON.stringify(data) }).then(res => res.json()).then(d => d.success ? location.reload() : alert('Error.'));
            });
        }
    });

    function eliminarServicio(id) { showConfirmModal('¿Seguro que quieres eliminar este servicio?', 'Eliminar Servicio', () => fetch(`index.php?action=delete&id=${id}`).then(res => res.json()).then(d => d.success && location.reload())); }
    function eliminarBarbero(id) { showConfirmModal('¿Seguro? No se pueden eliminar barberos con servicios registrados.', 'Eliminar Barbero', () => fetch(`index.php?action=delete_barbero&id=${id}`).then(res => res.json()).then(d => d.success ? location.reload() : alert(d.message || 'Error.'))); }
    function editarServicio(id) { const modal = document.getElementById('editModal'); const row = document.querySelector(`tr[data-id="${id}"]`); if(!modal || !row) return; document.getElementById('edit_id').value = id; document.getElementById('edit_barbero').value = row.querySelector('[data-barbero-id]').dataset.barberoId; document.getElementById('edit_servicio').value = row.querySelector('[data-servicio-id]').dataset.servicioId; document.getElementById('edit_costo').value = row.querySelector('[data-costo]').dataset.costo; document.getElementById('edit_propina').value = row.querySelector('[data-propina]').dataset.propina; modal.classList.add('show'); }
    function editarBarbero(id, nombre, telefono) { const modal = document.getElementById('editBarberoModal'); if(!modal) return; document.getElementById('edit_barbero_id').value = id; document.getElementById('edit_barbero_nombre').value = nombre; document.getElementById('edit_barbero_telefono').value = telefono || ''; modal.classList.add('show'); }

    window.onclick = e => document.querySelectorAll('.modal').forEach(m => { if (e.target == m) m.classList.remove('show'); });
    window.addEventListener('load', () => document.querySelectorAll('.stat-card').forEach((c, i) => setTimeout(() => c.classList.add('animate-in'), i * 100)));
</script>
</body>
</html>
<?php
$mysqli->close();
?>