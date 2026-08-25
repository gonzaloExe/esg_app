-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 25-08-2026 a las 14:53:49
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `esg_sistema`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `clave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
('departamento_por_defecto', '1', 'ID del departamento para usuarios nuevos'),
('tiempo_maximo_resolucion', '48', 'Tiempo máximo en horas para resolver tickets'),
('version_sistema', '1.0.0', 'Versión actual del sistema');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departamentos`
--

CREATE TABLE `departamentos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `departamentos`
--

INSERT INTO `departamentos` (`id`, `nombre`, `descripcion`, `activo`, `fecha_creacion`) VALUES
(1, 'Apoyo', 'Departamento de Apoyo y Soporte Técnico', 1, '2026-08-25 09:26:10'),
(2, 'Investigación', 'Departamento de Investigación y Desarrollo', 1, '2026-08-25 09:26:10'),
(3, 'Ventas', 'Departamento de Ventas y Comercialización', 1, '2026-08-25 09:26:10'),
(4, 'Marketing', 'Departamento de Marketing y Publicidad', 1, '2026-08-25 09:26:10'),
(5, 'Recursos Humanos', 'Departamento de Recursos Humanos', 1, '2026-08-25 09:26:10'),
(6, 'Finanzas', 'Departamento de Finanzas y Contabilidad', 1, '2026-08-25 09:26:10'),
(7, 'Logística', 'Departamento de Logística y Distribución', 1, '2026-08-25 09:26:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `pc_origen` varchar(100) NOT NULL,
  `usuario_origen` varchar(100) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `departamento_id` int(11) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `estado` enum('pendiente_aprobacion','aprobado','rechazado','en_progreso','resuelto') DEFAULT 'pendiente_aprobacion',
  `auto_aprobado` tinyint(1) DEFAULT 0,
  `aprobado_por` int(11) DEFAULT NULL,
  `fecha_aprobacion` datetime DEFAULT NULL,
  `motivo_rechazo` text DEFAULT NULL,
  `asignado_a` int(11) DEFAULT NULL,
  `tecnico_asignado` varchar(100) DEFAULT NULL,
  `fecha_asignacion` datetime DEFAULT NULL,
  `resuelto_por` int(11) DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL,
  `comentarios_tecnicos` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `pc_identificador` varchar(100) NOT NULL,
  `nombre_usuario` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `nombre_completo` varchar(100) DEFAULT NULL,
  `rol` tinyint(4) DEFAULT 3 COMMENT '1=SuperAdmin, 2=Encargado, 3=Usuario',
  `departamento_id` int(11) DEFAULT NULL,
  `es_tecnico` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_desactivacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `pc_identificador`, `nombre_usuario`, `password`, `nombre_completo`, `rol`, `departamento_id`, `es_tecnico`, `activo`, `fecha_creacion`, `fecha_desactivacion`) VALUES
(2, 'u2274-CP-454', 'gonzcardozo', '$2y$10$FGlFNLU/YZnG.1jX3fGy7.n2l44dHar6wSm2m/fS1hRTd.qCD7/qG', 'gonzcardozo', 1, 1, 0, 1, '2026-08-25 09:26:14', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`clave`);

--
-- Indices de la tabla `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `idx_nombre` (`nombre`);

--
-- Indices de la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_departamento` (`departamento_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_pc_origen` (`pc_origen`),
  ADD KEY `aprobado_por` (`aprobado_por`),
  ADD KEY `asignado_a` (`asignado_a`),
  ADD KEY `resuelto_por` (`resuelto_por`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pc_identificador` (`pc_identificador`),
  ADD KEY `idx_rol` (`rol`),
  ADD KEY `idx_departamento` (`departamento_id`),
  ADD KEY `idx_activo` (`activo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`),
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`asignado_a`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `tickets_ibfk_5` FOREIGN KEY (`resuelto_por`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
