<?php
// ============================================================
// CONFIGURACIÓN DEL SISTEMA ESG
// ============================================================

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'esg_sistema');
define('DB_USER', 'esg_user');
define('DB_PASS', 'campos480');

// Configuración general
define('SITE_NAME', 'ESG - Entorno Seguro y Gestión');
define('SITE_URL', 'http://localhost/esg-app/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS

// Configuración de zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función para debug (eliminar en producción)
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

// Función para redireccionar
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Función para obtener el nombre del rol
function getRolNombre($rol) {
    $roles = [
        1 => 'SuperAdmin',
        2 => 'Encargado',
        3 => 'Usuario'
    ];
    return isset($roles[$rol]) ? $roles[$rol] : 'Desconocido';
}

// Función para obtener el color del rol
function getRolColor($rol) {
    $colores = [
        1 => 'danger',
        2 => 'success',
        3 => 'secondary'
    ];
    return isset($colores[$rol]) ? $colores[$rol] : 'secondary';
}

// Función para obtener el ícono del rol
function getRolIcono($rol) {
    $iconos = [
        1 => 'fa-shield-alt',
        2 => 'fa-crown',
        3 => 'fa-user'
    ];
    return isset($iconos[$rol]) ? $iconos[$rol] : 'fa-user';
}

// Función para limpiar strings
function limpiarString($string) {
    return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
}

// Función para validar archivos
function validarArchivo($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return false;
    }
    
    return true;
}

// Función para subir archivo
function subirArchivo($file) {
    if (!validarArchivo($file)) {
        return false;
    }
    
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    
    $nombreArchivo = uniqid() . '_' . basename($file['name']);
    $rutaCompleta = UPLOAD_DIR . $nombreArchivo;
    
    if (move_uploaded_file($file['tmp_name'], $rutaCompleta)) {
        return $nombreArchivo;
    }
    
    return false;
}

// Función para verificar si el usuario está autenticado
function estaAutenticado() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['pc_identificador']);
}

// Función para verificar si es SuperAdmin
function esSuperAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] == 1;
}

// Función para verificar si es Encargado
function esEncargado() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] == 2;
}

// Función para verificar si es Usuario
function esUsuario() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] == 3;
}

// Función para verificar si es Técnico TI
function esTecnico() {
    return isset($_SESSION['es_tecnico']) && $_SESSION['es_tecnico'] == 1;
}

// Función para verificar si está activo
function estaActivo() {
    return isset($_SESSION['activo']) && $_SESSION['activo'] == 1;
}

// Función para verificar permisos de acceso al admin
function tieneAccesoAdmin() {
    if (!estaAutenticado()) return false;
    if (!estaActivo()) return false;
    return esSuperAdmin() || esEncargado() || esTecnico();
}
?>
