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
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'Académico','Equipos para uso académico en aulas','2026-05-30 01:11:43'),(2,'Administrativo','Equipos para uso administrativo','2026-05-30 01:11:43'),(3,'Laboratorio','Equipos para laboratorios especializados','2026-05-30 01:11:43'),(4,'Biblioteca','Equipos para sala de informática','2026-05-30 01:11:43');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion`
--

LOCK TABLES `configuracion` WRITE;
/*!40000 ALTER TABLE `configuracion` DISABLE KEYS */;
INSERT INTO `configuracion` VALUES (1,'dias_prestamo_docente','7','numero','Días máximos de préstamo para docentes','2026-05-30 01:11:43'),(2,'dias_prestamo_estudiante','3','numero','Días máximos de préstamo para estudiantes','2026-05-30 01:11:43'),(3,'max_equipos_docente','5','numero','Máximo de equipos que puede solicitar un docente','2026-05-30 01:11:43'),(4,'max_equipos_estudiante','2','numero','Máximo de equipos que puede solicitar un estudiante','2026-05-30 01:11:43'),(5,'multa_dia_retraso','2000','moneda','Valor de multa por día de retraso','2026-05-30 01:11:43');
INSERT INTO `configuracion` (`clave`, `valor`, `tipo`, `descripcion`) VALUES ('dias_alerta_garantia','30','entero','Días previos al vencimiento de garantía para generar alerta');
/*!40000 ALTER TABLE `configuracion` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipos`
--

LOCK TABLES `equipos` WRITE;
/*!40000 ALTER TABLE `equipos` DISABLE KEYS */;
INSERT INTO `equipos` VALUES (10,'123456','hp',2,1,'azus','123456','123456','interl 5','8','256','cargador','2026-06-03','prestado','esta en optimo estado',2,2,5,'tiene la pantalla un poco mala',5.00,3,1,'2026-06-02 23:18:04','2026-06-04 01:41:23');
/*!40000 ALTER TABLE `equipos` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `estudiantes`
--

LOCK TABLES `estudiantes` WRITE;
/*!40000 ALTER TABLE `estudiantes` DISABLE KEYS */;
/*!40000 ALTER TABLE `estudiantes` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `feedback`
--

LOCK TABLES `feedback` WRITE;
/*!40000 ALTER TABLE `feedback` DISABLE KEYS */;
/*!40000 ALTER TABLE `feedback` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `inventario_dañados`
--

LOCK TABLES `inventario_dañados` WRITE;
/*!40000 ALTER TABLE `inventario_dañados` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventario_dañados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mantenimiento`
--

DROP TABLE IF EXISTS `mantenimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mantenimiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_equipo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `descripcion_trabajo` text NOT NULL,
  `costo` decimal(10,2) DEFAULT 0.00,
  `proveedor` varchar(100) DEFAULT NULL,
  `tecnico` varchar(100) DEFAULT NULL,
  `estado` enum('programado','en_proceso','completado','cancelado') DEFAULT 'programado',
  `observaciones` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_equipo` (`id_equipo`),
  KEY `id_usuario` (`id_usuario`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `mantenimiento_ibfk_1` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id`),
  CONSTRAINT `mantenimiento_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mantenimiento`
--

LOCK TABLES `mantenimiento` WRITE;
/*!40000 ALTER TABLE `mantenimiento` DISABLE KEYS */;
/*!40000 ALTER TABLE `mantenimiento` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `movimientos`
--

LOCK TABLES `movimientos` WRITE;
/*!40000 ALTER TABLE `movimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `movimientos` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

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
  CONSTRAINT `prestamos_ibfk_3` FOREIGN KEY (`id_estudiante`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prestamos`
--

LOCK TABLES `prestamos` WRITE;
/*!40000 ALTER TABLE `prestamos` DISABLE KEYS */;
/*!40000 ALTER TABLE `prestamos` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Administrador del sistema - acceso total','2026-05-30 01:11:43'),(2,'coordinador','Coordinador - puede gestionar inventario','2026-05-30 01:11:43'),(3,'docente','Docente - puede solicitar equipos','2026-05-30 01:11:43'),(4,'estudiante','Estudiante - puede solicitar equipos','2026-05-30 01:11:43');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sedes`
--

DROP TABLE IF EXISTS `sedes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sedes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `capacidad` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sedes`
--

LOCK TABLES `sedes` WRITE;
/*!40000 ALTER TABLE `sedes` DISABLE KEYS */;
INSERT INTO `sedes` VALUES (1,'Sede Principal','Calle 50 #40-20, Centro',NULL,500,1,'2026-05-30 01:11:43'),(2,'El Porvenir','Carrera 45 #67-89, El Porvenir',NULL,300,1,'2026-05-30 01:11:43'),(3,'El Progreso','Avenida 68 #12-34, El Progreso',NULL,250,1,'2026-05-30 01:11:43'),(4,'Los Comodatos','Diagonal 23 #45-67, Los Comodatos',NULL,200,1,'2026-05-30 01:11:43'),(5,'La Paz','',NULL,NULL,1,'2026-06-12 00:00:00');
/*!40000 ALTER TABLE `sedes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profesores`
--

DROP TABLE IF EXISTS `profesores`;
CREATE TABLE `profesores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `sede_id` int(11) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sede_id` (`sede_id`),
  CONSTRAINT `profesores_ibfk_1` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Column added by migration `migracion_profesores.sql`:
-- inventario_general.profesor_id -> FK a profesores.id
--

--
-- Tables/columns added by migration `migracion_documentacion.sql`:
-- proveedores, actas, acta_elementos, profesores.identificacion,
-- inventario_general: origen_bien, documento_no_disponible, proveedor_id,
-- numero_factura, fecha_compra, valor_compra, numero_orden_compra,
-- fecha_garantia, donante_nombre, fecha_donacion, institucion_origen,
-- fecha_transferencia, descripcion_origen, documento_adquisicion
--

-- Table structure for table `proveedores`

DROP TABLE IF EXISTS `proveedores`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `actas`

DROP TABLE IF EXISTS `actas`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `acta_elementos`

DROP TABLE IF EXISTS `acta_elementos`;
CREATE TABLE `acta_elementos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `acta_id` int(11) NOT NULL,
  `elemento_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acta_elemento` (`acta_id`,`elemento_id`),
  KEY `elemento_id` (`elemento_id`),
  CONSTRAINT `acta_elementos_ibfk_1` FOREIGN KEY (`acta_id`) REFERENCES `actas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acta_elementos_ibfk_2` FOREIGN KEY (`elemento_id`) REFERENCES `inventario_general` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `elemento_historial`
--

DROP TABLE IF EXISTS `elemento_historial`;
CREATE TABLE `elemento_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `elemento_id` int(11) NOT NULL,
  `tipo_evento` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `datos_anterior` json DEFAULT NULL,
  `datos_nuevos` json DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acta_id` int(11) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_historial_elemento` (`elemento_id`),
  KEY `idx_historial_tipo` (`tipo_evento`),
  KEY `idx_historial_fecha` (`fecha`),
  CONSTRAINT `fk_historial_elemento` FOREIGN KEY (`elemento_id`) REFERENCES `inventario_general` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_historial_acta` FOREIGN KEY (`acta_id`) REFERENCES `actas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitudes`
--

LOCK TABLES `solicitudes` WRITE;
/*!40000 ALTER TABLE `solicitudes` DISABLE KEYS */;
INSERT INTO `solicitudes` VALUES (3,6,NULL,10,'2026-06-02','02:11:32','para estudiar','2026-06-04','aprobada','2026-06-03 00:11:48',1,NULL,'2026-06-03 00:11:32'),(4,6,NULL,10,'2026-06-02','02:14:14','fre','2026-06-05','aprobada','2026-06-03 00:14:27',1,NULL,'2026-06-03 00:14:14'),(5,6,NULL,10,'2026-06-02','02:15:44','11234','2026-06-04','aprobada','2026-06-03 00:16:20',1,NULL,'2026-06-03 00:15:44'),(6,6,NULL,10,'2026-06-03','03:41:05','estudiar','2026-06-05','aprobada','2026-06-04 01:41:23',1,NULL,'2026-06-04 01:41:05');
/*!40000 ALTER TABLE `solicitudes` ENABLE KEYS */;
UNLOCK TABLES;

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_tipo` (`nombre_tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipo_equipo`
--

LOCK TABLES `tipo_equipo` WRITE;
/*!40000 ALTER TABLE `tipo_equipo` DISABLE KEYS */;
INSERT INTO `tipo_equipo` VALUES (1,'Computador de Escritorio','Equipos de cómputo de escritorio','2026-05-30 01:11:43'),(2,'Portátil','Computadores portátiles','2026-05-30 01:11:43'),(3,'Tablet','Dispositivos móviles tipo tablet','2026-05-30 01:11:43'),(4,'Proyector','Equipos de proyección y video beam','2026-05-30 01:11:43'),(5,'Impresora','Equipos de impresión multifuncional','2026-05-30 01:11:43'),(6,'Accesorios','Periféricos y accesorios de computo','2026-05-30 01:11:43');
/*!40000 ALTER TABLE `tipo_equipo` ENABLE KEYS */;
UNLOCK TABLES;

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
  UNIQUE KEY `nombre` (`nombre`),
  KEY `rol_id` (`rol_id`),
  KEY `idx_email` (`email`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador Sistema','admin@mic.com','3001234567',NULL,NULL,'uploads/user_1_1780107630.jpg','$2y$10$X8pOORgiU5dZ/nF4BqC3pehIElLziNxTpXt6LPWHi9i3YmPL3bl/i',1,1,'2026-05-30 01:11:43','2026-05-30 02:20:30',NULL),(6,'didier g',NULL,NULL,'TI','1016042167',NULL,'$2y$10$n5IL.1IXqvIvHJdwdEO74.SjyNc/YOGjARMyfbAOt9qzbqQNSPxta',4,1,'2026-06-03 00:00:56','2026-06-03 00:00:56',NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventario_general`
--

DROP TABLE IF EXISTS `inventario_general`;
CREATE TABLE `inventario_general` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `estado` varchar(50) DEFAULT 'bueno',
  `ubicacion` varchar(200) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `vr_comercial` decimal(12,2) DEFAULT NULL,
  `vida_util` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_ubicacion` (`ubicacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping routines for database 'mic'
--

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

-- Dump completed on 2026-06-05 14:05:23
