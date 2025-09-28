<?php
session_start();
require_once 'db_config.php';

// 1. Proteger la página, verificar rol de 'admin' y obtener el ID de su barbería
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$barberia_id = $_SESSION['user_barberia_id'];
if (empty($barberia_id)) {
    // Si un admin no tiene barbería asignada, no puede hacer nada.
    die("Error: No tienes una barbería asignada. Contacta al superadministrador.");
}


// 2. PROCESAR ACCIONES POST (con filtro de barberia_id)
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
}

// 3. PROCESAR ACCIONES AJAX (con filtro de barberia_id)
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
        // Verificar si tiene servicios asociados
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
}

// 4. OBTENER DATOS PARA LA VISTA (con filtro de barberia_id)
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

$historial_sql = "
    SELECT s.id, s.costo_total, s.propina, s.fecha_registro, s.barbero_id, s.tipo_servicio_id,
           b.nombre AS nombre_barbero, ts.nombre_servicio
    FROM servicios s
    JOIN barberos b ON s.barbero_id = b.id
    JOIN tipos_servicio ts ON s.tipo_servicio_id = ts.id
    WHERE s.barberia_id = ? $where_fecha
    ORDER BY s.fecha_registro DESC LIMIT 100";
$historial_stmt = $mysqli->prepare($historial_sql);
$historial_stmt->bind_param("i", $barberia_id);
$historial_stmt->execute();
$historial_query = $historial_stmt->get_result();

$stats_sql = "
    SELECT COUNT(*) as total_servicios, SUM(costo_total + propina) as total_ganado,
           AVG(costo_total) as promedio_servicio, SUM(propina) as total_propinas
    FROM servicios WHERE DATE(fecha_registro) = CURDATE() AND barberia_id = ?";
$stats_stmt = $mysqli->prepare($stats_sql);
$stats_stmt->bind_param("i", $barberia_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$stats_barbero_sql = "
    SELECT b.nombre, COUNT(s.id) as servicios, SUM(s.costo_total + s.propina) as total
    FROM servicios s JOIN barberos b ON s.barbero_id = b.id
    WHERE DATE(s.fecha_registro) = CURDATE() AND s.barberia_id = ?
    GROUP BY b.id ORDER BY total DESC";
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
                <div class="logo-icon">
                     <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8" cy="7" r="4"/></svg>
                </div>
                <div>
                    <h1>Panel de Administración</h1>
                    <p class="header-subtitle">Bienvenido, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?></p>
                </div>
            </div>
            <a href="logout.php" class="logout-button">Cerrar Sesión</a>
        </div>
    </header>

    <div class="container" id="main-container">
        <?php if (isset($_SESSION['mensaje'])) { echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); } ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Ganancia Total Hoy</h3>
                <p class="stat-number">$<?= number_format($stats['total_ganado'] ?? 0, 0, ',', '.') ?></p>
            </div>
            <div class="stat-card">
                <h3>Servicios Hoy</h3>
                <p class="stat-number"><?= $stats['total_servicios'] ?? 0 ?></p>
            </div>
            <div class="stat-card">
                <h3>Promedio por Servicio</h3>
                <p class="stat-number">$<?= number_format($stats['promedio_servicio'] ?? 0, 0, ',', '.') ?></p>
            </div>
            <div class="stat-card">
                <h3>Propinas Hoy</h3>
                <p class="stat-number">$<?= number_format($stats['total_propinas'] ?? 0, 0, ',', '.') ?></p>
            </div>
        </div>

        <nav class="main-nav">
             <a href="#" class="nav-link active" data-section="registro">Registro</a>
             <a href="#" class="nav-link" data-section="barberos">Barberos</a>
             <a href="#" class="nav-link" data-section="historial">Historial</a>
        </nav>

        <section class="card" id="registro-section">
            <div class="card-header"><h2>Registrar Nuevo Servicio</h2></div>
            <form action="admin_dashboard.php" method="POST" style="padding: 2rem;">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="barbero_id">Barbero</label>
                        <select id="barbero_id" name="barbero_id" required>
                            <option value="">Seleccione barbero...</option>
                            <?php $barberos_query->data_seek(0); while ($barbero = $barberos_query->fetch_assoc()): ?>
                                <option value="<?= $barbero['id']; ?>"><?= htmlspecialchars($barbero['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="servicio_id">Tipo de Servicio</label>
                        <select id="servicio_id" name="servicio_id" required>
                            <option value="">Seleccione servicio...</option>
                            <?php $servicios_query->data_seek(0); while ($servicio = $servicios_query->fetch_assoc()): ?>
                                <option value="<?= $servicio['id']; ?>" data-precio="<?= $servicio['precio_base']; ?>"><?= htmlspecialchars($servicio['nombre_servicio']) . ' - $' . number_format($servicio['precio_base'], 0, ',', '.'); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="costo">Costo Total (COP)</label>
                        <input type="number" id="costo" name="costo" step="1000" min="0" required placeholder="0">
                    </div>
                    <div class="form-group">
                        <label for="propina">Propina (COP)</label>
                        <input type="number" id="propina" name="propina" step="1000" min="0" value="0" placeholder="0">
                    </div>
                </div>
                <button type="submit" name="registrar_corte" class="btn-primary">Registrar Servicio</button>
            </form>
        </section>

        <section class="card" id="barberos-section" style="display:none;">
            <div class="card-header"><h2>Gestión de Barberos</h2></div>
             <div style="padding: 2rem;">
                <h3>Agregar Nuevo Barbero</h3>
                <form action="admin_dashboard.php" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre_barbero">Nombre del Barbero</label>
                            <input type="text" id="nombre_barbero" name="nombre_barbero" required>
                        </div>
                        <div class="form-group">
                            <label for="telefono_barbero">Teléfono (Opcional)</label>
                            <input type="tel" id="telefono_barbero" name="telefono_barbero">
                        </div>
                    </div>
                    <button type="submit" name="agregar_barbero" class="btn-primary">Agregar Barbero</button>
                </form>
            </div>
            <div style="padding: 0 2rem 2rem;">
                <h3>Barberos Registrados</h3>
                <div class="barberos-list">
                    <?php
                    $barberos_lista_stmt = $mysqli->prepare("
                        SELECT b.id, b.nombre, b.telefono, COUNT(s.id) as total_servicios
                        FROM barberos b
                        LEFT JOIN servicios s ON b.id = s.barbero_id AND s.barberia_id = ?
                        WHERE b.barberia_id = ?
                        GROUP BY b.id ORDER BY b.nombre");
                    $barberos_lista_stmt->bind_param("ii", $barberia_id, $barberia_id);
                    $barberos_lista_stmt->execute();
                    $barberos_lista = $barberos_lista_stmt->get_result();
                    while($barbero = $barberos_lista->fetch_assoc()):
                    ?>
                        <div class="barbero-card" data-id="<?= $barbero['id'] ?>">
                            <div class="barbero-info">
                                <h4><?= htmlspecialchars($barbero['nombre']) ?></h4>
                                <?php if($barbero['telefono']): ?><p>📱 <?= htmlspecialchars($barbero['telefono']) ?></p><?php endif; ?>
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

        <section class="card" id="historial-section" style="display:none;">
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
                    <thead><tr><th>Fecha</th><th>Barbero</th><th>Servicio</th><th>Costo</th><th>Propina</th><th>Total</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php while ($registro = $historial_query->fetch_assoc()): ?>
                            <tr data-id="<?= $registro['id'] ?>">
                                <td><?= date('d/m/Y H:i', strtotime($registro['fecha_registro'])) ?></td>
                                <td data-barbero-id="<?= $registro['barbero_id'] ?>"><?= htmlspecialchars($registro['nombre_barbero']) ?></td>
                                <td data-servicio-id="<?= $registro['tipo_servicio_id'] ?>"><?= htmlspecialchars($registro['nombre_servicio']) ?></td>
                                <td data-costo="<?= $registro['costo_total'] ?>">$<?= number_format($registro['costo_total'], 0, ',', '.') ?></td>
                                <td data-propina="<?= $registro['propina'] ?>">$<?= number_format($registro['propina'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($registro['costo_total'] + $registro['propina'], 0, ',', '.') ?></td>
                                <td class="actions-cell">
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
    <div id="confirmModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Confirmar</h3><span class="close" onclick="hideConfirmModal()">&times;</span></div><div class="modal-body"><p id="confirmModalText"></p><div class="modal-actions"><button id="cancelBtn" class="btn-secondary">Cancelar</button><button id="confirmBtn" class="btn-primary">Confirmar</button></div></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Navegación principal
    const navLinks = document.querySelectorAll('.main-nav .nav-link');
    const sections = document.querySelectorAll('section.card');
    navLinks.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const sectionId = e.target.dataset.section + '-section';
            navLinks.forEach(l => l.classList.remove('active'));
            e.target.classList.add('active');
            sections.forEach(s => s.style.display = s.id === sectionId ? 'block' : 'none');
        });
    });

    // Cargar sección correcta según URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('view') === 'barberos') document.querySelector('[data-section="barberos"]').click();
    if (urlParams.get('filtro')) document.querySelector('[data-section="historial"]').click();

    // Lógica para auto-llenar precio
    document.getElementById('servicio_id').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        document.getElementById('costo').value = selected.dataset.precio || '';
    });

    // Lógica de Modals
    document.getElementById('editForm').addEventListener('submit', e => {
        e.preventDefault();
        const id = document.getElementById('edit_id').value;
        const data = { barbero_id: document.getElementById('edit_barbero').value, servicio_id: document.getElementById('edit_servicio').value, costo: document.getElementById('edit_costo').value, propina: document.getElementById('edit_propina').value };
        fetch(`admin_dashboard.php?action=update&id=${id}`, { method: 'POST', body: JSON.stringify(data) })
            .then(res => res.json()).then(data => data.success ? location.reload() : alert('Error al actualizar'));
    });
    document.getElementById('editBarberoForm').addEventListener('submit', e => {
        e.preventDefault();
        const id = document.getElementById('edit_barbero_id').value;
        const data = { nombre: document.getElementById('edit_barbero_nombre').value, telefono: document.getElementById('edit_barbero_telefono').value };
        fetch(`admin_dashboard.php?action=update_barbero&id=${id}`, { method: 'POST', body: JSON.stringify(data) })
            .then(res => res.json()).then(data => data.success ? location.reload() : alert('Error al actualizar'));
    });
});

// Funciones globales para modals
let onConfirmCallback = null;
function showConfirmModal(text, callback) {
    document.getElementById('confirmModalText').textContent = text;
    onConfirmCallback = callback;
    document.getElementById('confirmModal').classList.add('show');
}
function hideConfirmModal() { document.getElementById('confirmModal').classList.remove('show'); }
document.getElementById('confirmBtn').addEventListener('click', () => { if(onConfirmCallback) onConfirmCallback(); hideConfirmModal(); });
document.getElementById('cancelBtn').addEventListener('click', hideConfirmModal);

function cerrarModal(modalId) { document.getElementById(modalId).classList.remove('show'); }
window.onclick = e => { if (e.target.classList.contains('modal')) e.target.classList.remove('show'); };

function eliminarServicio(id) {
    showConfirmModal('¿Seguro que quieres eliminar este servicio?', () => {
        fetch(`admin_dashboard.php?action=delete&id=${id}`).then(res => res.json()).then(data => data.success ? location.reload() : alert('Error al eliminar'));
    });
}
function eliminarBarbero(id) {
    showConfirmModal('¿Seguro que quieres eliminar este barbero?', () => {
        fetch(`admin_dashboard.php?action=delete_barbero&id=${id}`).then(res => res.json()).then(data => data.success ? location.reload() : alert(data.message || 'Error al eliminar'));
    });
}
function editarServicio(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_barbero').value = row.querySelector('[data-barbero-id]').dataset.barberoId;
    document.getElementById('edit_servicio').value = row.querySelector('[data-servicio-id]').dataset.servicioId;
    document.getElementById('edit_costo').value = row.querySelector('[data-costo]').dataset.costo;
    document.getElementById('edit_propina').value = row.querySelector('[data-propina]').dataset.propina;
    document.getElementById('editModal').classList.add('show');
}
function editarBarbero(id, nombre, telefono) {
    document.getElementById('edit_barbero_id').value = id;
    document.getElementById('edit_barbero_nombre').value = nombre;
    document.getElementById('edit_barbero_telefono').value = telefono;
    document.getElementById('editBarberoModal').classList.add('show');
}
</script>
</body>
</html>
<?php $mysqli->close(); ?>