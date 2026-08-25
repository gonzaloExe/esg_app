# ESG - Entorno Seguro y Gestión

Sistema de gestión de tickets de incidencias con flujo de aprobación jerárquico de 3 niveles.

## 🚀 Características Principales

- **Identificación única por PC** - No requiere correos electrónicos
- **Registro automático** - Los usuarios se registran al primer uso
- **Sistema de roles numéricos** (1=SuperAdmin, 2=Encargado, 3=Usuario)
- **Departamentos con ID numérico** - Creación y asignación flexible
- **Flujo de aprobación de 3 niveles**
- **Técnicos TI** - Capacidad para resolver tickets de todos los departamentos
- **Modo Offline** - Guardado local y sincronización automática
- **Panel de administración unificado** según rol del usuario

## 📋 Requisitos del Servidor

- PHP 8.0 o superior
- MySQL 5.7 o superior
- Apache o Nginx
- Extensiones PHP: PDO, MySQLi, GD, Fileinfo

## 🔧 Instalación

### 1. Clonar o descargar los archivos

Colocar todos los archivos en el directorio web (ej: `/var/www/html/esg-app/`)

### 2. Configurar la base de datos

Editar el archivo `includes/config.php` con tus credenciales:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'esg_sistema');
define('DB_USER', 'root');
define('DB_PASS', '');
3. Ejecutar el instalador
Acceder a: http://tu-servidor/esg-app/instalar.php

El instalador creará automáticamente:

Base de datos

Todas las tablas

Datos iniciales

SuperAdmin (u2274-PC-254 / gonza123)

Departamentos iniciales

4. Verificar la instalación
Acceder a: http://tu-servidor/esg-app/verificar_db.php

5. Crear carpeta de uploads (si no se creó automáticamente)
bash
mkdir uploads
chmod 777 uploads
👤 Credenciales por Defecto
SuperAdmin (Único)
PC: u2274-PC-254

Usuario: admin

Contraseña: gonza123

⚠️ IMPORTANTE: Cambiar la contraseña del SuperAdmin después de la primera instalación.

📊 Sistema de Roles
Rol	Número	Descripción
SuperAdmin	1	Administrador único del sistema (u2274-PC-254)
Encargado	2	Jefe de departamento - Aprueba tickets de su departamento
Usuario	3	Usuario normal - Envía tickets
Reglas de Roles
TODOS los usuarios empiezan con rol = 3

El SuperAdmin es el ÚNICO que puede cambiar roles

Los encargados reciben tickets de su departamento

Los usuarios solo envían tickets

🏢 Sistema de Departamentos
ID numérico autoincrementable (1, 2, 3, ...)

SuperAdmin crea departamentos con ID automático

Ejemplos:

ID 1 = Apoyo

ID 2 = Investigación

ID 3 = Ventas

ID 4 = Marketing

Asignación de Departamentos
SuperAdmin asigna a cada usuario un departamento

Encargados vinculados a un departamento específico

Encargado SOLO ve tickets de usuarios de su mismo departamento

Usuarios sin departamento: asignación automática a ID 1

🔧 Técnicos TI
Cualquier usuario (rol 2 o 3) puede ser técnico TI

Flag: es_tecnico = 1

Técnicos TI pueden ver y resolver tickets de TODOS los departamentos

SuperAdmin activa/desactiva esta capacidad

📝 Flujo de Trabajo
NIVEL 1 - Usuario Normal (rol = 3)
Envía ticket con: Título, Descripción (mínimo 10 caracteres), Foto (opcional)

Ticket guardado con estado "pendiente_aprobacion"

Puede ver sus propios tickets

NIVEL 2 - Encargado (rol = 2)
Sus tickets se auto-aprueban automáticamente

En admin.php ve SOLO tickets de su departamento

Puede APROBAR o RECHAZAR (con motivo) tickets de su departamento

NIVEL 3 - Técnico TI (es_tecnico = 1)
En admin.php ve TODOS los tickets aprobados

Puede ASIGNAR a técnico específico

Puede RESOLVER tickets

SUPERADMIN (rol = 1)
NO ve formulario en index.php

Administra todo el sistema

Crea departamentos

Asigna usuarios a departamentos

Cambia roles

Marca/desmarca técnicos TI

Elimina cualquier ticket

📁 Estructura de Archivos
text
/esg-app/
├── /assets/
│   ├── /css/
│   │   └── estilo.css
│   └── /js/
│       ├── app.js
│       ├── admin.js
│       └── offline.js
├── /includes/
│   ├── config.php
│   ├── auth.php
│   └── Database.php
├── /uploads/
├── index.php
├── admin.php
├── login.php
├── logout.php
├── api.php
├── instalar.php
├── verificar_db.php
└── README.md
🔒 Seguridad
PDO con consultas preparadas - Protección SQL Injection

Contraseñas hasheadas con password_hash()

Validación de roles en cada acción

Filtrado por departamento para encargados

Sesiones seguras con configuración HTTP-only

📱 Diseño Responsive
Bootstrap 5 para diseño responsive

Google Fonts (Roboto)

Iconos FontAwesome

Tarjetas con sombras suaves

Botones mobile-friendly (mínimo 48px)

Adaptación a todos los dispositivos

🔌 API Endpoints
GET
action=obtener_tickets - Obtener tickets (filtrados por rol)

action=obtener_usuarios - Obtener usuarios (SuperAdmin)

action=obtener_departamentos - Obtener departamentos

action=obtener_estadisticas - Obtener estadísticas

action=obtener_ticket&id=X - Obtener detalle de ticket

action=mis_tickets - Obtener tickets del usuario actual

action=obtener_tecnicos - Obtener técnicos disponibles

POST
action=crear_ticket - Crear nuevo ticket

action=aprobar - Aprobar ticket (Encargado)

action=rechazar - Rechazar ticket (Encargado)

action=asignar - Asignar ticket a técnico

action=resolver - Resolver ticket

action=eliminar - Eliminar ticket (SuperAdmin)

action=cambiar_rol - Cambiar rol de usuario (SuperAdmin)

action=cambiar_departamento - Cambiar departamento (SuperAdmin)

action=toggle_tecnico - Marcar/Desmarcar técnico (SuperAdmin)

action=toggle_usuario - Activar/Desactivar usuario (SuperAdmin)

action=crear_departamento - Crear departamento (SuperAdmin)

action=editar_departamento - Editar departamento (SuperAdmin)

action=eliminar_departamento - Eliminar departamento (SuperAdmin)

🌐 Modo Offline
Detección automática de conexión

Guardado en localStorage

Sincronización automática al recuperar conexión

Indicador visual de estado

🐛 Solución de Problemas
Error de conexión a la base de datos
Verificar credenciales en includes/config.php

Verificar que MySQL esté corriendo

Crear la base de datos manualmente si es necesario

Error de permisos de uploads
bash
chmod 777 uploads
Problemas con el SuperAdmin
Ejecutar verificar_db.php para diagnóstico

Reinstalar con instalar.php si es necesario

📄 Licencia
Este sistema es de uso interno y confidencial.

👨‍💻 Soporte
Para soporte técnico, contactar al administrador del sistema.

Versión: 1.0.0
Fecha: 2024
Desarrollado por: ESG Team

text

## INSTRUCCIONES FINALES

1. **Crear la estructura de carpetas** como se muestra arriba
2. **Copiar todos los archivos** en sus respectivas ubicaciones
3. **Configurar la base de datos** en `includes/config.php`
4. **Ejecutar `instalar.php`** para crear la base de datos y datos iniciales
5. **Acceder a `index.php`** para comenzar a usar el sistema

El sistema está completamente funcional con todas las características solicitadas:
- ✅ Registro automático de usuarios
- ✅ Roles numéricos (1, 2, 3)
- ✅ Sistema de departamentos
- ✅ SuperAdmin único (u2274-PC-254 / gonza123)
- ✅ Auto-aprobación para encargados
- ✅ Técnicos TI (es_tecnico)
- ✅ Panel admin.php según rol
- ✅ API unificada
- ✅ Modo offline
- ✅ Diseño responsive
- ✅ Y todas las demás funcionalidades especificadas
# esg_app
