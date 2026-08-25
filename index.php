<?php
// ============================================================
// INDEX - PÁGINA PRINCIPAL DEL SISTEMA ESG
// ============================================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Database.php';

$auth = new Auth();
$db = Database::getInstancia();

// Autenticar usuario por PC
if (!$auth->autenticarPorPC()) {
    // Si hay error, mostrar mensaje
    $error = $_SESSION['mensaje_error'] ?? 'Error de autenticación';
    unset($_SESSION['mensaje_error']);
    die($error);
}

// Obtener datos del usuario actual
$usuario_id = $_SESSION['usuario_id'];
$pc = $_SESSION['pc_identificador'];
$usuario = $_SESSION['nombre_usuario'];
$nombre_completo = $_SESSION['nombre_completo'];
$rol = $_SESSION['rol'];
$departamento_id = $_SESSION['departamento_id'];
$departamento_nombre = $_SESSION['departamento_nombre'];
$es_tecnico = $_SESSION['es_tecnico'];
$activo = $_SESSION['activo'];

// Obtener nombre del rol
$rol_nombre = getRolNombre($rol);
$rol_color = getRolColor($rol);
$rol_icono = getRolIcono($rol);

// Verificar si es SuperAdmin
$esSuperAdmin = ($rol == 1);
$esEncargado = ($rol == 2);
$esTecnico = ($es_tecnico == 1);

// Determinar si debe mostrar el formulario
$mostrarFormulario = !$esSuperAdmin && $activo;

// Obtener tickets del usuario (si no es SuperAdmin)
$misTickets = [];
if (!$esSuperAdmin) {
    $misTickets = $db->obtenerTicketsPorUsuario($usuario_id);
}

// Obtener estadísticas para SuperAdmin
$estadisticas = [];
if ($esSuperAdmin) {
    $estadisticas = $db->obtenerEstadisticas();
}

// Obtener lista de departamentos para el selector
$departamentos = $db->obtenerDepartamentos();

// Verificar si hay mensajes flash
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
    <title>ESG - Entorno Seguro y Gestión</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts - Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Estilos personalizados -->
    <link href="assets/css/estilo.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Barra superior con info del usuario -->
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
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <!-- Botón Internet -->
                                <span id="estado-internet" class="badge bg-success me-2">
                                    <i class="fas fa-wifi"></i> <span id="estado-internet-texto">Online</span>
                                </span>
                                
                                <?php if ($esSuperAdmin): ?>
                                <a href="admin.php" class="btn btn-primary">
                                    <i class="fas fa-cogs"></i> Ir al Panel
                                </a>
                                <?php else: ?>
                                <a href="admin.php" class="btn btn-primary">
                                    <i class="fas fa-tasks"></i> Mis Tickets
                                </a>
                                <?php endif; ?>
                                
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
                
                <!-- Contenido principal -->
                <div class="container mt-4">
                    <div class="row">
                        <!-- Tarjeta de información del usuario -->
                        <div class="col-md-4 mb-4">
                            <div class="card shadow-sm">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-id-card"></i> Información del Usuario</h5>
                                </div>
                                <div class="card-body">
                                    <div class="info-item">
                                        <strong><i class="fas fa-desktop"></i> PC:</strong>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($pc); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong><i class="fas fa-user"></i> Usuario:</strong>
                                        <span><?php echo htmlspecialchars($nombre_completo); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong><i class="fas fa-building"></i> Departamento:</strong>
                                        <span><?php echo htmlspecialchars($departamento_nombre ?? 'Sin asignar'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong><i class="fas fa-user-tag"></i> Rol:</strong>
                                        <span class="badge bg-<?php echo $rol_color; ?>">
                                            <i class="fas <?php echo $rol_icono; ?>"></i>
                                            <?php echo $rol_nombre; ?>
                                        </span>
                                    </div>
                                    <?php if ($esTecnico): ?>
                                    <div class="info-item">
                                        <strong><i class="fas fa-tools"></i> Técnico TI:</strong>
                                        <span class="badge bg-info">Sí</span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <strong><i class="fas fa-circle"></i> Estado:</strong>
                                        <span class="badge bg-<?php echo $activo ? 'success' : 'danger'; ?>">
                                            <?php echo $activo ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($esSuperAdmin): ?>
                        <!-- Panel de SuperAdmin -->
                        <div class="col-md-8">
                            <div class="card shadow-sm">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Panel de SuperAdmin</h5>
                                </div>
                                <div class="card-body">
                                    <div class="text-center">
                                        <i class="fas fa-shield-alt fa-4x text-danger mb-3"></i>
                                        <h4>👑 SuperAdmin</h4>
                                        <p class="text-muted">Bienvenido al sistema de gestión ESG</p>
                                        
                                        <div class="row mt-4">
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
                                        
                                        <a href="admin.php" class="btn btn-danger btn-lg mt-3">
                                            <i class="fas fa-cogs"></i> IR AL PANEL DE ADMINISTRACIÓN
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Formulario de tickets para usuarios y encargados -->
                        <div class="col-md-8">
                            <?php if ($mostrarFormulario): ?>
                            <div class="card shadow-sm">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-ticket-alt"></i> Crear Nuevo Ticket</h5>
                                </div>
                                <div class="card-body">
                                    <form id="form-ticket" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label for="titulo" class="form-label">Título del Ticket <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="titulo" name="titulo" 
                                                   placeholder="Ej: Problema con la impresora" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="descripcion" class="form-label">Descripción <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="descripcion" name="descripcion" 
                                                      rows="4" placeholder="Describa el problema en detalle (mínimo 10 caracteres)" 
                                                      minlength="10" required></textarea>
                                            <div class="form-text">Mínimo 10 caracteres</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="foto" class="form-label">Foto (Opcional)</label>
                                            <input type="file" class="form-control" id="foto" name="foto" 
                                                   accept="image/jpeg,image/png">
                                            <div class="form-text">Formatos permitidos: JPG, PNG. Tamaño máximo: 5MB</div>
                                        </div>
                                        <?php if ($esEncargado): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> 
                                            Como encargado, sus tickets serán aprobados automáticamente.
                                        </div>
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-primary btn-lg w-100">
                                            <i class="fas fa-paper-plane"></i> Enviar Ticket
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Lista de tickets del usuario -->
                            <?php if (!empty($misTickets)): ?>
                            <div class="card shadow-sm mt-4">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="fas fa-list"></i> Mis Tickets</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Título</th>
                                                    <th>Estado</th>
                                                    <th>Fecha</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($misTickets as $ticket): ?>
                                                <tr>
                                                    <td>#<?php echo $ticket['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($ticket['titulo']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $ticket['estado'] == 'pendiente_aprobacion' ? 'warning' : 
                                                                ($ticket['estado'] == 'aprobado' ? 'success' : 
                                                                ($ticket['estado'] == 'rechazado' ? 'danger' : 
                                                                ($ticket['estado'] == 'en_progreso' ? 'info' : 'secondary'))); 
                                                        ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['estado'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($ticket['fecha'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info ver-ticket" data-id="<?php echo $ticket['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
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
                    <!-- Contenido cargado vía JS -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/offline.js"></script>
    
    <script>
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            // Manejar envío de formulario
            const form = document.getElementById('form-ticket');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    crearTicket(this);
                });
            }
            
            // Manejar click en ver ticket
            document.querySelectorAll('.ver-ticket').forEach(btn => {
                btn.addEventListener('click', function() {
                    verTicket(this.dataset.id);
                });
            });
        });
    </script>
</body>
</html>
