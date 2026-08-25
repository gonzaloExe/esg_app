<?php
// ============================================================
// ADMIN - PANEL DE ADMINISTRACIÓN ESG
// ============================================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Database.php';

// Función para obtener color del estado
function getEstadoColor($estado) {
    $colores = [
        'pendiente_aprobacion' => 'warning',
        'aprobado' => 'success',
        'rechazado' => 'danger',
        'en_progreso' => 'info',
        'resuelto' => 'secondary'
    ];
    return $colores[$estado] ?? 'secondary';
}

$auth = new Auth();
$db = Database::getInstancia();

// Autenticar usuario por PC
if (!$auth->autenticarPorPC()) {
    $error = $_SESSION['mensaje_error'] ?? 'Error de autenticación';
    unset($_SESSION['mensaje_error']);
    die($error);
}

// Obtener datos del usuario actual
$usuario_id = $_SESSION['usuario_id'];
$pc = $_SESSION['pc_identificador'];
$nombre_completo = $_SESSION['nombre_completo'];
$rol = $_SESSION['rol'];
$departamento_id = $_SESSION['departamento_id'];
$departamento_nombre = $_SESSION['departamento_nombre'];
$es_tecnico = $_SESSION['es_tecnico'];
$activo = $_SESSION['activo'];

$rol_nombre = getRolNombre($rol);
$rol_color = getRolColor($rol);
$rol_icono = getRolIcono($rol);

$esSuperAdmin = ($rol == 1);
$esEncargado = ($rol == 2);
$esTecnico = ($es_tecnico == 1);

// Verificar acceso
if (!$activo) {
    die('Usuario desactivado');
}

// Obtener datos según rol
$tickets = [];
$estadisticas = [];
$usuarios = [];
$departamentos = [];
$sinEncargado = [];

if ($esSuperAdmin) {
    // SuperAdmin: ve todo
    $tickets = $db->obtenerTickets();
    $estadisticas = $db->obtenerEstadisticas();
    
    $stmt = $db->prepare("
        SELECT u.*, d.nombre as departamento_nombre 
        FROM usuarios u 
        LEFT JOIN departamentos d ON u.departamento_id = d.id 
        ORDER BY u.id
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll();
    
    $departamentos = $db->obtenerDepartamentos();
    
    // Verificar departamentos sin encargado
    $stmt = $db->prepare("
        SELECT d.* 
        FROM departamentos d 
        WHERE d.activo = 1 
        AND NOT EXISTS (
            SELECT 1 FROM usuarios u 
            WHERE u.departamento_id = d.id 
            AND u.rol = 2 
            AND u.activo = 1
        )
    ");
    $stmt->execute();
    $sinEncargado = $stmt->fetchAll();
    
} elseif ($esEncargado) {
    // Encargado: ve solo su departamento
    $tickets = $db->obtenerTicketsPorDepartamento($departamento_id);
    $estadisticas = $db->obtenerEstadisticas();
    $departamentos = $db->obtenerDepartamentos();
    
} elseif ($esTecnico) {
    // Técnico TI: ve tickets aprobados de todos los departamentos
    $ticketsAprobados = $db->obtenerTickets(['estado' => 'aprobado']);
    $ticketsEnProgreso = $db->obtenerTickets(['estado' => 'en_progreso', 'tecnico_asignado' => $usuario_id]);
    $tickets = array_merge($ticketsAprobados, $ticketsEnProgreso);
    $estadisticas = $db->obtenerEstadisticas();
    
} else {
    // Usuario normal: redirigir al index
    redirect('index.php');
}

// Obtener técnicos disponibles para asignación
$tecnicos = [];
if ($esTecnico) {
    $stmt = $db->prepare("
        SELECT u.*, d.nombre as departamento_nombre 
        FROM usuarios u 
        LEFT JOIN departamentos d ON u.departamento_id = d.id 
        WHERE u.es_tecnico = 1 AND u.activo = 1 AND u.id != ?
        ORDER BY u.nombre_completo
    ");
    $stmt->execute([$usuario_id]);
    $tecnicos = $stmt->fetchAll();
}

// Mensajes flash
$mensaje_exito = $_SESSION['mensaje_exito'] ?? null;
$mensaje_error = $_SESSION['mensaje_error'] ?? null;
unset($_SESSION['mensaje_exito']);
unset($_SESSION['mensaje_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel ESG - <?php echo $rol_nombre; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/estilo.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Barra superior -->
                <div class="header-user-bar">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="user-info">
                                    <span class="badge bg-<?php echo $rol_color; ?>">
                                        <i class="fas <?php echo $rol_icono; ?>"></i>
                                        <?php echo $rol_nombre; ?>
                                    </span>
                                    <span class="pc-info">
                                        <i class="fas fa-desktop"></i> <?php echo htmlspecialchars($pc); ?>
                                    </span>
                                    <span class="user-info-text">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($nombre_completo); ?>
                                    </span>
                                    <?php if ($departamento_nombre && $departamento_nombre != 'Sin asignar'): ?>
                                    <span class="dept-info">
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($departamento_nombre); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($esTecnico): ?>
                                    <span class="badge bg-info">
                                        <i class="fas fa-tools"></i> Técnico TI
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($esEncargado && $departamento_nombre): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-crown"></i> Encargado de <?php echo htmlspecialchars($departamento_nombre); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($esSuperAdmin): ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-shield-alt"></i> SuperAdmin
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-home"></i> Inicio
                                </a>
                                <a href="logout.php" class="btn btn-outline-danger">
                                    <i class="fas fa-sign-out-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mensajes flash -->
                <?php if ($mensaje_exito): ?>
                <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($mensaje_error): ?>
                <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($mensaje_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Alerta departamentos sin encargado -->
                <?php if ($esSuperAdmin && !empty($sinEncargado)): ?>
                <div class="alert alert-warning m-3">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Departamentos sin encargado:</strong>
                    <?php 
                    $nombres = array_map(function($d) { return $d['nombre']; }, $sinEncargado);
                    echo implode(', ', $nombres);
                    ?>
                    <br><small>Los tickets de estos departamentos quedarán pendientes hasta que se asigne un encargado.</small>
                </div>
                <?php endif; ?>
                
                <!-- Contenido -->
                <div class="container mt-4">
                    <?php if ($esSuperAdmin): ?>
                    <!-- Panel SuperAdmin -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Panel de SuperAdmin</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Estadísticas -->
                                    <div class="row mb-4">
                                        <div class="col-md-3">
                                            <div class="stat-card">
                                                <div class="stat-number"><?php echo $estadisticas['total_tickets'] ?? 0; ?></div>
                                                <div class="stat-label">Total Tickets</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-card">
                                                <div class="stat-number text-warning"><?php echo $estadisticas['por_estado']['pendiente_aprobacion'] ?? 0; ?></div>
                                                <div class="stat-label">Pendientes</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-card">
                                                <div class="stat-number text-success"><?php echo $estadisticas['por_estado']['resuelto'] ?? 0; ?></div>
                                                <div class="stat-label">Resueltos</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-card">
                                                <div class="stat-number text-info"><?php echo $estadisticas['total_usuarios'] ?? 0; ?></div>
                                                <div class="stat-label">Usuarios</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tabs de administración -->
                                    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab">
                                                <i class="fas fa-users"></i> Usuarios
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="departamentos-tab" data-bs-toggle="tab" data-bs-target="#departamentos" type="button" role="tab">
                                                <i class="fas fa-building"></i> Departamentos
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="tickets-tab" data-bs-toggle="tab" data-bs-target="#tickets" type="button" role="tab">
                                                <i class="fas fa-ticket-alt"></i> Tickets
                                            </button>
                                        </li>
                                    </ul>
                                    
                                    <div class="tab-content mt-3" id="adminTabsContent">
                                        <!-- Tab Usuarios -->
                                        <div class="tab-pane fade show active" id="usuarios" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>PC</th>
                                                            <th>Usuario</th>
                                                            <th>Rol</th>
                                                            <th>Departamento</th>
                                                            <th>Técnico</th>
                                                            <th>Estado</th>
                                                            <th>Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($usuarios as $u): ?>
                                                        <tr>
                                                            <td><?php echo $u['id']; ?></td>
                                                            <td><?php echo htmlspecialchars($u['pc_identificador']); ?></td>
                                                            <td><?php echo htmlspecialchars($u['nombre_completo']); ?></td>
                                                            <td>
                                                                <select class="form-select form-select-sm cambiar-rol" data-id="<?php echo $u['id']; ?>" style="min-width: 120px;" <?php echo $u['pc_identificador'] == 'u2274-PC-254' ? 'disabled' : ''; ?>>
                                                                    <option value="1" <?php echo $u['rol'] == 1 ? 'selected' : ''; ?>>SuperAdmin</option>
                                                                    <option value="2" <?php echo $u['rol'] == 2 ? 'selected' : ''; ?>>Encargado</option>
                                                                    <option value="3" <?php echo $u['rol'] == 3 ? 'selected' : ''; ?>>Usuario</option>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <select class="form-select form-select-sm cambiar-departamento" data-id="<?php echo $u['id']; ?>" style="min-width: 130px;">
                                                                    <?php foreach ($departamentos as $d): ?>
                                                                    <option value="<?php echo $d['id']; ?>" <?php echo $u['departamento_id'] == $d['id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($d['nombre']); ?>
                                                                    </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <input type="checkbox" class="form-check-input toggle-tecnico" data-id="<?php echo $u['id']; ?>" <?php echo $u['es_tecnico'] ? 'checked' : ''; ?> <?php echo $u['pc_identificador'] == 'u2274-PC-254' ? 'disabled' : ''; ?>>
                                                            </td>
                                                            <td>
                                                                <input type="checkbox" class="form-check-input toggle-usuario" data-id="<?php echo $u['id']; ?>" <?php echo $u['activo'] ? 'checked' : ''; ?> <?php echo $u['pc_identificador'] == 'u2274-PC-254' ? 'disabled' : ''; ?>>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-danger eliminar-usuario" data-id="<?php echo $u['id']; ?>" <?php echo $u['pc_identificador'] == 'u2274-PC-254' ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <!-- Tab Departamentos -->
                                        <div class="tab-pane fade" id="departamentos" role="tabpanel">
                                            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modal-departamento">
                                                <i class="fas fa-plus"></i> Nuevo Departamento
                                            </button>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Nombre</th>
                                                            <th>Descripción</th>
                                                            <th>Usuarios</th>
                                                            <th>Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($departamentos as $d): 
                                                            $count = 0;
                                                            foreach ($usuarios as $u) {
                                                                if ($u['departamento_id'] == $d['id']) $count++;
                                                            }
                                                        ?>
                                                        <tr>
                                                            <td><?php echo $d['id']; ?></td>
                                                            <td><?php echo htmlspecialchars($d['nombre']); ?></td>
                                                            <td><?php echo htmlspecialchars($d['descripcion']); ?></td>
                                                            <td><?php echo $count; ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-info editar-departamento" data-id="<?php echo $d['id']; ?>" data-nombre="<?php echo htmlspecialchars($d['nombre']); ?>" data-descripcion="<?php echo htmlspecialchars($d['descripcion']); ?>">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger eliminar-departamento" data-id="<?php echo $d['id']; ?>" <?php echo $count > 0 ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <!-- Tab Tickets -->
                                        <div class="tab-pane fade" id="tickets" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Título</th>
                                                            <th>Usuario</th>
                                                            <th>Departamento</th>
                                                            <th>Estado</th>
                                                            <th>Fecha</th>
                                                            <th>Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($tickets as $t): ?>
                                                        <tr>
                                                            <td>#<?php echo $t['id']; ?></td>
                                                            <td><?php echo htmlspecialchars($t['titulo']); ?></td>
                                                            <td><?php echo htmlspecialchars($t['usuario_nombre'] ?? $t['usuario_origen']); ?></td>
                                                            <td><?php echo htmlspecialchars($t['departamento_nombre'] ?? 'Sin asignar'); ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo getEstadoColor($t['estado']); ?>">
                                                                    <?php echo str_replace('_', ' ', $t['estado']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('d/m/Y', strtotime($t['fecha'])); ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-info ver-ticket" data-id="<?php echo $t['id']; ?>">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger eliminar-ticket" data-id="<?php echo $t['id']; ?>">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php elseif ($esEncargado): ?>
                    <!-- Panel Encargado -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-crown"></i> Panel de Encargado - <?php echo htmlspecialchars($departamento_nombre); ?></h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <div class="stat-card">
                                                <div class="stat-number"><?php echo count($tickets); ?></div>
                                                <div class="stat-label">Total Tickets</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="stat-card">
                                                <div class="stat-number text-warning">
                                                    <?php 
                                                    $pendientes = array_filter($tickets, function($t) { return $t['estado'] == 'pendiente_aprobacion'; });
                                                    echo count($pendientes);
                                                    ?>
                                                </div>
                                                <div class="stat-label">Pendientes de Aprobación</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="stat-card">
                                                <div class="stat-number text-success">
                                                    <?php 
                                                    $aprobados = array_filter($tickets, function($t) { return $t['estado'] == 'aprobado'; });
                                                    echo count($aprobados);
                                                    ?>
                                                </div>
                                                <div class="stat-label">Aprobados</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Título</th>
                                                    <th>Usuario</th>
                                                    <th>Estado</th>
                                                    <th>Fecha</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tickets as $t): ?>
                                                <tr>
                                                    <td>#<?php echo $t['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($t['titulo']); ?></td>
                                                    <td><?php echo htmlspecialchars($t['usuario_nombre'] ?? $t['usuario_origen']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getEstadoColor($t['estado']); ?>">
                                                            <?php echo str_replace('_', ' ', $t['estado']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($t['fecha'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info ver-ticket" data-id="<?php echo $t['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($t['estado'] == 'pendiente_aprobacion'): ?>
                                                        <button class="btn btn-sm btn-success aprobar-ticket" data-id="<?php echo $t['id']; ?>">
                                                            <i class="fas fa-check"></i> Aprobar
                                                        </button>
                                                        <button class="btn btn-sm btn-danger rechazar-ticket" data-id="<?php echo $t['id']; ?>">
                                                            <i class="fas fa-times"></i> Rechazar
                                                        </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php elseif ($esTecnico): ?>
                    <!-- Panel Técnico TI -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-tools"></i> Panel de Técnico TI</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-3">
                                            <div class="stat-card">
                                                <div class="stat-number"><?php echo count($tickets); ?></div>
                                                <div class="stat-label">Tickets Disponibles</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-card">
                                                <div class="stat-number text-info">
                                                    <?php 
                                                    $progreso = array_filter($tickets, function($t) { return $t['estado'] == 'en_progreso'; });
                                                    echo count($progreso);
                                                    ?>
                                                </div>
                                                <div class="stat-label">En Progreso</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stat-card">
                                                <div class="stat-number text-success">
                                                    <?php 
                                                    $resueltos = array_filter($tickets, function($t) { return $t['estado'] == 'resuelto'; });
                                                    echo count($resueltos);
                                                    ?>
                                                </div>
                                                <div class="stat-label">Resueltos</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Título</th>
                                                    <th>Usuario</th>
                                                    <th>Departamento</th>
                                                    <th>Estado</th>
                                                    <th>Fecha</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tickets as $t): ?>
                                                <tr>
                                                    <td>#<?php echo $t['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($t['titulo']); ?></td>
                                                    <td><?php echo htmlspecialchars($t['usuario_nombre'] ?? $t['usuario_origen']); ?></td>
                                                    <td><?php echo htmlspecialchars($t['departamento_nombre'] ?? 'Sin asignar'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getEstadoColor($t['estado']); ?>">
                                                            <?php echo str_replace('_', ' ', $t['estado']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($t['fecha'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info ver-ticket" data-id="<?php echo $t['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($t['estado'] == 'aprobado'): ?>
                                                        <button class="btn btn-sm btn-primary asignar-ticket" data-id="<?php echo $t['id']; ?>">
                                                            <i class="fas fa-user-plus"></i> Asignar
                                                        </button>
                                                        <?php elseif ($t['estado'] == 'en_progreso' && $t['asignado_a'] == $usuario_id): ?>
                                                        <button class="btn btn-sm btn-success resolver-ticket" data-id="<?php echo $t['id']; ?>">
                                                            <i class="fas fa-check-double"></i> Resolver
                                                        </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para ver detalle de ticket -->
    <div class="modal fade" id="modal-ticket" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalle del Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modal-ticket-body">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para aprobar/rechazar -->
    <div class="modal fade" id="modal-aprobar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aprobar/Rechazar Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="aprobacion-ticket-id">
                    <div class="mb-3">
                        <label class="form-label">Decisión</label>
                        <div class="btn-group w-100">
                            <button class="btn btn-success btn-lg" onclick="aprobarTicket()">
                                <i class="fas fa-check"></i> Aprobar
                            </button>
                            <button class="btn btn-danger btn-lg" onclick="mostrarRechazo()">
                                <i class="fas fa-times"></i> Rechazar
                            </button>
                        </div>
                    </div>
                    <div id="motivo-rechazo" style="display:none;">
                        <div class="mb-3">
                            <label for="motivo" class="form-label">Motivo del Rechazo <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="motivo" rows="3" placeholder="Explique el motivo del rechazo"></textarea>
                        </div>
                        <button class="btn btn-danger" onclick="rechazarTicket()">
                            <i class="fas fa-times"></i> Rechazar
                        </button>
                        <button class="btn btn-secondary" onclick="cancelarRechazo()">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para asignar ticket -->
    <div class="modal fade" id="modal-asignar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Asignar Ticket a Técnico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="asignar-ticket-id">
                    <div class="mb-3">
                        <label for="tecnico-select" class="form-label">Seleccionar Técnico</label>
                        <select class="form-select" id="tecnico-select">
                            <option value="">Seleccionar técnico...</option>
                            <?php foreach ($tecnicos as $tecnico): ?>
                            <option value="<?php echo $tecnico['id']; ?>">
                                <?php echo htmlspecialchars($tecnico['nombre_completo']); ?> 
                                (<?php echo htmlspecialchars($tecnico['departamento_nombre'] ?? 'Sin departamento'); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" onclick="asignarTicket()">
                        <i class="fas fa-user-check"></i> Asignar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para crear/editar departamento -->
    <div class="modal fade" id="modal-departamento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-departamento-title">Nuevo Departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="departamento-id">
                    <div class="mb-3">
                        <label for="departamento-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="departamento-nombre" placeholder="Ej: Desarrollo">
                    </div>
                    <div class="mb-3">
                        <label for="departamento-descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="departamento-descripcion" rows="3" placeholder="Descripción del departamento"></textarea>
                    </div>
                    <button class="btn btn-primary" onclick="guardarDepartamento()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/admin.js"></script>
    <script src="assets/js/offline.js"></script>
    
    <script>
    // ============================================================
    // SCRIPTS ADICIONALES PARA ADMIN.PHP
    // ============================================================
    
    // Ver ticket
    document.querySelectorAll('.ver-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            fetch(`api.php?action=obtener_ticket&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const t = data.data;
                        const modalBody = document.getElementById('modal-ticket-body');
                        
                        let html = `
                            <div class="row">
                                <div class="col-md-6"><strong>ID:</strong> #${t.id}</div>
                                <div class="col-md-6">
                                    <strong>Estado:</strong> 
                                    <span class="badge bg-${getEstadoColor(t.estado)}">
                                        ${t.estado.replace(/_/g, ' ').toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-12"><strong>Título:</strong> ${t.titulo}</div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <strong>Descripción:</strong><br>
                                    ${t.descripcion}
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6"><strong>Departamento:</strong> ${t.departamento_nombre || 'Sin asignar'}</div>
                                <div class="col-md-6"><strong>Usuario:</strong> ${t.usuario_nombre || t.usuario_origen}</div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6"><strong>Fecha:</strong> ${new Date(t.fecha).toLocaleString()}</div>
                                <div class="col-md-6"><strong>PC Origen:</strong> ${t.pc_origen}</div>
                            </div>
                        `;
                        
                        if (t.foto) {
                            html += `
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <strong>Foto:</strong><br>
                                        <img src="uploads/${t.foto}" class="img-fluid img-thumbnail" style="max-height: 200px;" alt="Foto del ticket">
                                    </div>
                                </div>
                            `;
                        }
                        
                        if (t.aprobado_por) {
                            html += `
                                <hr>
                                <div class="row">
                                    <div class="col-md-6"><strong>Aprobado por:</strong> ${t.aprobado_por_nombre || t.aprobado_por}</div>
                                    <div class="col-md-6"><strong>Fecha aprobación:</strong> ${t.fecha_aprobacion ? new Date(t.fecha_aprobacion).toLocaleString() : 'N/A'}</div>
                                </div>
                            `;
                        }
                        
                        if (t.motivo_rechazo) {
                            html += `
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <strong>Motivo de rechazo:</strong><br>
                                        <span class="text-danger">${t.motivo_rechazo}</span>
                                    </div>
                                </div>
                            `;
                        }
                        
                        if (t.asignado_a) {
                            html += `
                                <hr>
                                <div class="row">
                                    <div class="col-md-6"><strong>Técnico asignado:</strong> ${t.tecnico_asignado || 'Sin asignar'}</div>
                                    <div class="col-md-6"><strong>Fecha asignación:</strong> ${t.fecha_asignacion ? new Date(t.fecha_asignacion).toLocaleString() : 'N/A'}</div>
                                </div>
                            `;
                        }
                        
                        if (t.comentarios_tecnicos) {
                            html += `
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <strong>Comentarios técnicos:</strong><br>
                                        ${t.comentarios_tecnicos}
                                    </div>
                                </div>
                            `;
                        }
                        
                        modalBody.innerHTML = html;
                        const modal = new bootstrap.Modal(document.getElementById('modal-ticket'));
                        modal.show();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo obtener el ticket' });
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo obtener el ticket' });
                });
        });
    });
    
    // Aprobar ticket (Encargado)
    document.querySelectorAll('.aprobar-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            document.getElementById('aprobacion-ticket-id').value = id;
            document.getElementById('motivo-rechazo').style.display = 'none';
            document.getElementById('motivo').value = '';
            const modal = new bootstrap.Modal(document.getElementById('modal-aprobar'));
            modal.show();
        });
    });
    
    // Rechazar ticket (Encargado)
    document.querySelectorAll('.rechazar-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            document.getElementById('aprobacion-ticket-id').value = id;
            document.getElementById('motivo-rechazo').style.display = 'block';
            const modal = new bootstrap.Modal(document.getElementById('modal-aprobar'));
            modal.show();
        });
    });
    
    // Asignar ticket (Técnico TI)
    document.querySelectorAll('.asignar-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            document.getElementById('asignar-ticket-id').value = id;
            
            // Cargar técnicos disponibles
            fetch('api.php?action=obtener_tecnicos')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('tecnico-select');
                        select.innerHTML = '<option value="">Seleccionar técnico...</option>';
                        data.data.forEach(tecnico => {
                            const option = document.createElement('option');
                            option.value = tecnico.id;
                            option.textContent = `${tecnico.nombre_completo} (${tecnico.departamento_nombre || 'Sin departamento'})`;
                            select.appendChild(option);
                        });
                    }
                });
            
            const modal = new bootstrap.Modal(document.getElementById('modal-asignar'));
            modal.show();
        });
    });
    
    // Resolver ticket (Técnico TI)
    document.querySelectorAll('.resolver-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            resolverTicket(id);
        });
    });
    
    // Eliminar ticket (SuperAdmin)
    document.querySelectorAll('.eliminar-ticket').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            eliminarTicket(id);
        });
    });
    
    // Cambiar rol (SuperAdmin)
    document.querySelectorAll('.cambiar-rol').forEach(select => {
        select.addEventListener('change', function() {
            const userId = this.dataset.id;
            const newRol = this.value;
            
            Swal.fire({
                title: '¿Cambiar rol?',
                text: 'Esta acción cambiará el rol del usuario',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, cambiar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=cambiar_rol&usuario_id=${userId}&rol=${newRol}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: '¡Actualizado!', text: data.message })
                                .then(() => location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.error });
                        }
                    });
                }
            });
        });
    });
    
    // Cambiar departamento (SuperAdmin)
    document.querySelectorAll('.cambiar-departamento').forEach(select => {
        select.addEventListener('change', function() {
            const userId = this.dataset.id;
            const newDept = this.value;
            
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=cambiar_departamento&usuario_id=${userId}&departamento_id=${newDept}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: '¡Actualizado!', text: data.message });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.error });
                }
            });
        });
    });
    
    // Toggle técnico (SuperAdmin)
    document.querySelectorAll('.toggle-tecnico').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const userId = this.dataset.id;
            
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_tecnico&usuario_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.error });
                    this.checked = !this.checked;
                }
            });
        });
    });
    
    // Toggle usuario (SuperAdmin)
    document.querySelectorAll('.toggle-usuario').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const userId = this.dataset.id;
            
            Swal.fire({
                title: this.checked ? '¿Activar usuario?' : '¿Desactivar usuario?',
                text: this.checked ? 'El usuario podrá acceder al sistema' : 'El usuario no podrá acceder al sistema',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=toggle_usuario&usuario_id=${userId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.error });
                            this.checked = !this.checked;
                        }
                    });
                } else {
                    this.checked = !this.checked;
                }
            });
        });
    });
    
    // Editar departamento (SuperAdmin)
    document.querySelectorAll('.editar-departamento').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            const descripcion = this.dataset.descripcion;
            
            document.getElementById('departamento-id').value = id;
            document.getElementById('departamento-nombre').value = nombre;
            document.getElementById('departamento-descripcion').value = descripcion || '';
            document.getElementById('modal-departamento-title').textContent = 'Editar Departamento';
            
            const modal = new bootstrap.Modal(document.getElementById('modal-departamento'));
            modal.show();
        });
    });
    
    // Eliminar departamento (SuperAdmin)
    document.querySelectorAll('.eliminar-departamento').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            
            Swal.fire({
                title: '¿Eliminar departamento?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=eliminar_departamento&departamento_id=${id}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Eliminado', text: data.message })
                                .then(() => location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.error });
                        }
                    });
                }
            });
        });
    });
    
    // Guardar departamento (SuperAdmin)
    window.guardarDepartamento = function() {
        const id = document.getElementById('departamento-id').value;
        const nombre = document.getElementById('departamento-nombre').value.trim();
        const descripcion = document.getElementById('departamento-descripcion').value.trim();
        
        if (!nombre) {
            Swal.fire({ icon: 'warning', title: 'Nombre requerido', text: 'El nombre del departamento es obligatorio' });
            return;
        }
        
        let action = 'crear_departamento';
        let data = `action=${action}&nombre=${encodeURIComponent(nombre)}&descripcion=${encodeURIComponent(descripcion)}`;
        
        if (id) {
            action = 'editar_departamento';
            data = `action=${action}&departamento_id=${id}&nombre=${encodeURIComponent(nombre)}&descripcion=${encodeURIComponent(descripcion)}`;
        }
        
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: '¡Guardado!', text: data.message })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
            }
        });
    };
    
    // Evento para nuevo departamento
    document.querySelector('[data-bs-target="#modal-departamento"]')?.addEventListener('click', function() {
        document.getElementById('departamento-id').value = '';
        document.getElementById('departamento-nombre').value = '';
        document.getElementById('departamento-descripcion').value = '';
        document.getElementById('modal-departamento-title').textContent = 'Nuevo Departamento';
    });
    
    // Función para obtener color del estado (para JS)
    function getEstadoColor(estado) {
        const colores = {
            'pendiente_aprobacion': 'warning',
            'aprobado': 'success',
            'rechazado': 'danger',
            'en_progreso': 'info',
            'resuelto': 'secondary'
        };
        return colores[estado] || 'secondary';
    }
    </script>
</body>
</html>