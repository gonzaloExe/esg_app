<?php
// ============================================================
// VERIFICADOR DE BASE DE DATOS
// ============================================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';

echo "<h1>Verificación del Sistema ESG</h1>";
echo "<hr>";

try {
    $db = Database::getInstancia();
    
    // Verificar conexión
    echo "<h2>✅ Conexión a base de datos exitosa</h2>";
    echo "<hr>";
    
    // Verificar tablas
    $tablas = ['usuarios', 'departamentos', 'tickets', 'configuracion'];
    echo "<h2>Tablas:</h2>";
    foreach ($tablas as $tabla) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '$tabla'");
            if ($stmt->rowCount() > 0) {
                echo "<p>✅ Tabla <strong>$tabla</strong> existe</p>";
            } else {
                echo "<p>❌ Tabla <strong>$tabla</strong> NO existe</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ Error verificando tabla $tabla: " . $e->getMessage() . "</p>";
        }
    }
    echo "<hr>";
    
    // Usuarios registrados
    echo "<h2>Usuarios registrados:</h2>";
    $usuarios = $db->query("
        SELECT u.*, d.nombre as departamento_nombre 
        FROM usuarios u 
        LEFT JOIN departamentos d ON u.departamento_id = d.id 
        ORDER BY u.id
    ")->fetchAll();
    
    if (count($usuarios) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>PC</th>";
        echo "<th>Usuario</th>";
        echo "<th>Rol</th>";
        echo "<th>Departamento</th>";
        echo "<th>Técnico</th>";
        echo "<th>Activo</th>";
        echo "</tr>";
        foreach ($usuarios as $u) {
            $rol_nombre = getRolNombre($u['rol']);
            $rol_color = getRolColor($u['rol']);
            echo "<tr>";
            echo "<td>{$u['id']}</td>";
            echo "<td>{$u['pc_identificador']}</td>";
            echo "<td>{$u['nombre_usuario']}</td>";
            echo "<td style='color: " . ($rol_color == 'danger' ? 'red' : ($rol_color == 'success' ? 'green' : 'gray')) . ";'><strong>$rol_nombre</strong></td>";
            echo "<td>" . ($u['departamento_nombre'] ?? 'Sin asignar') . "</td>";
            echo "<td>" . ($u['es_tecnico'] ? '✅ Sí' : '❌ No') . "</td>";
            echo "<td>" . ($u['activo'] ? '✅ Activo' : '❌ Inactivo') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ No hay usuarios registrados</p>";
    }
    echo "<hr>";
    
    // Departamentos
    echo "<h2>Departamentos:</h2>";
    $departamentos = $db->obtenerDepartamentos();
    if (count($departamentos) > 0) {
        foreach ($departamentos as $d) {
            // Contar usuarios en este departamento
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM usuarios WHERE departamento_id = ? AND activo = 1");
            $stmt->execute([$d['id']]);
            $total = $stmt->fetch()['total'];
            
            // Contar encargados
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM usuarios WHERE departamento_id = ? AND rol = 2 AND activo = 1");
            $stmt->execute([$d['id']]);
            $encargados = $stmt->fetch()['total'];
            
            echo "<p>📁 <strong>{$d['nombre']}</strong> (ID: {$d['id']}) - Usuarios: $total - Encargados: $encargados</p>";
        }
    } else {
        echo "<p>❌ No hay departamentos</p>";
    }
    echo "<hr>";
    
    // Estadísticas generales
    echo "<h2>Estadísticas:</h2>";
    $stats = $db->obtenerEstadisticas();
    echo "<ul>";
    echo "<li>Total tickets: {$stats['total_tickets']}</li>";
    echo "<li>Total usuarios activos: {$stats['total_usuarios']}</li>";
    echo "<li>Total departamentos: {$stats['total_departamentos']}</li>";
    echo "<li>Por estado:";
    echo "<ul>";
    foreach ($stats['por_estado'] as $estado => $cantidad) {
        echo "<li>$estado: $cantidad</li>";
    }
    echo "</ul>";
    echo "</ul>";
    echo "<hr>";
    
    // Verificar SuperAdmin
    echo "<h2>SuperAdmin:</h2>";
    $sa = $db->query("SELECT * FROM usuarios WHERE pc_identificador = 'u2274-PC-254'")->fetch();
    if ($sa) {
        echo "<p>✅ SuperAdmin encontrado: <strong>{$sa['pc_identificador']}</strong> (Rol: {$sa['rol']})</p>";
        echo "<p>Contraseña hash: {$sa['password']}</p>";
    } else {
        echo "<p>❌ SuperAdmin NO encontrado. Ejecute instalar.php para crearlo.</p>";
    }
    echo "<hr>";
    
    echo "<p><a href='index.php'>Ir al sistema</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 5px;'>";
    echo "<h2 style='color: #721c24;'>❌ Error de conexión</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
