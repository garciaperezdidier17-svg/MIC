-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: mic
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `acta_elementos`
--

DROP TABLE IF EXISTS `acta_elementos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acta_elementos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `acta_id` int(11) NOT NULL,
  `elemento_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acta_elemento` (`acta_id`,`elemento_id`),
  KEY `elemento_id` (`elemento_id`),
  CONSTRAINT `acta_elementos_ibfk_1` FOREIGN KEY (`acta_id`) REFERENCES `actas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acta_elementos_ibfk_2` FOREIGN KEY (`elemento_id`) REFERENCES `inventario_general` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `actas`
--

DROP TABLE IF EXISTS `actas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `actas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `responsable_id` int(11) NOT NULL,
  `sede_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_generacion` datetime NOT NULL DEFAULT current_timestamp(),
  `archivo_pdf` varchar(255) NOT NULL,
  `estado` enum('generada','entregada','devuelta','reasignada') NOT NULL DEFAULT 'generada',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `responsable_id` (`responsable_id`),
  KEY `sede_id` (`sede_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `actas_ibfk_1` FOREIGN KEY (`responsable_id`) REFERENCES `profesores` (`id`),
  CONSTRAINT `actas_ibfk_2` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`),
  CONSTRAINT `actas_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bajas`
--

DROP TABLE IF EXISTS `bajas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bajas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `elemento_id` int(11) NOT NULL,
  `motivo` varchar(50) NOT NULL,
  `fecha_baja` date NOT NULL,
  `descripcion` text DEFAULT NULL,
  `documento_baja` varchar(255) DEFAULT NULL,
  `usuario_solicita` int(11) NOT NULL,
  `estado` enum('solicitada','aprobada','rechazada') NOT NULL DEFAULT 'solicitada',
  `aprobado_por` int(11) DEFAULT NULL,
  `observacion_aprobacion` text DEFAULT NULL,
  `fecha_aprobacion` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_baja_elemento` (`elemento_id`),
  KEY `idx_baja_solicitante` (`usuario_solicita`),
  KEY `idx_baja_aprobador` (`aprobado_por`),
  KEY `idx_baja_estado` (`estado`),
  CONSTRAINT `bajas_ibfk_aprueba` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `bajas_ibfk_elemento` FOREIGN KEY (`elemento_id`) REFERENCES `inventario_general` (`id`),
  CONSTRAINT `bajas_ibfk_solicita` FOREIGN KEY (`usuario_solicita`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `elemento_historial`
--

DROP TABLE IF EXISTS `elemento_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `elemento_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `elemento_id` int(11) NOT NULL,
  `tipo_evento` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `datos_anterior` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_anterior`)),
  `datos_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_nuevos`)),
  `usuario_id` int(11) DEFAULT NULL,
  `acta_id` int(11) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_historial_elemento` (`elemento_id`),
  KEY `idx_historial_tipo` (`tipo_evento`),
  KEY `idx_historial_fecha` (`fecha`),
  KEY `fk_historial_usuario` (`usuario_id`),
  KEY `fk_historial_acta` (`acta_id`),
  CONSTRAINT `fk_historial_acta` FOREIGN KEY (`acta_id`) REFERENCES `actas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_historial_elemento` FOREIGN KEY (`elemento_id`) REFERENCES `inventario_general` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipos`
--

DROP TABLE IF EXISTS `equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_interno` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `id_tipo` int(11) DEFAULT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `marca` varchar(50) DEFAULT NULL,
  `numero_serie` varchar(50) DEFAULT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `procesador` varchar(50) DEFAULT NULL,
  `ram` varchar(20) DEFAULT NULL,
  `almacenamiento` varchar(50) DEFAULT NULL,
  `accesorios` text DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `estado` enum('disponible','prestado','mantenimiento','dañado','donado') DEFAULT 'disponible',
  `observacion` text DEFAULT NULL,
  `id_sede` int(11) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 5,
  `descripcion_articulo` text DEFAULT NULL,
  `vr_comercial` decimal(12,2) DEFAULT 0.00,
  `vida_util` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_interno` (`codigo_interno`),
  KEY `id_tipo` (`id_tipo`),
  KEY `id_categoria` (`id_categoria`),
  KEY `id_sede` (`id_sede`),
  KEY `idx_codigo_interno` (`codigo_interno`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `equipos_ibfk_1` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_equipo` (`id`),
  CONSTRAINT `equipos_ibfk_2` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id`),
  CONSTRAINT `equipos_ibfk_3` FOREIGN KEY (`id_sede`) REFERENCES `sedes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `equipos_bajo_stock`
--

DROP TABLE IF EXISTS `equipos_bajo_stock`;
/*!50001 DROP VIEW IF EXISTS `equipos_bajo_stock`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `equipos_bajo_stock` AS SELECT
 1 AS `id`,
  1 AS `codigo_interno`,
  1 AS `nombre`,
  1 AS `marca`,
  1 AS `modelo`,
  1 AS `stock`,
  1 AS `stock_minimo`,
  1 AS `sede_nombre` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `estados`
--

DROP TABLE IF EXISTS `estados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estudiantes`
--

DROP TABLE IF EXISTS `estudiantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estudiantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `codigo_estudiante` varchar(20) NOT NULL,
  `grado` int(11) NOT NULL CHECK (`grado` between 6 and 11),
  `grupo` varchar(5) NOT NULL,
  `jornada` enum('mañana','tarde','noche') DEFAULT 'mañana',
  `fecha_nacimiento` date DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono_contacto` varchar(20) DEFAULT NULL,
  `nombre_acudiente` varchar(100) DEFAULT NULL,
  `telefono_acudiente` varchar(20) DEFAULT NULL,
  `email_acudiente` varchar(100) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_estudiante` (`codigo_estudiante`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evidencias`
--

DROP TABLE IF EXISTS `evidencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entidad` varchar(20) NOT NULL,
  `entidad_id` int(11) NOT NULL,
  `tipo_evidencia` varchar(50) DEFAULT NULL,
  `archivo` varchar(255) NOT NULL,
  `subida_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ev_entidad` (`entidad`,`entidad_id`),
  KEY `idx_ev_subida_por` (`subida_por`),
  CONSTRAINT `evidencias_ibfk_usuario` FOREIGN KEY (`subida_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `tipo_encuesta` enum('satisfaccion','sugerencia','queja','reporte_error') DEFAULT 'sugerencia',
  `comentario` text NOT NULL,
  `puntuacion` int(11) DEFAULT 5,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_dañados`
--

DROP TABLE IF EXISTS `inventario_dañados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario_dañados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_equipo` int(11) NOT NULL,
  `descripcion_daño` text NOT NULL,
  `fecha_daño` date NOT NULL,
  `reportado_por` int(11) DEFAULT NULL,
  `estado` enum('pendiente','reparado','descartado') DEFAULT 'pendiente',
  `fecha_reparacion` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_equipo` (`id_equipo`),
  KEY `reportado_por` (`reportado_por`),
  CONSTRAINT `inventario_dañados_ibfk_1` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventario_dañados_ibfk_2` FOREIGN KEY (`reportado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_general`
--

DROP TABLE IF EXISTS `inventario_general`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario_general` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_interno` varchar(50) DEFAULT NULL,
  `qr_path` varchar(255) DEFAULT NULL,
  `nombre` varchar(200) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `marca` varchar(50) DEFAULT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `numero_serie` varchar(50) DEFAULT NULL,
  `procesador` varchar(50) DEFAULT NULL,
  `ram` varchar(20) DEFAULT NULL,
  `almacenamiento` varchar(50) DEFAULT NULL,
  `accesorios` text DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `id_sede` int(11) DEFAULT NULL,
  `profesor_id` int(11) DEFAULT NULL,
  `origen_bien` enum('Compra','Donaci├│n','Transferencia','Otro') DEFAULT NULL,
  `documento_no_disponible` tinyint(1) NOT NULL DEFAULT 0,
  `proveedor_id` int(11) DEFAULT NULL,
  `numero_factura` varchar(100) DEFAULT NULL,
  `fecha_compra` date DEFAULT NULL,
  `valor_compra` decimal(12,2) DEFAULT NULL,
  `numero_orden_compra` varchar(100) DEFAULT NULL,
  `fecha_garantia` date DEFAULT NULL,
  `donante_nombre` varchar(150) DEFAULT NULL,
  `fecha_donacion` date DEFAULT NULL,
  `institucion_origen` varchar(150) DEFAULT NULL,
  `fecha_transferencia` date DEFAULT NULL,
  `descripcion_origen` text DEFAULT NULL,
  `documento_adquisicion` varchar(255) DEFAULT NULL,
  `estado` varchar(50) DEFAULT 'bueno',
  `situacion` varchar(30) NOT NULL DEFAULT 'disponible',
  `ubicacion` varchar(200) DEFAULT NULL,
  `codigo_ubicacion` varchar(20) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `vr_comercial` decimal(12,2) DEFAULT NULL,
  `vida_util` int(11) DEFAULT NULL,
  `activo` tinyint(4) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_ubicacion` (`ubicacion`),
  KEY `idx_profesor` (`profesor_id`),
  KEY `idx_proveedor` (`proveedor_id`),
  KEY `idx_inv_situacion` (`situacion`),
  KEY `idx_inv_id_sede` (`id_sede`),
  KEY `idx_inv_codigo` (`codigo_interno`),
  CONSTRAINT `inventario_general_ibfk_profesor` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`),
  CONSTRAINT `inventario_general_ibfk_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mantenimiento`
--

DROP TABLE IF EXISTS `mantenimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mantenimiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_equipo` int(11) DEFAULT NULL,
  `elemento_id` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `descripcion_trabajo` text NOT NULL,
  `costo` decimal(10,2) DEFAULT 0.00,
  `proveedor` varchar(100) DEFAULT NULL,
  `tecnico` varchar(100) DEFAULT NULL,
  `estado` enum('programado','en_proceso','completado','cancelado') DEFAULT 'programado',
  `observaciones` text DEFAULT NULL,
  `resultado` varchar(50) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_equipo` (`id_equipo`),
  KEY `id_usuario` (`id_usuario`),
  KEY `idx_estado` (`estado`),
  KEY `idx_mto_elemento` (`elemento_id`),
  CONSTRAINT `mantenimiento_ibfk_1` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id`),
  CONSTRAINT `mantenimiento_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `mantenimiento_ibfk_elemento` FOREIGN KEY (`elemento_id`) REFERENCES `inventario_general` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `movimientos`
--

DROP TABLE IF EXISTS `movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_equipo` int(11) NOT NULL,
  `tipo` enum('entrada','salida','ajuste','devolucion','donacion') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `stock_anterior` int(11) NOT NULL,
  `stock_nuevo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_solicitud` int(11) DEFAULT NULL,
  `id_prestamo` int(11) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_solicitud` (`id_solicitud`),
  KEY `id_prestamo` (`id_prestamo`),
  KEY `idx_equipo` (`id_equipo`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `movimientos_ibfk_1` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id`),
  CONSTRAINT `movimientos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `movimientos_ibfk_3` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id`),
  CONSTRAINT `movimientos_ibfk_4` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `tipo` enum('alerta','info','exito','error','recordatorio') DEFAULT 'info',
  `titulo` varchar(100) NOT NULL,
  `mensaje` text NOT NULL,
  `leido` tinyint(1) DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_leido` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_leido` (`leido`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `novedades`
--

DROP TABLE IF EXISTS `novedades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `novedades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `elemento_id` int(11) NOT NULL,
  `toma_fisica_id` int(11) DEFAULT NULL,
  `tipo` varchar(50) NOT NULL,
  `descripcion` text NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'abierta',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nov_elemento` (`elemento_id`),
  KEY `idx_nov_toma` (`toma_fisica_id`),
  KEY `idx_nov_usuario` (`usuario_id`),
  KEY `idx_nov_fecha` (`fecha`),
  CONSTRAINT `novedades_ibfk_elemento` FOREIGN KEY (`elemento_id`) REFERENCES `inventario_general` (`id`),
  CONSTRAINT `novedades_ibfk_toma` FOREIGN KEY (`toma_fisica_id`) REFERENCES `tomas_fisicas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `novedades_ibfk_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `prestamos`
--

DROP TABLE IF EXISTS `prestamos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prestamos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_solicitud` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `fecha_prestamo` date NOT NULL,
  `fecha_devolucion_esperada` date NOT NULL,
  `fecha_devolucion_real` date DEFAULT NULL,
  `hora_prestamo` time NOT NULL,
  `hora_devolucion` time DEFAULT NULL,
  `estado` enum('activo','devuelto','vencido','extraviado') DEFAULT 'activo',
  `observaciones` text DEFAULT NULL,
  `multa` decimal(10,2) DEFAULT 0.00,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_solicitud` (`id_solicitud`),
  KEY `id_equipo` (`id_equipo`),
  KEY `idx_estado` (`estado`),
  KEY `prestamos_ibfk_3` (`id_estudiante`),
  CONSTRAINT `prestamos_ibfk_1` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitudes` (`id`),
  CONSTRAINT `prestamos_ibfk_2` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id`),
  CONSTRAINT `prestamos_ibfk_3` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profesores`
--

DROP TABLE IF EXISTS `profesores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profesores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `identificacion` varchar(30) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `sede_id` int(11) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sede_id` (`sede_id`),
  CONSTRAINT `profesores_ibfk_1` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `nit` varchar(30) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `resumen_dashboard`
--

DROP TABLE IF EXISTS `resumen_dashboard`;
/*!50001 DROP VIEW IF EXISTS `resumen_dashboard`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `resumen_dashboard` AS SELECT
 1 AS `total_equipos`,
  1 AS `equipos_disponibles`,
  1 AS `equipos_prestados`,
  1 AS `equipos_mantenimiento`,
  1 AS `stock_critico`,
  1 AS `prestamos_activos`,
  1 AS `solicitudes_pendientes` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sedes`
--

DROP TABLE IF EXISTS `sedes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sedes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(10) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `capacidad` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `solicitudes`
--

DROP TABLE IF EXISTS `solicitudes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `solicitudes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_estudiante` int(11) DEFAULT NULL,
  `id_equipo` int(11) NOT NULL,
  `fecha_solicitud` date NOT NULL,
  `hora_solicitud` time NOT NULL,
  `motivo` text NOT NULL,
  `fecha_devolucion_esperada` date DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada','entregada','devuelta','cancelada') DEFAULT 'pendiente',
  `fecha_atencion` timestamp NULL DEFAULT NULL,
  `id_atendido` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_estudiante` (`id_estudiante`),
  KEY `id_equipo` (`id_equipo`),
  KEY `id_atendido` (`id_atendido`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `solicitudes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `solicitudes_ibfk_2` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id`),
  CONSTRAINT `solicitudes_ibfk_3` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id`),
  CONSTRAINT `solicitudes_ibfk_4` FOREIGN KEY (`id_atendido`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipo_equipo`
--

DROP TABLE IF EXISTS `tipo_equipo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipo_equipo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_tipo` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_tipo` (`nombre_tipo`),
  KEY `idx_tipo_categoria` (`categoria_id`),
  CONSTRAINT `tipo_equipo_ibfk_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=198 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tomas_fisicas`
--

DROP TABLE IF EXISTS `tomas_fisicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tomas_fisicas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sede_id` int(11) NOT NULL,
  `ubicacion` varchar(200) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_toma` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('en_progreso','finalizada','cancelada') NOT NULL DEFAULT 'en_progreso',
  `total_esperados` int(11) NOT NULL DEFAULT 0,
  `encontrados` int(11) NOT NULL DEFAULT 0,
  `no_encontrados` int(11) NOT NULL DEFAULT 0,
  `con_novedades` int(11) NOT NULL DEFAULT 0,
  `danados` int(11) NOT NULL DEFAULT 0,
  `en_mantenimiento` int(11) NOT NULL DEFAULT 0,
  `en_reparacion` int(11) NOT NULL DEFAULT 0,
  `en_buen_estado` int(11) NOT NULL DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `finalizada_en` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tf_sede` (`sede_id`),
  KEY `idx_tf_usuario` (`usuario_id`),
  KEY `idx_tf_fecha` (`fecha_toma`),
  CONSTRAINT `tomas_fisicas_ibfk_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`),
  CONSTRAINT `tomas_fisicas_ibfk_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tomas_fisicas_detalle`
--

DROP TABLE IF EXISTS `tomas_fisicas_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tomas_fisicas_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `toma_fisica_id` int(11) NOT NULL,
  `elemento_id` int(11) NOT NULL,
  `encontrado` tinyint(1) NOT NULL DEFAULT 0,
  `estado_registrado` varchar(50) DEFAULT NULL,
  `estado_encontrado` varchar(50) DEFAULT NULL,
  `coincide_codigo` tinyint(1) NOT NULL DEFAULT 0,
  `coincide_sede` tinyint(1) NOT NULL DEFAULT 0,
  `coincide_ubicacion` tinyint(1) NOT NULL DEFAULT 0,
  `coincide_responsable` tinyint(1) NOT NULL DEFAULT 0,
  `situacion_despues` varchar(30) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `verificador_id` int(11) DEFAULT NULL,
  `verificada_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_toma_elemento` (`toma_fisica_id`,`elemento_id`),
  KEY `idx_tfd_elemento` (`elemento_id`),
  KEY `idx_tfd_verificador` (`verificador_id`),
  CONSTRAINT `tomas_fisicas_detalle_ibfk_elemento` FOREIGN KEY (`elemento_id`) REFERENCES `inventario_general` (`id`),
  CONSTRAINT `tomas_fisicas_detalle_ibfk_toma` FOREIGN KEY (`toma_fisica_id`) REFERENCES `tomas_fisicas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tomas_fisicas_detalle_ibfk_verificador` FOREIGN KEY (`verificador_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `tipo_documento` enum('TI','CC') DEFAULT NULL,
  `numero_documento` varchar(20) DEFAULT NULL,
  `foto_url` varchar(500) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` varchar(20) DEFAULT 'usuario',
  `rol_id` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `numero_documento` (`numero_documento`),
  KEY `rol_id` (`rol_id`),
  KEY `idx_email` (`email`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `equipos_bajo_stock`
--

/*!50001 DROP VIEW IF EXISTS `equipos_bajo_stock`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `equipos_bajo_stock` AS select `e`.`id` AS `id`,`e`.`codigo_interno` AS `codigo_interno`,`e`.`nombre` AS `nombre`,`e`.`marca` AS `marca`,`e`.`modelo` AS `modelo`,`e`.`stock` AS `stock`,`e`.`stock_minimo` AS `stock_minimo`,`s`.`nombre` AS `sede_nombre` from (`equipos` `e` join `sedes` `s` on(`e`.`id_sede` = `s`.`id`)) where `e`.`stock` < `e`.`stock_minimo` and `e`.`activo` = 1 order by `e`.`stock_minimo` - `e`.`stock` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `resumen_dashboard`
--

/*!50001 DROP VIEW IF EXISTS `resumen_dashboard`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `resumen_dashboard` AS select (select count(0) from `equipos` where `equipos`.`activo` = 1) AS `total_equipos`,(select count(0) from `equipos` where `equipos`.`estado` = 'disponible') AS `equipos_disponibles`,(select count(0) from `equipos` where `equipos`.`estado` = 'prestado') AS `equipos_prestados`,(select count(0) from `equipos` where `equipos`.`estado` = 'mantenimiento') AS `equipos_mantenimiento`,(select count(0) from `equipos` where `equipos`.`stock` < `equipos`.`stock_minimo`) AS `stock_critico`,(select count(0) from `prestamos` where `prestamos`.`estado` = 'activo') AS `prestamos_activos`,(select count(0) from `solicitudes` where `solicitudes`.`estado` = 'pendiente') AS `solicitudes_pendientes` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-13 18:17:43

--
-- Table structure for table `auditoria`
-- (Agregada el 2026-08-13: Auditoría del Sistema)
--

DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `entidad` varchar(100) DEFAULT NULL,
  `entidad_id` int(11) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_anteriores`)),
  `datos_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_nuevos`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aud_usuario` (`usuario_id`),
  KEY `idx_aud_accion` (`accion`),
  KEY `idx_aud_modulo` (`modulo`),
  KEY `idx_aud_fecha` (`fecha`),
  KEY `idx_aud_entidad` (`entidad`,`entidad_id`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
