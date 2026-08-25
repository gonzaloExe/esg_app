<?php
// ============================================================
// API UNIFICADA - TODAS LAS ACCIONES DEL SISTEMA
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Database.php';

$auth = new Auth();
$db = Database::getInstancia();

// Autenticar usuario por PC
if (!$auth->autenticarPorPC()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Obtener datos del usuario actual
$usuario_id = $_SESSION['usuario_id'];
$pc = $_SESSION['pc_identificador'];
$rol = $_SESSION['rol'];
$departamento_id = $_SESSION['departamento_id'];
$es_tecnico = $_SESSION['es_tecnico'];
$activo = $_SESSION['activo'];

$esSuperAdmin = ($rol == 1);
$esEncargado = ($rol == 2);

// Verificar si está activo
if (!$activo) {
    echo json_encode(['success' => false, 'error' => 'Usuario desactivado']);
    exit;
}

// Obtener acción
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================================================
// PROCESAR ACCIONES
// ============================================================

try {
    switch ($action) {
        // ============================================================
        // ACCIONES GET
        // ============================================================
        
        case 'obtener_tickets':
            // Obtener tickets según rol
            if ($esSuperAdmin) {
                $tickets = $db->obtenerTickets();
            } elseif ($esEncargado) {
                $tickets = $db->obtenerTicketsPorDepartamento($departamento_id);
            } elseif ($es_tecnico == 1) {
                // Técnico TI: ver tickets aprobados y en progreso
                $aprobados = $db->obtenerTickets(['estado' => 'aprobado']);
                $enProgreso = $db->obtenerTickets(['estado' => 'en_progreso', 'tecnico_asignado' => $usuario_id]);
                $tickets = array_merge($aprobados, $enProgreso);
            } else {
                $tickets = $db->obtenerTicketsPorUsuario($usuario_id);
            }
            
            echo json_encode(['success' => true, 'data' => $tickets]);
            break;
            
        case 'obtener_ticket':
            $id = $_GET['id'] ?? 0;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                break;
            }
            
            // Verificar permisos
            $ticket = $db->obtenerTickets();
            $ticket = array_filter($ticket, function($t) use ($id) {
                return $t['id'] == $id;
            });
            $ticket = reset($ticket);
            
            if (!$ticket) {
                echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
                break;
            }
            
            // Verificar que el usuario tenga acceso a este ticket
            if (!$esSuperAdmin && !$es_tecnico && $ticket['usuario_id'] != $usuario_id) {
                if ($esEncargado && $ticket['departamento_id'] != $departamento_id) {
                    echo json_encode(['success' => false, 'error' => 'Sin permiso para ver este ticket']);
                    break;
                }
            }
            
            echo json_encode(['success' => true, 'data' => $ticket]);
            break;
            
        case 'obtener_usuarios':
            if (!$esSuperAdmin) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                break;
            }
            
            $usuarios = $db->query("
                SELECT u.*, d.nombre as departamento_nombre 
                FROM usuarios u 
                LEFT JOIN departamentos d ON u.departamento_id = d.id 
                ORDER BY u.id
            ")->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $usuarios]);
            break;
            
        case 'obtener_departamentos':
            $departamentos = $db->obtenerDepartamentos();
            echo json_encode(['success' => true, 'data' => $departamentos]);
            break;
            
        case 'obtener_estadisticas':
            $estadisticas = $db->obtenerEstadisticas();
            echo json_encode(['success' => true, 'data' => $estadisticas]);
            break;
            
        case 'mis_tickets':
            $tickets = $db->obtenerTicketsPorUsuario($usuario_id);
            echo json_encode(['success' => true, 'data' => $tickets]);
            break;
            
        case 'obtener_tecnicos':
            $tecnicos = $db->query("
                SELECT u.*, d.nombre as departamento_nombre 
                FROM usuarios u 
                LEFT JOIN departamentos d ON u.departamento_id = d.id 
                WHERE u.es_tecnico = 1 AND u.activo = 1 AND u.id != ?
                ORDER BY u.nombre_completo
            ", [$usuario_id])->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $tecnicos]);
            break;
            
        // ============================================================
        // ACCIONES POST
        // ============================================================
            
        case 'crear_ticket':
            // Verificar que no sea SuperAdmin
            if ($esSuperAdmin) {
                echo json_encode(['success' => false, 'error' => 'SuperAdmin no puede enviar tickets']);
                break;
            }
            
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            
            if (empty($titulo)) {
                echo json_encode(['success' => false, 'error' => 'El título es obligatorio']);
                break;
            }
            
            if (strlen($descripcion) < 10) {
                echo json_encode(['success' => false, 'error' => 'La descripción debe tener al menos 10 caracteres']);
                break;
            }
            
            // Procesar foto si existe
            $foto = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                $foto = subirArchivo($_FILES['foto']);
                if (!$foto) {
                    echo json_encode(['success' => false, 'error' => 'Error al subir la foto']);
                    break;
                }
            }
            
            // Determinar estado inicial
            $estado = 'pendiente_aprobacion';
            $auto_aprobado = 0;
            $aprobado_por = null;
            $fecha_aprobacion = null;
            
            // Si es encargado, auto-aprobar
            if ($esEncargado) {
                $estado = 'aprobado';
                $auto_aprobado = 1;
                $aprobado_por = $usuario_id;
                $fecha_aprobacion = date('Y-m-d H:i:s');
            }
            
            $sql = "INSERT INTO tickets (
                titulo, descripcion, foto, pc_origen, usuario_origen, 
                usuario_id, departamento_id, estado, auto_aprobado, 
                aprobado_por, fecha_aprobacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                $titulo, $descripcion, $foto, $pc, $_SESSION['nombre_usuario'],
                $usuario_id, $departamento_id, $estado, $auto_aprobado,
                $aprobado_por, $fecha_aprobacion
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => $esEncargado ? 'Ticket creado y auto-aprobado' : 'Ticket creado exitosamente',
                    'auto_aprobado' => $auto_aprobado == 1
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al crear ticket']);
            }
            break;
            
        case 'aprobar':
            if (!$esEncargado) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso para aprobar']);
                break;
            }
            
            $ticket_id = $_POST['ticket_id'] ?? 0;
            if ($ticket_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                break;
            }
            
            // Verificar que el ticket pertenezca al departamento del encargado
            $ticket = $db->obtenerTickets();
            $ticket = array_filter($ticket, function($t) use ($ticket_id, $departamento_id) {
                return $t['id'] == $ticket_id && $t['departamento_id'] == $departamento_id;
            });
            $ticket = reset($ticket);
            
            if (!$ticket) {
                echo json_encode(['success' => false, 'error' => 'Ticket no encontrado o no pertenece a tu departamento']);
                break;
            }
            
            if ($ticket['estado'] != 'pendiente_aprobacion') {
                echo json_encode(['success' => false, 'error' => 'El ticket ya fue procesado']);
                break;
            }
            
            $sql = "UPDATE tickets SET estado = 'aprobado', aprobado_por = ?, fecha_aprobacion = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$usuario_id, $ticket_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Ticket aprobado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al aprobar']);
            }
            break;
            
        case 'rechazar':
            if (!$esEncargado) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso para rechazar']);
                break;
            }
            
            $ticket_id = $_POST['ticket_id'] ?? 0;
            $motivo = trim($_POST['motivo'] ?? '');
            
            if ($ticket_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                break;
            }
            
            if (empty($motivo)) {
                echo json_encode(['success' => false, 'error' => 'El motivo es obligatorio']);
                break;
            }
            
            // Verificar que el ticket pertenezca al departamento del encargado
            $ticket = $db->obtenerTickets();
            $ticket = array_filter($ticket, function($t) use ($ticket_id, $departamento_id) {
                return $t['id'] == $ticket_id && $t['departamento_id'] == $departamento_id;
            });
            $ticket = reset($ticket);
            
            if (!$ticket) {
                echo json_encode(['success' => false, 'error' => 'Ticket no encontrado o no pertenece a tu departamento']);
                break;
            }
            
            if ($ticket['estado'] != 'pendiente_aprobacion') {
                echo json_encode(['success' => false, 'error' => 'El ticket ya fue procesado']);
                break;
            }
            
            $sql = "UPDATE tickets SET estado = 'rechazado', aprobado_por = ?, fecha_aprobacion = NOW(), motivo_rechazo = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$usuario_id, $motivo, $ticket_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Ticket rechazado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al rechazar']);
            }
            break;
            
        case 'asignar':
            if ($es_tecnico != 1) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso para asignar']);
                break;
            }
            
            $ticket_id = $_POST['ticket_id'] ?? 0;
            $tecnico_id = $_POST['tecnico_id'] ?? 0;
            
            if ($ticket_id <= 0 || $tecnico_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
                break;
            }
            
            // Verificar que el técnico existe y es técnico
            $tecnico = $db->obtenerUsuarioPorId($tecnico_id);
            if (!$tecnico || $tecnico['es_tecnico'] != 1) {
                echo json_encode(['success' => false, 'error' => 'Técnico no válido']);
                break;
            }
            
            // Verificar que el ticket esté aprobado y sin asignar
            $ticket = $db->obtenerTickets();
            $ticket = array_filter($ticket, function($t) use ($ticket_id) {
                return $t['id'] == $ticket_id && $t['estado'] == 'aprobado';
            });
            $ticket = reset($ticket);
            
            if (!$ticket) {
                echo json_encode(['success' => false, 'error' => 'Ticket no encontrado o no está aprobado']);
                break;
            }
            
            $sql = "UPDATE tickets SET estado = 'en_progreso', asignado_a = ?, tecnico_asignado = ?, fecha_asignacion = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$tecnico_id, $tecnico['nombre_completo'], $ticket_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Ticket asignado a ' . $tecnico['nombre_completo']]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al asignar']);
            }
            break;
            
        case 'resolver':
            if ($es_tecnico != 1) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso para resolver']);
                break;
            }
            
            $ticket_id = $_POST['ticket_id'] ?? 0;
            $comentarios = trim($_POST['comentarios'] ?? '');
            
            if ($ticket_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                break;
            }
            
            // Verificar que el ticket esté asignado a este técnico
            $ticket = $db->obtenerTickets();
            $ticket = array_filter($ticket, function($t) use ($ticket_id, $usuario_id) {
                return $t['id'] == $ticket_id && $t['asignado_a'] == $usuario_id;
            });
            $ticket = reset($ticket);
            
            if (!$ticket) {
                echo json_encode(['success' => false, 'error' => 'Ticket no encontrado o no te pertenece']);
                break;
            }
            
            $sql = "UPDATE tickets SET estado = 'resuelto', resuelto_por = ?, fecha_resolucion = NOW(), comentarios_tecnicos = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$usuario_id, $comentarios, $ticket_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Ticket resuelto exitosamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al resolver']);
            }
            break;
            
        case 'eliminar':
            if (!$esSuperAdmin) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso para eliminar']);
                break;
            }
            
            $ticket_id = $_POST['ticket_id'] ?? 0;
            if ($ticket_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                break;
            }
            
            $stmt = $db->prepare("DELETE FROM tickets WHERE id = ?");
            $result = $stmt->execute([$ticket_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Ticket eliminado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al eliminar']);
            }
            break;
            
        case 'cambiar_rol':
            if (!$esSuperAdmin) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                break;
            }
            
            $usuario_id_cambiar = $_POST['usuario_id'] ?? 0;
            $nuevo_rol = $_POST['rol'] ?? 0;
            
            if ($usuario_id_cambiar <= 0 || !in_array($nuevo_rol, [1, 2, 3])) {
                echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
                break;
            }
            
            // No permitir cambiar el SuperAdmin
            $usuario = $db->obtenerUsuarioPorId($usuario_id_cambiar);
            if ($usuario['pc_identificador'] == 'u2274-PC-254') {
                echo json_encode(['success' => false, 'error' => 'No se puede cambiar el SuperAdmin']);
                break;
            }
            
            $sql = "UPDATE usuarios SET rol = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$nuevo_rol, $usuario_id_cambiar]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Rol actualizado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
            }
            break;
            
        case 'cambiar_departamento':
            if (!$esSuperAdmin) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                break;
            }
            
            $usuario_id_cambiar = $_POST['usuario_id'] ?? 0;
            $nuevo_departamento = $_POST['departamento_id'] ?? 0;
            
            if ($usuario_id_cambiar <= 0 || $nuevo_departamento <= 0) {
                echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
                break;
            }
            
            // Verificar departamento
            $departamento = $db->obtenerDepartamentoPorId($nuevo_departamento);
            if (!$departamento) {
                echo json_encode(['success' => false, 'error' => 'Departamento no existe']);
                break;
            }
            
            $sql = "UPDATE usuarios SET departamento_id = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$nuevo_departamento, $usuario_id_cambiar]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Departamento actualizado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
            }
            break;
            
        case 'toggle_tecnico':
            if (!$esSuperAdmin) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                break;
            }
            
            $usuario_id_cambiar = $_POST['usuario_id'] ?? 0;
            if ($usuario_id_cambiar <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                break;
            }
            
            // No permitir cambiar el SuperAdmin
            $usuario = $db->obtenerUsuarioPorId($usuario_id_cambiar);
            if ($usuario['pc_identificador'] == 'u2274-PC-254') {
                echo json_encode(['success' => false, 'error' => 'No se puede cambiar el SuperAdmin']);
                break;
            }
            
            $nuevo_valor = $usuario['es_tecnico'] == 1 ? 0 : 1;
            $sql = "UPDATE usuarios SET es_tecnico = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$nuevo_valor, $usuario_id_cambiar]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Estado técnico actualizado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
            }
            break;
            
        case 'toggle_usuario':
            if (!$esSuperAdmin) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                break;
            }
            
            $usuario_id_cambiar = $_POST['usuario_id'] ?? 0;
            if ($usuario_id_cambiar <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                break;
            }
            
            // No permitir desactivar el SuperAdmin
            $usuario = $db->obtenerUsuarioPorId($usuario_id_cambiar);
            if ($usuario['pc_identificador'] == 'u2274-PC-254') {
                echo json_encode(['success' => false, 'error' => 'No se puede desactivar el SuperAdmin']);
                break;
            }
            
            $nuevo_estado = $usuario['activo'] == 1 ? 0 : 1;
            
            // Si se desactiva un encargado, cambiar a rol 3
            if ($nuevo_estado == 0 && $usuario['rol'] == 2) {
                $sql = "UPDATE usuarios SET activo = ?, rol = 3 WHERE id = ?";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$nuevo_estado, $usuario_id_cambiar]);
            } else {
                $sql = "UPDATE usuarios SET activo = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$nuevo_estado, $usuario_id_cambiar]);
            }
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Estado del usuario actualizado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
            }
            break;
            
        case 'crear_departamento':
            if (!$esSuperAdmin) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                break;
            }
            
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            
            if (empty($nombre)) {
                echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio']);
                break;
            }
            
            $sql = "INSERT INTO departamentos (nombre, descripcion) VALUES (?, ?)";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$nombre, $descripcion]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Departamento creado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al crear departamento']);
            }
            break;
            
        case 'editar_departamento':
            if (!$esSuperAdmin) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                break;
            }
            
            $departamento_id = $_POST['departamento_id'] ?? 0;
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            
            if ($departamento_id <= 0 || empty($nombre)) {
                echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
                break;
            }
            
            $sql = "UPDATE departamentos SET nombre = ?, descripcion = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$nombre, $descripcion, $departamento_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Departamento actualizado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
            }
            break;
            
        case 'eliminar_departamento':
            if (!$esSuperAdmin) {
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                break;
            }
            
            $departamento_id = $_POST['departamento_id'] ?? 0;
            if ($departamento_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                break;
            }
            
            // Verificar que no tenga usuarios
            $usuarios = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE departamento_id = ?", [$departamento_id])->fetch();
            if ($usuarios['total'] > 0) {
                echo json_encode(['success' => false, 'error' => 'No se puede eliminar: el departamento tiene usuarios asignados']);
                break;
            }
            
            $sql = "DELETE FROM departamentos WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$departamento_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Departamento eliminado']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al eliminar']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
