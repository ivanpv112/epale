-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 09-09-2026 a las 00:08:00
-- Versión del servidor: 8.0.36
-- Versión de PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `epale_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

DROP TABLE IF EXISTS `alumnos`;
CREATE TABLE IF NOT EXISTS `alumnos` (
  `alumno_id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `carrera` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`alumno_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `alumnos`
--

INSERT INTO `alumnos` (`alumno_id`, `usuario_id`, `carrera`) VALUES
(1, 3, 'LIME'),
(2, 4, 'LTIN'),
(4, 7, 'LTIN'),
(5, 10, 'LTIN'),
(6, 11, 'w'),
(10, 15, 'LTIN'),
(12, 24, 'LAFI');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

DROP TABLE IF EXISTS `asistencias`;
CREATE TABLE IF NOT EXISTS `asistencias` (
  `asistencia_id` int NOT NULL AUTO_INCREMENT,
  `inscripcion_id` int NOT NULL,
  `fecha` date NOT NULL,
  `estatus` enum('ASISTENCIA','FALTA','RETARDO') NOT NULL,
  PRIMARY KEY (`asistencia_id`),
  UNIQUE KEY `unique_asistencia` (`inscripcion_id`,`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `asistencias`
--

INSERT INTO `asistencias` (`asistencia_id`, `inscripcion_id`, `fecha`, `estatus`) VALUES
(1, 7, '2026-04-16', 'ASISTENCIA'),
(2, 1, '2026-04-16', 'ASISTENCIA'),
(3, 6, '2026-04-16', 'ASISTENCIA'),
(4, 7, '2026-04-17', 'ASISTENCIA'),
(5, 1, '2026-04-17', 'ASISTENCIA'),
(6, 7, '2026-04-21', 'ASISTENCIA'),
(7, 1, '2026-04-21', 'ASISTENCIA'),
(8, 7, '2026-04-23', 'ASISTENCIA'),
(9, 1, '2026-04-23', 'ASISTENCIA'),
(11, 7, '2026-04-29', 'FALTA'),
(12, 1, '2026-04-29', 'ASISTENCIA'),
(13, 7, '2026-04-30', 'FALTA'),
(14, 1, '2026-04-30', 'ASISTENCIA'),
(15, 11, '2026-08-20', 'ASISTENCIA'),
(16, 7, '2026-08-20', 'ASISTENCIA'),
(17, 1, '2026-08-20', 'ASISTENCIA'),
(18, 11, '2026-08-21', 'ASISTENCIA'),
(19, 7, '2026-08-21', 'ASISTENCIA'),
(20, 1, '2026-08-21', 'ASISTENCIA'),
(21, 6, '2026-08-21', 'ASISTENCIA'),
(22, 6, '2026-08-26', 'ASISTENCIA'),
(23, 6, '2026-08-27', 'ASISTENCIA'),
(24, 13, '2026-08-27', 'ASISTENCIA'),
(25, 6, '2026-09-08', 'FALTA'),
(26, 21, '2026-09-08', 'ASISTENCIA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `avisos`
--

DROP TABLE IF EXISTS `avisos`;
CREATE TABLE IF NOT EXISTS `avisos` (
  `aviso_id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) DEFAULT NULL,
  `cuerpo` text,
  `tipo_audiencia` enum('GLOBAL','IDIOMA','MATERIA','GRUPO') DEFAULT NULL,
  `audiencia_ref` varchar(100) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_expiracion` datetime DEFAULT NULL,
  PRIMARY KEY (`aviso_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `avisos`
--

INSERT INTO `avisos` (`aviso_id`, `titulo`, `cuerpo`, `tipo_audiencia`, `audiencia_ref`, `fecha_creacion`, `fecha_expiracion`) VALUES
(4, 'XD', 'se cancela la clase de mañana', 'MATERIA', '3', '2026-04-23 00:32:15', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones`
--

DROP TABLE IF EXISTS `calificaciones`;
CREATE TABLE IF NOT EXISTS `calificaciones` (
  `calificacion_id` int NOT NULL AUTO_INCREMENT,
  `inscripcion_id` int NOT NULL,
  `tipo_examen` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `puntaje` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`calificacion_id`),
  KEY `inscripcion_id` (`inscripcion_id`)
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `calificaciones`
--

INSERT INTO `calificaciones` (`calificacion_id`, `inscripcion_id`, `tipo_examen`, `puntaje`) VALUES
(1, 1, 'Q1', 8.00),
(2, 1, 'Q2', 10.00),
(3, 1, 'Q3', 8.00),
(4, 1, 'QO1', 5.00),
(5, 1, 'QO2', 5.00),
(6, 1, 'WRITING', 3.00),
(7, 1, 'PLATAFORMA', 30.00),
(8, 1, 'PARTICIPACION', 2.00),
(11, 1, 'TOEFL', 10.00),
(12, 6, 'TOEFL', 5.00),
(13, 6, 'PARTICIPACION', NULL),
(20, 6, 'QO2', NULL),
(22, 8, 'QO1', 5.00),
(26, 8, 'TOEFL', 15.00),
(30, 10, 'PARTICIPACION', 4.00),
(31, 11, 'TOEFL', 15.00),
(32, 7, 'WRITING', 5.00),
(33, 7, 'PARTICIPACION', 5.00),
(34, 7, 'QO2', 5.00),
(35, 7, 'QO1', 5.00),
(36, 8, 'PARTICIPACION', 5.00),
(37, 8, 'PLATAFORMA', 40.00),
(38, 8, 'WRITING', 3.00),
(39, 8, 'Q1', 5.00),
(40, 8, 'Q2', 2.00),
(41, 8, 'Q3', 10.00),
(42, 8, 'QO2', 5.00),
(51, 10, 'Q1', 10.00),
(52, 10, 'Q2', 10.00),
(53, 10, 'Q3', 10.00),
(54, 10, 'QO1', 5.00),
(55, 10, 'QO2', 5.00),
(56, 10, 'PLATAFORMA', 30.00),
(57, 10, 'WRITING', 5.00),
(58, 14, 'PLATAFORMA', 50.00),
(59, 13, 'TOEFL', 15.00),
(60, 13, 'PARTICIPACION', 5.00),
(61, 13, 'PLATAFORMA', 40.00),
(62, 13, 'Q1', 10.00),
(63, 13, 'Q2', 10.00),
(64, 13, 'Q3', 10.00),
(65, 13, 'QO1', 5.00),
(66, 13, 'QO2', 5.00),
(67, 13, 'WRITING', 5.00),
(68, 14, 'QO1', 5.00),
(69, 14, 'QO2', 5.00),
(70, 14, 'PARTICIPACION', 5.00),
(71, 14, 'WRITING', 5.00),
(72, 15, 'TOEFL', 15.00),
(73, 15, 'PARTICIPACION', 5.00),
(74, 15, 'PLATAFORMA', 40.00),
(75, 15, 'WRITING', 5.00),
(76, 15, 'Q1', 10.00),
(77, 15, 'Q2', 10.00),
(78, 15, 'Q3', 10.00),
(79, 15, 'QO1', 5.00),
(80, 15, 'QO2', 5.00),
(81, 12, 'Q1', 10.00),
(82, 12, 'Q2', 10.00),
(83, 12, 'Q3', 10.00),
(84, 12, 'QO1', 5.00),
(85, 12, 'QO2', 5.00),
(86, 18, 'CERTIFICACION', 10.00),
(87, 18, 'Q1', 10.00),
(88, 18, 'Q2', 10.00),
(89, 18, 'Q3', 10.00),
(90, 18, 'QO1', 5.00),
(91, 18, 'QO2', 5.00),
(92, 18, 'PLATAFORMA', 20.00),
(93, 18, 'WRITING', 5.00),
(94, 19, 'CERTIFICACION', 5.00),
(95, 19, 'Q1', 5.00),
(96, 19, 'Q2', 5.00),
(97, 19, 'Q3', 5.00),
(98, 19, 'QO1', 5.00),
(99, 19, 'QO2', 5.00),
(100, 19, 'PARTICIPACION', 5.00),
(101, 19, 'PLATAFORMA', 40.00),
(102, 19, 'WRITING', 5.00),
(106, 6, 'WRITING', NULL),
(107, 6, 'QO1', 0.00),
(109, 13, 'CERTIFICACION', 10.00),
(110, 20, 'TOEFL', 5.00),
(111, 20, 'PLATAFORMA', 5.00),
(112, 20, 'WRITING', 5.00),
(113, 20, 'Q1', 5.00),
(114, 20, 'Q2', 5.00),
(115, 20, 'Q3', 5.00),
(116, 20, 'QO1', 5.00),
(117, 20, 'QO2', 5.00),
(118, 21, 'QO1', 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificaciones`
--

DROP TABLE IF EXISTS `certificaciones`;
CREATE TABLE IF NOT EXISTS `certificaciones` (
  `certificacion_id` int NOT NULL AUTO_INCREMENT,
  `alumno_id` int DEFAULT NULL,
  `idioma` varchar(50) DEFAULT NULL,
  `puntaje` varchar(20) DEFAULT NULL,
  `nivel_obtenido` varchar(50) DEFAULT NULL,
  `periodo` varchar(20) DEFAULT NULL,
  `fecha_aplicacion` date DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`certificacion_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `certificaciones`
--

INSERT INTO `certificaciones` (`certificacion_id`, `alumno_id`, `idioma`, `puntaje`, `nivel_obtenido`, `periodo`, `fecha_aplicacion`, `fecha_registro`) VALUES
(1, 1, 'Inglés', '280', 'B1', '2027B', '2027-02-15', '2026-03-20 19:04:32'),
(2, 1, 'Francés', '200', 'C1', '2022B', '2022-09-11', '2026-04-15 22:10:18'),
(3, 1, 'JAPONES', '550', 'C2', '2022B', '2022-08-31', '2026-05-08 21:52:28'),
(4, 1, 'Aleman', '550', 'C2', '2027B', '2027-02-14', '2026-09-02 21:48:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ciclos`
--

DROP TABLE IF EXISTS `ciclos`;
CREATE TABLE IF NOT EXISTS `ciclos` (
  `ciclo_id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`ciclo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ciclos`
--

INSERT INTO `ciclos` (`ciclo_id`, `nombre`, `activo`) VALUES
(1, '2026A', 1),
(2, '2026B', 0),
(3, '2027B', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `criterios_evaluacion`
--

DROP TABLE IF EXISTS `criterios_evaluacion`;
CREATE TABLE IF NOT EXISTS `criterios_evaluacion` (
  `criterio_id` int NOT NULL AUTO_INCREMENT,
  `materia_id` int NOT NULL,
  `categoria` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_examen` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_examen` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `puntos_maximos` decimal(5,2) NOT NULL,
  `icono` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'fa-star',
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'var(--udg-light)',
  PRIMARY KEY (`criterio_id`),
  KEY `materia_id` (`materia_id`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `criterios_evaluacion`
--

INSERT INTO `criterios_evaluacion` (`criterio_id`, `materia_id`, `categoria`, `codigo_examen`, `nombre_examen`, `puntos_maximos`, `icono`, `color`) VALUES
(1, 1, 'Quizzes', 'Q1', 'Quiz 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(2, 1, 'Quizzes', 'Q2', 'Quiz 2', 10.00, 'fa-book-open', 'var(--udg-light)'),
(3, 1, 'Quizzes', 'Q3', 'Quiz 3', 10.00, 'fa-book-open', 'var(--udg-light)'),
(4, 1, 'Quizzes Orales', 'QO1', 'Quiz Oral 1', 5.00, 'fa-comments', 'var(--success)'),
(5, 1, 'Quizzes Orales', 'QO2', 'Quiz Oral 2', 5.00, 'fa-comments', 'var(--success)'),
(6, 1, 'Proyectos', 'WRITING', 'Writing Project', 5.00, 'fa-file-signature', 'var(--warning)'),
(7, 1, 'Plataforma', 'PLATAFORMA', 'Actividades Moodle', 40.00, 'fa-laptop-code', '#dc3545'),
(9, 1, 'Certificación', 'TOEFL', 'Examen TOEFL', 15.00, 'fa-certificate', '#6f42c1'),
(10, 2, 'Certificación', 'TOEFL', 'Examen TOEFL', 10.00, 'fa-certificate', '#6f42c1'),
(11, 2, 'Quizzes', 'Q1', 'Quiz 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(12, 2, 'Plataforma', 'PLATAFORMA', 'Actividades Moodle', 40.00, 'fa-laptop-code', '#dc3545'),
(33, 8, 'Exámenes', 'Q1', 'Examen 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(34, 8, 'Exámenes', 'Q2', 'Examen 2', 10.00, 'fa-book-open', 'var(--udg-light)'),
(35, 8, 'Exámenes', 'Q3', 'Examen 3', 10.00, 'fa-book-open', 'var(--udg-light)'),
(36, 8, 'Exámenes Orales', 'QO1', 'Examen Oral 1', 5.00, 'fa-comments', '#28a745'),
(37, 8, 'Exámenes Orales', 'QO2', 'Examen Oral 2', 5.00, 'fa-comments', '#28a745'),
(38, 8, 'Proyectos', 'WRITING', 'Proyecto Escrito', 5.00, 'fa-file-signature', '#ffc107'),
(39, 8, 'Participación', 'PARTICIPACION', 'Participación en Clase', 5.00, 'fa-hand-paper', '#17a2b8'),
(40, 8, 'Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 50.00, 'fa-laptop-code', '#dc3545'),
(41, 5, 'Exámenes', 'Q1', 'Examen 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(42, 5, 'Exámenes', 'Q2', 'Examen 2', 10.00, 'fa-book-open', 'var(--udg-light)'),
(43, 5, 'Exámenes', 'Q3', 'Examen 3', 10.00, 'fa-book-open', 'var(--udg-light)'),
(44, 5, 'Exámenes Orales', 'QO1', 'Examen Oral 1', 5.00, 'fa-comments', '#28a745'),
(45, 5, 'Exámenes Orales', 'QO2', 'Examen Oral 2', 5.00, 'fa-comments', '#28a745'),
(46, 5, 'Proyectos', 'WRITING', 'Proyecto Escrito', 5.00, 'fa-file-signature', '#ffc107'),
(47, 5, 'Participación', 'PARTICIPACION', 'Participación en Clase', 5.00, 'fa-hand-paper', '#17a2b8'),
(48, 5, 'Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 50.00, 'fa-laptop-code', '#dc3545'),
(49, 3, 'Exámenes', 'Q1', 'Examen 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(50, 3, 'Exámenes', 'Q2', 'Examen 2', 10.00, 'fa-book-open', 'var(--udg-light)'),
(51, 3, 'Exámenes', 'Q3', 'Examen 3', 10.00, 'fa-book-open', 'var(--udg-light)'),
(52, 3, 'Exámenes Orales', 'QO1', 'Examen Oral 1', 5.00, 'fa-comments', '#28a745'),
(53, 3, 'Exámenes Orales', 'QO2', 'Examen Oral 2', 5.00, 'fa-comments', '#28a745'),
(54, 3, 'Proyectos', 'WRITING', 'Proyecto Escrito', 5.00, 'fa-file-signature', '#ffc107'),
(55, 3, 'Participación', 'PARTICIPACION', 'Participación en Clase', 5.00, 'fa-hand-paper', '#17a2b8'),
(56, 3, 'Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 50.00, 'fa-laptop-code', '#dc3545'),
(66, 9, 'Exámenes', 'Q1', 'Examen 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(67, 9, 'Exámenes', 'Q2', 'Examen 2', 10.00, 'fa-book-open', 'var(--udg-light)'),
(68, 9, 'Exámenes', 'Q3', 'Examen 3', 10.00, 'fa-book-open', 'var(--udg-light)'),
(69, 9, 'Exámenes Orales', 'QO1', 'Examen Oral 1', 5.00, 'fa-comments', '#28a745'),
(70, 9, 'Exámenes Orales', 'QO2', 'Examen Oral 2', 5.00, 'fa-comments', '#28a745'),
(71, 9, 'Proyectos', 'WRITING', 'Proyecto Escrito', 5.00, 'fa-file-signature', '#ffc107'),
(72, 9, 'Participación', 'PARTICIPACION', 'Participación en Clase', 5.00, 'fa-hand-paper', '#17a2b8'),
(73, 9, 'Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 40.00, 'fa-laptop-code', '#dc3545'),
(74, 9, 'Certificación', 'CERTIFICACION', 'Examen de Certificación', 10.00, 'fa-certificate', '#6f42c1'),
(75, 4, 'Exámenes', 'Q1', 'Examen 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(76, 4, 'Exámenes', 'Q2', 'Examen 2', 10.00, 'fa-book-open', 'var(--udg-light)'),
(77, 4, 'Exámenes', 'Q3', 'Examen 3', 10.00, 'fa-book-open', 'var(--udg-light)'),
(78, 4, 'Exámenes Orales', 'QO1', 'Examen Oral 1', 5.00, 'fa-comments', '#28a745'),
(79, 4, 'Exámenes Orales', 'QO2', 'Examen Oral 2', 5.00, 'fa-comments', '#28a745'),
(80, 4, 'Proyectos', 'WRITING', 'Proyecto Escrito', 5.00, 'fa-file-signature', '#ffc107'),
(81, 4, 'Participación', 'PARTICIPACION', 'Participación en Clase', 5.00, 'fa-hand-paper', '#17a2b8'),
(82, 4, 'Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 40.00, 'fa-laptop-code', '#dc3545'),
(83, 4, 'Certificación', 'CERTIFICACION', 'Examen de Certificación', 10.00, 'fa-certificate', '#6f42c1'),
(84, 10, 'Exámenes', 'Q1', 'Examen 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(85, 10, 'Exámenes', 'Q2', 'Examen 2', 10.00, 'fa-book-open', 'var(--udg-light)'),
(86, 10, 'Exámenes', 'Q3', 'Examen 3', 10.00, 'fa-book-open', 'var(--udg-light)'),
(87, 10, 'Exámenes Orales', 'QO1', 'Examen Oral 1', 5.00, 'fa-comments', '#28a745'),
(88, 10, 'Exámenes Orales', 'QO2', 'Examen Oral 2', 5.00, 'fa-comments', '#28a745'),
(89, 10, 'Proyectos', 'WRITING', 'Proyecto Escrito', 5.00, 'fa-file-signature', '#ffc107'),
(90, 10, 'Participación', 'PARTICIPACION', 'Participación en Clase', 5.00, 'fa-hand-paper', '#17a2b8'),
(91, 10, 'Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 50.00, 'fa-laptop-code', '#dc3545'),
(92, 11, 'Exámenes', 'Q1', 'Examen 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(93, 11, 'Exámenes', 'Q2', 'Examen 2', 10.00, 'fa-book-open', 'var(--udg-light)'),
(94, 11, 'Exámenes', 'Q3', 'Examen 3', 10.00, 'fa-book-open', 'var(--udg-light)'),
(95, 11, 'Exámenes Orales', 'QO1', 'Examen Oral 1', 5.00, 'fa-comments', '#28a745'),
(96, 11, 'Exámenes Orales', 'QO2', 'Examen Oral 2', 5.00, 'fa-comments', '#28a745'),
(97, 11, 'Proyectos', 'WRITING', 'Proyecto Escrito', 5.00, 'fa-file-signature', '#ffc107'),
(98, 11, 'Participación', 'PARTICIPACION', 'Participación en Clase', 5.00, 'fa-hand-paper', '#17a2b8'),
(99, 11, 'Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 40.00, 'fa-laptop-code', '#dc3545'),
(100, 11, 'Certificación', 'CERTIFICACION', 'Examen de Certificación', 10.00, 'fa-certificate', '#6f42c1');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dictamenes_estudiantes`
--

DROP TABLE IF EXISTS `dictamenes_estudiantes`;
CREATE TABLE IF NOT EXISTS `dictamenes_estudiantes` (
  `id_dictamen` int NOT NULL AUTO_INCREMENT,
  `codigo_alumno` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_alumno` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrera` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ciclo_acreditacion` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clave_materia` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_materia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `num_dictamen` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_carga` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_dictamen`),
  UNIQUE KEY `unica_acreditacion` (`codigo_alumno`,`clave_materia`,`num_dictamen`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `dictamenes_estudiantes`
--

INSERT INTO `dictamenes_estudiantes` (`id_dictamen`, `codigo_alumno`, `nombre_alumno`, `carrera`, `ciclo_acreditacion`, `clave_materia`, `nombre_materia`, `num_dictamen`, `fecha_carga`) VALUES
(4, '222078445', 'ABRICA RIVERA DIEGO ROMEO', 'LAFI', '2026-A', 'I5134', 'Inglés I', 'III/547/2026', '2026-09-08 20:20:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `examenes_diagnosticos`
--

DROP TABLE IF EXISTS `examenes_diagnosticos`;
CREATE TABLE IF NOT EXISTS `examenes_diagnosticos` (
  `examen_id` int NOT NULL AUTO_INCREMENT,
  `alumno_id` int DEFAULT NULL,
  `idioma` varchar(50) DEFAULT NULL,
  `calificacion_texto` varchar(50) DEFAULT NULL,
  `nivel_asignado` int DEFAULT NULL,
  `fecha_realizacion` date DEFAULT NULL,
  `periodo` varchar(20) DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`examen_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `examenes_diagnosticos`
--

INSERT INTO `examenes_diagnosticos` (`examen_id`, `alumno_id`, `idioma`, `calificacion_texto`, `nivel_asignado`, `fecha_realizacion`, `periodo`, `fecha_registro`) VALUES
(1, 1, 'INGLES', 'A2 INICAL', 2, '2022-08-16', '2022-B', '2026-04-16 22:39:49'),
(2, 1, 'Frances', 'A3 AVANZADO', 1, '2022-04-04', '2022-B', '2026-04-30 01:00:54'),
(3, 1, 'JAPONES', 'A2 INICIAL', 2, '2022-01-21', '2022B', '2026-05-07 23:10:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos`
--

DROP TABLE IF EXISTS `grupos`;
CREATE TABLE IF NOT EXISTS `grupos` (
  `nrc` int NOT NULL,
  `clave_siiau` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `materia_id` int NOT NULL,
  `profesor_id` int NOT NULL,
  `ciclo_id` int NOT NULL,
  `cupo` int DEFAULT '30',
  `edicion_total` tinyint(1) DEFAULT '0',
  `clave_grupo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVO',
  PRIMARY KEY (`nrc`),
  KEY `materia_id` (`materia_id`),
  KEY `profesor_id` (`profesor_id`),
  KEY `ciclo_id` (`ciclo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `grupos`
--

INSERT INTO `grupos` (`nrc`, `clave_siiau`, `materia_id`, `profesor_id`, `ciclo_id`, `cupo`, `edicion_total`, `clave_grupo`, `estado`) VALUES
(1, NULL, 1, 11, 1, 30, 0, 'grp_6a9755f28f42b', 'ACTIVO'),
(9999, NULL, 5, 16, 1, 30, 0, 'grp_69fe5b971042f', 'ACTIVO'),
(35436, NULL, 3, 16, 1, 30, 0, 'grp_6a88d5bc6b020', 'ACTIVO'),
(44444, NULL, 4, 16, 3, 30, 0, 'grp_6a8d214347834', 'CERRADO'),
(60966, NULL, 4, 2, 1, 30, 0, '60966', 'ACTIVO'),
(122222, NULL, 11, 2, 1, 30, 0, 'grp_6a88d5681b6eb', 'ACTIVO'),
(122223, NULL, 5, 16, 1, 30, 0, 'grp_69fe5b971042f', 'ACTIVO'),
(134543, NULL, 11, 2, 1, 30, 0, 'grp_6a88d5681b6eb', 'ACTIVO'),
(199517, 'CU183', 7, 8, 1, 30, 0, 'JN.13.1.JN', 'ACTIVO'),
(2133213, NULL, 9, 8, 3, 30, 0, 'grp_6a8e04cf7baf0', 'CERRADO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios`
--

DROP TABLE IF EXISTS `horarios`;
CREATE TABLE IF NOT EXISTS `horarios` (
  `horario_id` int NOT NULL AUTO_INCREMENT,
  `nrc` int NOT NULL,
  `dias_patron` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `modalidad` enum('PRESENCIAL','VIRTUAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aula` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`horario_id`),
  KEY `nrc` (`nrc`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `horarios`
--

INSERT INTO `horarios` (`horario_id`, `nrc`, `dias_patron`, `hora_inicio`, `hora_fin`, `modalidad`, `aula`) VALUES
(5, 60966, 'L-M', '09:00:00', '11:00:00', 'PRESENCIAL', 'N-202'),
(12, 9999, 'V', '11:00:00', '12:00:00', 'PRESENCIAL', 'N-930'),
(22, 199517, 'V', '13:00:00', '15:00:00', 'PRESENCIAL', 'N391'),
(24, 134543, 'L-M', '13:00:00', '14:00:00', 'PRESENCIAL', 'N-239'),
(25, 35436, 'V', '13:00:00', '14:00:00', 'PRESENCIAL', 'A-259'),
(26, 122222, 'I-J', '11:00:00', '12:00:00', 'VIRTUAL', 'Zoom'),
(27, 122223, 'V', '09:00:00', '10:00:00', 'VIRTUAL', 'Zoom'),
(32, 44444, 'L-M', '23:00:00', '01:00:00', 'PRESENCIAL', 'N-444'),
(33, 2133213, 'S', '09:10:00', '10:13:00', 'PRESENCIAL', 'X234'),
(34, 1, 'V', '10:45:00', '12:45:00', 'PRESENCIAL', 'N-987');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones`
--

DROP TABLE IF EXISTS `inscripciones`;
CREATE TABLE IF NOT EXISTS `inscripciones` (
  `inscripcion_id` int NOT NULL AUTO_INCREMENT,
  `alumno_id` int NOT NULL,
  `nrc` int NOT NULL,
  `estatus` enum('INSCRITO','SOLICITUD_BAJA','BAJA','SOLICITUD_ALTA') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'INSCRITO',
  `calificacion_final` decimal(5,2) DEFAULT '0.00',
  PRIMARY KEY (`inscripcion_id`),
  KEY `alumno_id` (`alumno_id`),
  KEY `nrc` (`nrc`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `inscripciones`
--

INSERT INTO `inscripciones` (`inscripcion_id`, `alumno_id`, `nrc`, `estatus`, `calificacion_final`) VALUES
(6, 4, 60966, 'INSCRITO', 0.00),
(10, 1, 9999, 'INSCRITO', 0.00),
(13, 1, 134543, 'BAJA', 0.00),
(14, 1, 35436, 'INSCRITO', 0.00),
(18, 1, 44444, 'INSCRITO', 0.00),
(19, 1, 2133213, 'INSCRITO', 0.00),
(20, 5, 1, 'INSCRITO', 0.00),
(21, 5, 134543, 'INSCRITO', 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_intentos`
--

DROP TABLE IF EXISTS `login_intentos`;
CREATE TABLE IF NOT EXISTS `login_intentos` (
  `intento_id` int NOT NULL AUTO_INCREMENT,
  `identificador` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exitoso` tinyint(1) NOT NULL DEFAULT '0',
  `fecha_intento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`intento_id`),
  KEY `idx_identificador_fecha` (`identificador`,`fecha_intento`),
  KEY `idx_ip_fecha` (`ip`,`fecha_intento`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

DROP TABLE IF EXISTS `materias`;
CREATE TABLE IF NOT EXISTS `materias` (
  `materia_id` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel` int NOT NULL,
  PRIMARY KEY (`materia_id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`materia_id`, `clave`, `nombre`, `nivel`) VALUES
(1, 'I0004', 'INGLÉS', 4),
(2, 'F0001', 'FRANCÉS', 1),
(3, 'I0001', 'INGLÉS', 1),
(4, 'A0004', 'Aleman', 4),
(5, 'I0002', 'INGLÉS', 2),
(7, 'JN4', 'JAPONES NEGOCIOS', 4),
(8, 'I0003', 'Inglés', 3),
(9, 'I0014', 'INGLÉS', 4),
(10, 'R0001', 'Ruso', 1),
(11, 'R004', 'RUSO', 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores`
--

DROP TABLE IF EXISTS `profesores`;
CREATE TABLE IF NOT EXISTS `profesores` (
  `profesor_id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `nacionalidad` varchar(50) DEFAULT NULL,
  `experiencia` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`profesor_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `profesores`
--

INSERT INTO `profesores` (`profesor_id`, `usuario_id`, `nacionalidad`, `experiencia`) VALUES
(1, 16, 'SAYAYIN', 'C1'),
(2, 2, 'MEXICANA', 'C1'),
(3, 11, '', ''),
(4, 8, '', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_bajas`
--

DROP TABLE IF EXISTS `solicitudes_bajas`;
CREATE TABLE IF NOT EXISTS `solicitudes_bajas` (
  `solicitud_id` int NOT NULL AUTO_INCREMENT,
  `inscripcion_id` int NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `descripcion` text,
  `estatus` enum('PENDIENTE','APROBADA','RECHAZADA','CANCELADA') DEFAULT 'PENDIENTE',
  `respuesta_admin` text,
  `fecha_solicitud` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_respuesta` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`solicitud_id`),
  KEY `inscripcion_id` (`inscripcion_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `solicitudes_bajas`
--

INSERT INTO `solicitudes_bajas` (`solicitud_id`, `inscripcion_id`, `motivo`, `descripcion`, `estatus`, `respuesta_admin`, `fecha_solicitud`, `fecha_respuesta`) VALUES
(8, 1, 'Choque de horario', 'Ya no la quiero', 'RECHAZADA', '', '2026-03-13 00:28:39', '2026-03-13 00:28:54'),
(9, 12, 'Cambio de carrera/institución', 'Me voy a cambiar de universidad', 'APROBADA', 'Concedida la baja', '2026-08-25 03:05:42', '2026-08-25 03:06:31'),
(10, 12, 'Inscripción por error', 'Me equivoque', 'APROBADA', 'Aprobada', '2026-08-25 03:39:58', '2026-08-25 03:40:14'),
(11, 10, 'Problemas laborales/personales', 'aas', 'PENDIENTE', NULL, '2026-08-25 06:27:09', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tareas_profesor`
--

DROP TABLE IF EXISTS `tareas_profesor`;
CREATE TABLE IF NOT EXISTS `tareas_profesor` (
  `tarea_id` int NOT NULL AUTO_INCREMENT,
  `profesor_id` int NOT NULL,
  `nrc` int NOT NULL,
  `tipo` enum('AVISO','ASIGNACION') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tarea_id`),
  KEY `profesor_id` (`profesor_id`),
  KEY `nrc` (`nrc`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tareas_profesor`
--

INSERT INTO `tareas_profesor` (`tarea_id`, `profesor_id`, `nrc`, `tipo`, `titulo`, `descripcion`, `fecha_inicio`, `fecha_fin`, `fecha_creacion`) VALUES
(5, 16, 44444, 'AVISO', 'Faltare hoy', 'Me dio gripa', '2026-08-24 23:04:00', '2026-08-31 23:04:00', '2026-08-25 05:04:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `usuario_id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_paterno` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_materno` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rol` enum('ADMIN','PROFESOR','ALUMNO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estatus` enum('ACTIVO','INACTIVO') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVO',
  `genero` enum('MASCULINO','FEMENINO','OTRO') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `periodo_ingreso` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_perfil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_cambio_password` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`usuario_id`),
  UNIQUE KEY `correo` (`correo`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`usuario_id`, `codigo`, `nombre`, `apellido_paterno`, `apellido_materno`, `correo`, `password`, `telefono`, `rol`, `estatus`, `genero`, `periodo_ingreso`, `foto_perfil`, `google_id`, `fecha_creacion`, `fecha_cambio_password`) VALUES
(1, 'AD01', 'Admin', 'Principal', NULL, 'admin@epale.com', '$2y$10$3p/LyyaWWdWxxb3/4QO97.HeZ7cHUNL.j.9hUZimIeA.Ua7iJN1fq', '', 'ADMIN', 'ACTIVO', NULL, NULL, NULL, NULL, '2026-02-18 21:47:58', NULL),
(2, 'PR01', 'Juan', 'Mendez', '', 'profe@epale.com', '$2y$10$0uLUwInpV4JgkV/gJtTWGOkcq9wZoKQNVk944UJMm76ovkOBfVBJO', '', 'PROFESOR', 'ACTIVO', 'MASCULINO', '', 'prof_2_1773183139.jpg', NULL, '2026-02-18 21:47:58', NULL),
(3, '3', 'Luis', 'Macias', 'Mendez', 'alumno@epale.com', '$2y$10$u9Tnus0MXvdfVLPfpO4fSOKU94enh4U5N61AB4j6zk6pzL3YMRjGq', '3322777085', 'ALUMNO', 'ACTIVO', 'MASCULINO', '', 'd7141781b2b45fbf01a6.webp', NULL, '2026-02-18 21:47:58', '2026-09-02 21:42:55'),
(4, '2187345', 'Jorge', 'Ledezma', 'Paredes', 'test2@gmail.com', '$2y$10$3p/LyyaWWdWxxb3/4QO97.HeZ7cHUNL.j.9hUZimIeA.Ua7iJN1fq', '3344553322', 'ALUMNO', 'ACTIVO', 'MASCULINO', '', NULL, NULL, '2026-02-18 21:58:25', NULL),
(7, '2', 'Ivan Alejandro', 'Godinez', 'Padilla', 'test3@gmail.com', '$2y$10$WKAXb1YFEvwvBvEAFnt6GO2ujXXSOuU3YuxNvX3SJ5FZj9pDfvmGO', '222334456', 'ALUMNO', 'ACTIVO', NULL, NULL, NULL, NULL, '2026-03-05 20:43:36', NULL),
(8, '01092834', 'Martin', 'Padilla', 'Yunuem', 'test9@gmail.com', '$2y$10$O/UZX7omnuXb5xyfbQMQce4zjAeXzX8N1jmQJTpkJaXXHo2HZ7Kka', '3344222233', 'PROFESOR', 'ACTIVO', NULL, '', NULL, NULL, '2026-03-05 23:03:29', NULL),
(10, '1', 'Valeria', 'Enriquez', 'Ruvalcaba', 'test10@gmail.com', '$2y$10$kyib1jrUJa/5J.AMnJCzIu53yUVFEQbEDCzGjuOIOlxNVJbPsjQl2', '', 'ALUMNO', 'ACTIVO', 'FEMENINO', NULL, NULL, NULL, '2026-03-06 20:27:09', NULL),
(11, '2000', 'pruebaprofe1', 'test2', 'test2', 'testprofe@gmail.com2', '$2y$10$TMceNURFhOrkJEvYwrVkMeDwqudabV.S.uwgWZno/PjLbB0PMTA6a', '3344224455', 'PROFESOR', 'ACTIVO', NULL, '', NULL, NULL, '2026-04-21 22:34:18', NULL),
(15, '228922367', 'JAIME ALFREDO', 'PEREZ', 'DIAZ', 'jaime@hotmail.com', '$2y$10$TC0LeJM3tuwW5J1geRqCNuda1I1HS..N1MlpSTzrOcFpD.eHCWTp.', NULL, 'ALUMNO', 'ACTIVO', 'MASCULINO', '2022B', NULL, NULL, '2026-05-06 21:33:25', NULL),
(16, '123456789', 'MARIA', 'GOMEZ', '', 'maria@gmail.com', '$2y$10$riXh.g7rFKK8fPGZQEQW9.M4hd.MZL/yQ/b839f03QzYkLhmimmAy', '', 'PROFESOR', 'ACTIVO', 'FEMENINO', '2026A', NULL, NULL, '2026-05-06 21:33:25', NULL),
(24, '222078445', 'DIEGO ROMEO', 'ABRICA', 'RIVERA', 'dictamenes@gmail.com', '$2y$10$mLDOsg43Wf6rPT8HQyehPuti0ehbFlbCL66XstQ.dibWAp2GXVhs2', '', 'ALUMNO', 'ACTIVO', 'MASCULINO', '2024A', NULL, NULL, '2026-09-08 17:07:00', NULL);

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `grupos`
--
ALTER TABLE `grupos`
  ADD CONSTRAINT `grupos_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`materia_id`),
  ADD CONSTRAINT `grupos_ibfk_2` FOREIGN KEY (`profesor_id`) REFERENCES `usuarios` (`usuario_id`),
  ADD CONSTRAINT `grupos_ibfk_3` FOREIGN KEY (`ciclo_id`) REFERENCES `ciclos` (`ciclo_id`);

--
-- Filtros para la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD CONSTRAINT `inscripciones_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`alumno_id`),
  ADD CONSTRAINT `inscripciones_ibfk_2` FOREIGN KEY (`nrc`) REFERENCES `grupos` (`nrc`);

--
-- Filtros para la tabla `tareas_profesor`
--
ALTER TABLE `tareas_profesor`
  ADD CONSTRAINT `tareas_profesor_ibfk_1` FOREIGN KEY (`profesor_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tareas_profesor_ibfk_2` FOREIGN KEY (`nrc`) REFERENCES `grupos` (`nrc`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
