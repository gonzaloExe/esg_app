<?php
// ============================================================
// CLASE PARA MANEJO DE BASE DE DATOS CON PDO
// ============================================================

require_once __DIR__ . '/config.php';

class Database {
    private static $instancia = null;
    private $conexion;
    private $host;
    private $dbname;
    private $user;
    private $pass;
    
    private function __construct() {
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->user = DB_USER;
        $this->pass = DB_PASS;
        
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $this->conexion = new PDO($dsn, $this->user, $this->pass);
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    public static function getInstancia() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }
    
    public function getConexion() {
        return $this->conexion;
    }
    
    public function prepare($sql) {
        return $this->conexion->prepare($sql);
    }
    
    public function query($sql, $params = []) {
        if (empty($params)) {
            return $this->conexion->query($sql);
        } else {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        }
    }
    
    public function lastInsertId() {
        return $this->conexion->lastInsertId();
    }
    
    // Métodos para operaciones comunes
    
    public function obtenerUsuarioPorPC($pc) {
        $stmt = $this->prepare("SELECT * FROM usuarios WHERE pc_identificador = ?");
        $stmt->execute([$pc]);
        return $stmt->fetch();
    }
    
    public function obtenerUsuarioPorId($id) {
        $stmt = $this->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function obtenerDepartamentos() {
        $stmt = $this->query("SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre");
        return $stmt->fetchAll();
    }
    
    public function obtenerDepartamentoPorId($id) {
        $stmt = $this->prepare("SELECT * FROM departamentos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function obtenerConfiguracion($clave) {
        $stmt = $this->prepare("SELECT valor FROM configuracion WHERE clave = ?");
        $stmt->execute([$clave]);
        $resultado = $stmt->fetch();
        return $resultado ? $resultado['valor'] : null;
    }
    
    public function obtenerTickets($filtros = []) {
        $sql = "SELECT t.*, 
                u.nombre_completo as usuario_nombre,
                d.nombre as departamento_nombre,
                a.nombre_completo as aprobado_por_nombre,
                r.nombre_completo as resuelto_por_nombre
                FROM tickets t
                LEFT JOIN usuarios u ON t.usuario_id = u.id
                LEFT JOIN departamentos d ON t.departamento_id = d.id
                LEFT JOIN usuarios a ON t.aprobado_por = a.id
                LEFT JOIN usuarios r ON t.resuelto_por = r.id
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filtros['usuario_id'])) {
            $sql .= " AND t.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        
        if (isset($filtros['departamento_id'])) {
            $sql .= " AND t.departamento_id = ?";
            $params[] = $filtros['departamento_id'];
        }
        
        if (isset($filtros['estado'])) {
            $sql .= " AND t.estado = ?";
            $params[] = $filtros['estado'];
        }
        
        if (isset($filtros['tecnico_asignado'])) {
            $sql .= " AND t.asignado_a = ?";
            $params[] = $filtros['tecnico_asignado'];
        }
        
        $sql .= " ORDER BY t.fecha DESC";
        
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function obtenerTicketsPorDepartamento($departamento_id) {
        return $this->obtenerTickets(['departamento_id' => $departamento_id]);
    }
    
    public function obtenerTicketsPorEstado($estado) {
        return $this->obtenerTickets(['estado' => $estado]);
    }
    
    public function obtenerTicketsPorUsuario($usuario_id) {
        return $this->obtenerTickets(['usuario_id' => $usuario_id]);
    }
    
    public function obtenerTicketsPendientesAprobacion($departamento_id = null) {
        $params = ['pendiente_aprobacion'];
        $sql = "SELECT t.*, 
                u.nombre_completo as usuario_nombre,
                d.nombre as departamento_nombre
                FROM tickets t
                LEFT JOIN usuarios u ON t.usuario_id = u.id
                LEFT JOIN departamentos d ON t.departamento_id = d.id
                WHERE t.estado = ?";
        
        if ($departamento_id) {
            $sql .= " AND t.departamento_id = ?";
            $params[] = $departamento_id;
        }
        
        $sql .= " ORDER BY t.fecha ASC";
        
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function obtenerTicketsAprobadosSinAsignar() {
        $stmt = $this->prepare("
            SELECT t.*, 
            u.nombre_completo as usuario_nombre,
            d.nombre as departamento_nombre
            FROM tickets t
            LEFT JOIN usuarios u ON t.usuario_id = u.id
            LEFT JOIN departamentos d ON t.departamento_id = d.id
            WHERE t.estado = 'aprobado' AND t.asignado_a IS NULL
            ORDER BY t.fecha ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function obtenerEstadisticas() {
        $stats = [];
        
        // Total tickets
        $stmt = $this->query("SELECT COUNT(*) as total FROM tickets");
        $stats['total_tickets'] = $stmt->fetch()['total'];
        
        // Por estado
        $stmt = $this->query("
            SELECT estado, COUNT(*) as cantidad 
            FROM tickets 
            GROUP BY estado
        ");
        $stats['por_estado'] = [];
        while ($row = $stmt->fetch()) {
            $stats['por_estado'][$row['estado']] = $row['cantidad'];
        }
        
        // Total usuarios
        $stmt = $this->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
        $stats['total_usuarios'] = $stmt->fetch()['total'];
        
        // Total departamentos
        $stmt = $this->query("SELECT COUNT(*) as total FROM departamentos WHERE activo = 1");
        $stats['total_departamentos'] = $stmt->fetch()['total'];
        
        // Tickets por departamento
        $stmt = $this->query("
            SELECT d.nombre, COUNT(t.id) as cantidad 
            FROM departamentos d
            LEFT JOIN tickets t ON d.id = t.departamento_id
            WHERE d.activo = 1
            GROUP BY d.id
            ORDER BY cantidad DESC
        ");
        $stats['por_departamento'] = $stmt->fetchAll();
        
        return $stats;
    }
}
?>