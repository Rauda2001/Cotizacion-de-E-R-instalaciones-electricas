-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 31-10-2026 a las 20:18:56
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `er_instalaciones`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `correo` varchar(190) DEFAULT NULL,
  `direccion` varchar(500) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `telefono`, `correo`, `direccion`, `notas`, `creado_en`, `actualizado_en`) VALUES
(1, 'Jose Ruiz', '7345932', NULL, 'Casa arriba', 'acometida', '2026-10-24 21:58:54', '2026-10-24 21:58:54'),
(2, 'German Alexis', NULL, NULL, 'Sonsonate', NULL, '2026-10-30 21:48:40', '2026-10-30 21:48:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizaciones`
--

CREATE TABLE `cotizaciones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `folio` varchar(40) NOT NULL,
  `cliente_id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `vigencia_hasta` date DEFAULT NULL,
  `estado` enum('borrador','enviada','aceptada','rechazada','vencida') NOT NULL DEFAULT 'borrador',
  `notas` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Contador anual para generar folios correlativos sin reutilizar números.
--

CREATE TABLE `cotizacion_folio_secuencias` (
  `anio` smallint(5) UNSIGNED NOT NULL,
  `ultimo_correlativo` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`anio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cotizaciones`
--

INSERT INTO `cotizaciones` (`id`, `folio`, `cliente_id`, `usuario_id`, `fecha`, `vigencia_hasta`, `estado`, `notas`, `creado_en`, `actualizado_en`) VALUES
(3, 'ER-20261024-1647A2A7', 1, 3, '2026-10-24', '2026-11-23', 'borrador', 'Hasta agotar existencia', '2026-10-24 22:33:26', '2026-10-24 22:33:26'),
(4, 'ER-20261024-E4A79BB1', 1, 3, '2026-10-24', '2026-11-23', 'borrador', 'Hasta agotar existencia', '2026-10-24 22:38:06', '2026-10-24 22:38:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizacion_materiales`
--

CREATE TABLE `cotizacion_materiales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cotizacion_id` bigint(20) UNSIGNED NOT NULL,
  `material_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nombre_snapshot` varchar(160) NOT NULL,
  `descripcion_snapshot` text DEFAULT NULL,
  `unidad_snapshot` varchar(40) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `precio_unitario_snapshot` decimal(12,2) NOT NULL
) ;

--
-- Volcado de datos para la tabla `cotizacion_materiales`
--

INSERT INTO `cotizacion_materiales` (`id`, `cotizacion_id`, `material_id`, `nombre_snapshot`, `descripcion_snapshot`, `unidad_snapshot`, `cantidad`, `precio_unitario_snapshot`) VALUES
(3, 3, 45, 'POSTE GALVANIZADO DE 4\" X 6 METROS', 'Poste metálico galvanizado para acometida', 'CU', 2.000, 42.00),
(4, 3, 46, 'TAPON HEMBRA PVC 4\"', 'Tapón hembra de PVC de 4 pulgadas', 'CU', 2.000, 1.60),
(5, 3, 51, 'CLEVIS', 'Clevis secundario para líneas eléctricas', 'CU', 1.000, 2.85),
(6, 4, 22, 'CABLE THHN # 12 BLANCO', 'Cable de cobre flexible calibre 12', 'MTS', 100.000, 0.74),
(7, 4, 30, 'TECNODUCTO 1/2\"', 'Tubería o ducto flexible/rígido (Venta mínima 10 metros)', 'MTS', 50.000, 0.52);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizacion_servicios`
--

CREATE TABLE `cotizacion_servicios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cotizacion_id` bigint(20) UNSIGNED NOT NULL,
  `servicio_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nombre_snapshot` varchar(160) NOT NULL,
  `descripcion_snapshot` text DEFAULT NULL,
  `unidad_snapshot` varchar(40) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `precio_unitario_snapshot` decimal(12,2) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales`
--

CREATE TABLE `materiales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `unidad` varchar(40) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `materiales`
--

INSERT INTO `materiales` (`id`, `nombre`, `descripcion`, `unidad`, `precio_unitario`, `activo`, `creado_en`, `actualizado_en`) VALUES
(18, 'CABLE THHN #14 NEGRO', 'Cable de cobre flexible calibre 14', 'MTS', 0.47, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(19, 'CABLE THHN #14 BLANCO', 'Cable de cobre flexible calibre 14', 'MTS', 0.47, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(20, 'CABLE THHN # 14 AMARILLO', 'Cable de cobre flexible calibre 14', 'MTS', 0.47, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(21, 'CABLE THHN # 12 NEGRO', 'Cable de cobre flexible calibre 12', 'MTS', 0.74, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(22, 'CABLE THHN # 12 BLANCO', 'Cable de cobre flexible calibre 12', 'MTS', 0.74, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(23, 'CABLE THHN # 12 VERDE', 'Cable de cobre flexible calibre 12', 'MTS', 0.74, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(24, 'CAJA CUADRADA PLASTICA', 'Caja de registro plástica cuadrada', 'CU', 1.00, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(25, 'CAJAS OCTAGONALES', 'Caja octagonal para centros de luz', 'CU', 0.70, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(26, 'RECEPTACULOS', 'Receptáculo o boquilla para bombilla', 'CU', 0.95, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(27, 'TAPADERA CUADRADA', 'Tapadera para caja cuadrada', 'CU', 0.55, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(28, 'SWITCH SENCILLO', 'Interruptor de pared sencillo', 'CU', 2.20, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(29, 'SWITCH SENCILLO TRIPLE', 'Interruptor triple de pared', 'CU', 3.75, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(30, 'TECNODUCTO 1/2\"', 'Tubería o ducto flexible/rígido (Venta mínima 10 metros)', 'MTS', 0.52, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(31, 'CAJAS RECTANGULARES', 'Caja rectangular 2x4 para interruptores y enchufes', 'CU', 0.70, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(32, 'TOMAS DOBLES POLARIZADOS EAGLE', 'Tomacorriente dúplex polarizado', 'CU', 2.60, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(33, 'CAJA TERMICA DE 2 CIRCUITOS', 'Centro de carga o caja térmica para 2 breakers', 'CU', 9.00, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(34, 'TECNODUCTO DE 1\"', 'Tubería o ducto (Venta mínima 10 metros)', 'MTS', 0.75, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(35, 'CABLE THHN # 8 BLANCO', 'Cable de cobre calibre 8', 'MTS', 2.00, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(36, 'CABLE THHN # 8 NEGRO', 'Cable de cobre calibre 8', 'MTS', 2.10, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(37, 'CABLE THHN # 8 VERDE', 'Cable de cobre calibre 8', 'MTS', 2.00, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(38, 'CUERPO TERMINAL DE 1\"', 'Cuerpo terminal para tubo de 1 pulgada', 'CU', 3.60, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(39, 'CEPO PARA BARRA COPER', 'Cepo o conector para barra de cobre de puesta a tierra', 'CU', 1.35, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(40, 'BARRA COPER', 'Barra Copperweld / puesta a tierra', 'FT', 1.38, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(41, 'ABRAZADERA TIPO UÑA DE 1\"', 'Abrazadera de uña para fijación de tubo 1\"', 'CU', 0.20, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(42, 'ABRAZADERA TIPO UÑA DE 1/2\"', 'Abrazadera de uña para fijación de tubo 1/2\"', 'CU', 0.10, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(43, 'TERMICO DE 15A 1 POLO', 'Interruptor termomagnético (breaker) 15A 1P', 'CU', 8.00, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(44, 'TERMICO DE 20A 1 POLO', 'Interruptor termomagnético (breaker) 20A 1P', 'CU', 8.10, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(45, 'POSTE GALVANIZADO DE 4\" X 6 METROS', 'Poste metálico galvanizado para acometida', 'CU', 42.00, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(46, 'TAPON HEMBRA PVC 4\"', 'Tapón hembra de PVC de 4 pulgadas', 'CU', 1.60, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(47, 'CABLE DUPLEX # 6', 'Cable dúplex calibre 6', 'MTS', 1.60, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(48, 'PREFORMADA PARA DUBLEX 6', 'Retenida o preformada para cable dúplex #6', 'CU', 1.80, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(49, 'ABRAZADERA DE 3-5 GALVANIZADA', 'Abrazadera galvanizada ajustable', 'PAR', 3.05, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(50, 'PERNO 1/2X4-1/2 GALVANIZADOS', 'Perno de acero galvanizado con tuerca', 'CU', 0.68, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(51, 'CLEVIS', 'Clevis secundario para líneas eléctricas', 'CU', 2.85, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(52, 'AISLADOR DE CARRETE', 'Aislador porcelana/plástico de carrete', 'CU', 0.90, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(53, 'COMPRENSADOS # 2 LAINA GRUESA', 'Conector de compresión calibre 2', 'CU', 2.58, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12'),
(54, 'COMPRENSADOS # 6', 'Conector de compresión calibre 6', 'CU', 1.60, 1, '2026-10-24 22:08:12', '2026-10-24 22:08:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `unidad` varchar(40) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre_usuario` varchar(50) NOT NULL,
  `correo` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('admin','operador') NOT NULL DEFAULT 'admin',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre_usuario`, `correo`, `password_hash`, `rol`, `activo`, `creado_en`) VALUES
(3, 'admin', 'admin@erinstalaciones.local', '$2y$10$8JDX/z59Hd3MSNr/Pw/Nrexsx/D6uUVDpJr08UxexbB5afNnpHKQ.', 'admin', 1, '2026-10-24 21:22:01');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clientes_nombre` (`nombre`),
  ADD KEY `idx_clientes_correo` (`correo`);

--
-- Indices de la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cotizaciones_folio` (`folio`),
  ADD KEY `idx_cotizaciones_fecha` (`fecha`),
  ADD KEY `idx_cotizaciones_estado` (`estado`),
  ADD KEY `fk_cotizaciones_cliente` (`cliente_id`),
  ADD KEY `fk_cotizaciones_usuario` (`usuario_id`);

--
-- Indices de la tabla `cotizacion_materiales`
--
ALTER TABLE `cotizacion_materiales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cotizacion_materiales_cotizacion` (`cotizacion_id`),
  ADD KEY `fk_cotizacion_materiales_material` (`material_id`);

--
-- Indices de la tabla `cotizacion_servicios`
--
ALTER TABLE `cotizacion_servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cotizacion_servicios_cotizacion` (`cotizacion_id`),
  ADD KEY `fk_cotizacion_servicios_servicio` (`servicio_id`);

--
-- Indices de la tabla `materiales`
--
ALTER TABLE `materiales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_materiales_nombre` (`nombre`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_servicios_nombre` (`nombre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_nombre` (`nombre_usuario`),
  ADD UNIQUE KEY `uq_usuarios_correo` (`correo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `cotizacion_materiales`
--
ALTER TABLE `cotizacion_materiales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cotizacion_servicios`
--
ALTER TABLE `cotizacion_servicios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materiales`
--
ALTER TABLE `materiales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD CONSTRAINT `fk_cotizaciones_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cotizaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `cotizacion_materiales`
--
ALTER TABLE `cotizacion_materiales`
  ADD CONSTRAINT `fk_cotizacion_materiales_cotizacion` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cotizacion_materiales_material` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `cotizacion_servicios`
--
ALTER TABLE `cotizacion_servicios`
  ADD CONSTRAINT `fk_cotizacion_servicios_cotizacion` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cotizacion_servicios_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
