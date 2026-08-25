<?php
// ============================================================
// INSTALADOR DEL SISTEMA ESG
// ============================================================

require_once __DIR__ . '/includes/config.php';

// Verificar si el sistema ya está instalado
if (file_exists(__DIR__ . '/.instalado')) {
    die('El sistema ya está instalado. Elimine el archivo .instalado para reinstalar.');
}

try {
    // Conectar a MySQL sin base de datos específica
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear base de datos
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
    
    echo "<h1>Instalación del Sistema ESG</h1>";
    echo "<p>Creando base de datos y tablas...</p>";
    
    // ============================================================
    // CREAR TABLAS
    // ============================================================
    
    $sql = "
    -- Tabla de usuarios
    CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pc_identificador VARCHAR(100) UNIQUE NOT NULL,
        nombre_usuario VARCHAR(100) NOT NULL,
        password VARCHAR(255) NULL,
        nombre_completo VARCHAR(100),
        rol TINYINT DEFAULT 3 COMMENT '1=SuperAdmin, 2=Encargado, 3=Usuario',
        departamento_id INT NULL,
        es_tecnico BOOLEAN DEFAULT FALSE,
        activo BOOLEAN DEFAULT TRUE,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_desactivacion DATETIME NULL,
        INDEX idx_rol (rol),
        INDEX idx_departamento (departamento_id),
        INDEX idx_activo (activo)
    );
    
    -- Tabla de departamentos
    CREATE TABLE IF NOT EXISTS departamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL UNIQUE,
        descripcion TEXT,
        activo BOOLEAN DEFAULT TRUE,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_nombre (nombre)
    );
    
    -- Tabla de tickets
    CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        descripcion TEXT NOT NULL,
        foto VARCHAR(255) DEFAULT NULL,
        pc_origen VARCHAR(100) NOT NULL,
        usuario_origen VARCHAR(100) NOT NULL,
        usuario_id INT NOT NULL,
        departamento_id INT NOT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('pendiente_aprobacion', 'aprobado', 'rechazado', 'en_progreso', 'resuelto') DEFAULT 'pendiente_aprobacion',
        auto_aprobado BOOLEAN DEFAULT FALSE,
        aprobado_por INT NULL,
        fecha_aprobacion DATETIME NULL,
        motivo_rechazo TEXT NULL,
        asignado_a INT NULL,
        tecnico_asignado VARCHAR(100) NULL,
        fecha_asignacion DATETIME NULL,
        resuelto_por INT NULL,
        fecha_resolucion DATETIME NULL,
        comentarios_tecnicos TEXT NULL,
        INDEX idx_estado (estado),
        INDEX idx_departamento (departamento_id),
        INDEX idx_usuario (usuario_id),
        INDEX idx_fecha (fecha),
        INDEX idx_pc_origen (pc_origen),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
        FOREIGN KEY (departamento_id) REFERENCES departamentos(id),
        FOREIGN KEY (aprobado_por) REFERENCES usuarios(id),
        FOREIGN KEY (asignado_a) REFERENCES usuarios(id),
        FOREIGN KEY (resuelto_por) REFERENCES usuarios(id)
    );
    
    -- Tabla de configuración
    CREATE TABLE IF NOT EXISTS configuracion (
        clave VARCHAR(100) PRIMARY KEY,
        valor TEXT,
        descripcion VARCHAR(255)
    );
    ";
    
    $pdo->exec($sql);
    echo "<p>✅ Tablas creadas correctamente</p>";
    
    // ============================================================
    // DATOS INICIALES
    // ============================================================
    
    echo "<p>Insertando datos iniciales...</p>";
    
    // Configuración
    $pdo->exec("
        INSERT INTO configuracion (clave, valor, descripcion) VALUES
        ('departamento_por_defecto', '1', 'ID del departamento para usuarios nuevos'),
        ('tiempo_maximo_resolucion', '48', 'Tiempo máximo en horas para resolver tickets'),
        ('version_sistema', '1.0.0', 'Versión actual del sistema')
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
    ");
    
    // SuperAdmin
    $password_hash = password_hash('gonza123', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO usuarios (pc_identificador, nombre_usuario, password, nombre_completo, rol, activo) VALUES
        ('u2274-PC-254', 'admin', '$password_hash', 'Super Administrador', 1, 1)
        ON DUPLICATE KEY UPDATE 
        nombre_usuario = VALUES(nombre_usuario),
        password = VALUES(password),
        nombre_completo = VALUES(nombre_completo),
        rol = VALUES(rol)
    ");
    
    // Departamentos
    $pdo->exec("
        INSERT INTO departamentos (id, nombre, descripcion) VALUES
        (1, 'Apoyo', 'Departamento de Apoyo y Soporte Técnico'),
        (2, 'Investigación', 'Departamento de Investigación y Desarrollo'),
        (3, 'Ventas', 'Departamento de Ventas y Comercialización'),
        (4, 'Marketing', 'Departamento de Marketing y Publicidad'),
        (5, 'Recursos Humanos', 'Departamento de Recursos Humanos'),
        (6, 'Finanzas', 'Departamento de Finanzas y Contabilidad'),
        (7, 'Logística', 'Departamento de Logística y Distribución')
        ON DUPLICATE KEY UPDATE 
        nombre = VALUES(nombre),
        descripcion = VALUES(descripcion)
    ");
    
    echo "<p>✅ Datos iniciales insertados</p>";
    
    // ============================================================
    // CREAR CARPETA UPLOADS
    // ============================================================
    
    $upload_dir = __DIR__ . '/uploads';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
        echo "<p>✅ Carpeta uploads creada</p>";
    } else {
        echo "<p>✅ Carpeta uploads ya existe</p>";
    }
    
    // ============================================================
    // CREAR ARCHIVO DE INSTALACIÓN
    // ============================================================
    
    file_put_contents(__DIR__ . '/.instalado', date('Y-m-d H:i:s'));
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h2 style='color: #155724;'>✅ Instalación completada con éxito</h2>";
    echo "<hr>";
    echo "<h3>Credenciales del SuperAdmin:</h3>";
    echo "<ul>";
    echo "<li><strong>PC:</strong> u2274-PC-254</li>";
    echo "<li><strong>Usuario:</strong> admin</li>";
    echo "<li><strong>Contraseña:</strong> gonza123</li>";
    echo "</ul>";
    echo "<p><strong>IMPORTANTE:</strong> Cambie la contraseña del SuperAdmin por seguridad.</p>";
    echo "<br>";
    echo "<a href='index.php' class='btn btn-primary'>Ir al Sistema</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 5px;'>";
    echo "<h2 style='color: #721c24;'>❌ Error de instalación</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
