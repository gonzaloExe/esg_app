<?php
// ============================================================
// SISTEMA DE AUTENTICACIÓN Y REGISTRO AUTOMÁTICO
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstancia();
    }
    
    // Obtiene o crea usuario por PC
    public function autenticarPorPC() {
        $pc = $this->getPCIdentificador();
        $usuario = $this->getCurrentUser();
        
        if (empty($pc)) {
            return false;
        }
        
        // Buscar usuario existente
        $usuarioExistente = $this->db->obtenerUsuarioPorPC($pc);
        
        if ($usuarioExistente) {
            // Usuario existe - actualizar sesión
            $this->iniciarSesion($usuarioExistente);
            
            // Verificar si está activo
            if ($usuarioExistente['activo'] != 1) {
                $_SESSION['mensaje_error'] = 'Usuario desactivado. Contacte al administrador.';
                return false;
            }
            
            return true;
        } else {
            // Registrar nuevo usuario
            return $this->registrarNuevoUsuario($pc, $usuario);
        }
    }
    
    // Registra un nuevo usuario con rol = 3 (usuario normal)
    private function registrarNuevoUsuario($pc, $nombre_usuario) {
        try {
            // Obtener departamento por defecto
            $departamento_id = $this->db->obtenerConfiguracion('departamento_por_defecto');
            if (!$departamento_id) {
                $departamento_id = 1; // Apoyo por defecto
            }
            
            $sql = "INSERT INTO usuarios (pc_identificador, nombre_usuario, nombre_completo, rol, departamento_id, activo) 
                    VALUES (?, ?, ?, 3, ?, 1)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$pc, $nombre_usuario, $nombre_usuario, $departamento_id]);
            
            $usuario_id = $this->db->lastInsertId();
            $usuario = $this->db->obtenerUsuarioPorId($usuario_id);
            
            $this->iniciarSesion($usuario);
            
            $_SESSION['mensaje_exito'] = 'Usuario registrado automáticamente. Bienvenido!';
            return true;
            
        } catch (PDOException $e) {
            error_log("Error al registrar usuario: " . $e->getMessage());
            return false;
        }
    }
    
    // Inicia sesión para el usuario
    private function iniciarSesion($usuario) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['pc_identificador'] = $usuario['pc_identificador'];
        $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
        $_SESSION['rol'] = $usuario['rol'];
        $_SESSION['departamento_id'] = $usuario['departamento_id'];
        $_SESSION['es_tecnico'] = $usuario['es_tecnico'];
        $_SESSION['activo'] = $usuario['activo'];
        
        // Obtener nombre del departamento
        if ($usuario['departamento_id']) {
            $departamento = $this->db->obtenerDepartamentoPorId($usuario['departamento_id']);
            $_SESSION['departamento_nombre'] = $departamento ? $departamento['nombre'] : 'Sin asignar';
        } else {
            $_SESSION['departamento_nombre'] = 'Sin asignar';
        }
    }
    
    // Obtiene el identificador de la PC
    private function getPCIdentificador() {
        return gethostname();
    }
    
    // Obtiene el nombre del usuario actual
    private function getCurrentUser() {
        return get_current_user();
    }
    
    // Verifica si es SuperAdmin (u2274-PC-254)
    public function esSuperAdminUnico($pc) {
        return $pc === 'u2274-PC-254';
    }
    
    // Verifica si el usuario actual es SuperAdmin
    public function usuarioActualEsSuperAdmin() {
        return isset($_SESSION['rol']) && $_SESSION['rol'] == 1;
    }
    
    // Verifica si el usuario actual es Encargado
    public function usuarioActualEsEncargado() {
        return isset($_SESSION['rol']) && $_SESSION['rol'] == 2;
    }
    
    // Verifica si el usuario actual es Técnico TI
    public function usuarioActualEsTecnico() {
        return isset($_SESSION['es_tecnico']) && $_SESSION['es_tecnico'] == 1;
    }
    
    // Logout
    public function logout() {
        $_SESSION = [];
        session_destroy();
        return true;
    }
}
?>
