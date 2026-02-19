-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 19-02-2026 a las 23:09:42
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
  `carrera` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`alumno_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `alumnos`
--

INSERT INTO `alumnos` (`alumno_id`, `usuario_id`, `carrera`) VALUES
(1, 3, 'LIME'),
(2, 4, 'LTIN');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones`
--

DROP TABLE IF EXISTS `calificaciones`;
CREATE TABLE IF NOT EXISTS `calificaciones` (
  `calificacion_id` int NOT NULL AUTO_INCREMENT,
  `inscripcion_id` int NOT NULL,
  `tipo_examen` enum('DIAGNOSTICO','TOEFL','Q1','Q2','Q3','QO1','QO2','WRITING','SPEAKING','PLATAFORMA','PARTICIPACION','FINAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `puntaje` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`calificacion_id`),
  KEY `inscripcion_id` (`inscripcion_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `calificaciones`
--

INSERT INTO `calificaciones` (`calificacion_id`, `inscripcion_id`, `tipo_examen`, `puntaje`) VALUES
(1, 1, 'Q1', 8.00),
(2, 1, 'Q2', 9.00),
(3, 1, 'Q3', 8.00),
(4, 1, 'QO1', 3.00),
(5, 1, 'QO2', 2.00),
(6, 1, 'WRITING', 5.00),
(7, 1, 'PLATAFORMA', 40.00),
(8, 1, 'PARTICIPACION', 5.00),
(9, 2, 'Q1', 8.00),
(10, 2, 'PLATAFORMA', 5.00),
(11, 1, 'TOEFL', 15.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ciclos`
--

DROP TABLE IF EXISTS `ciclos`;
CREATE TABLE IF NOT EXISTS `ciclos` (
  `ciclo_id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`ciclo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ciclos`
--

INSERT INTO `ciclos` (`ciclo_id`, `nombre`, `activo`) VALUES
(1, '2026-A', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos`
--

DROP TABLE IF EXISTS `grupos`;
CREATE TABLE IF NOT EXISTS `grupos` (
  `nrc` int NOT NULL,
  `materia_id` int NOT NULL,
  `profesor_id` int NOT NULL,
  `ciclo_id` int NOT NULL,
  `cupo` int DEFAULT '30',
  PRIMARY KEY (`nrc`),
  KEY `materia_id` (`materia_id`),
  KEY `profesor_id` (`profesor_id`),
  KEY `ciclo_id` (`ciclo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `grupos`
--

INSERT INTO `grupos` (`nrc`, `materia_id`, `profesor_id`, `ciclo_id`, `cupo`) VALUES
(60495, 1, 2, 1, 30),
(60501, 2, 2, 1, 30);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios`
--

DROP TABLE IF EXISTS `horarios`;
CREATE TABLE IF NOT EXISTS `horarios` (
  `horario_id` int NOT NULL AUTO_INCREMENT,
  `nrc` int NOT NULL,
  `dias_patron` enum('L-M','M-J','V','S') COLLATE utf8mb4_unicode_ci NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `modalidad` enum('PRESENCIAL','VIRTUAL') COLLATE utf8mb4_unicode_ci NOT NULL,
  `aula` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`horario_id`),
  KEY `nrc` (`nrc`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `horarios`
--

INSERT INTO `horarios` (`horario_id`, `nrc`, `dias_patron`, `hora_inicio`, `hora_fin`, `modalidad`, `aula`) VALUES
(1, 60495, 'L-M', '14:00:00', '16:00:00', 'PRESENCIAL', 'AULA 202'),
(2, 60501, 'M-J', '16:00:00', '18:00:00', 'PRESENCIAL', 'AULA 101');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones`
--

DROP TABLE IF EXISTS `inscripciones`;
CREATE TABLE IF NOT EXISTS `inscripciones` (
  `inscripcion_id` int NOT NULL AUTO_INCREMENT,
  `alumno_id` int NOT NULL,
  `nrc` int NOT NULL,
  `estatus` enum('INSCRITO','SOLICITUD_BAJA','BAJA','SOLICITUD_ALTA') COLLATE utf8mb4_unicode_ci DEFAULT 'INSCRITO',
  `calificacion_final` decimal(5,2) DEFAULT '0.00',
  PRIMARY KEY (`inscripcion_id`),
  KEY `alumno_id` (`alumno_id`),
  KEY `nrc` (`nrc`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `inscripciones`
--

INSERT INTO `inscripciones` (`inscripcion_id`, `alumno_id`, `nrc`, `estatus`, `calificacion_final`) VALUES
(1, 1, 60495, 'INSCRITO', 0.00),
(2, 1, 60501, 'INSCRITO', 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

DROP TABLE IF EXISTS `materias`;
CREATE TABLE IF NOT EXISTS `materias` (
  `materia_id` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel` int NOT NULL,
  PRIMARY KEY (`materia_id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`materia_id`, `clave`, `nombre`, `nivel`) VALUES
(1, 'I0004', 'Inglés', 4),
(2, 'F0001', 'Francés', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `usuario_id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `correo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rol` enum('ADMIN','PROFESOR','ALUMNO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `estatus` enum('ACTIVO','INACTIVO') COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVO',
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_id`),
  UNIQUE KEY `correo` (`correo`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`usuario_id`, `codigo`, `nombre`, `apellidos`, `correo`, `password`, `telefono`, `rol`, `estatus`, `google_id`, `fecha_creacion`) VALUES
(1, 'AD01', 'Admin', 'Principal', 'admin@epale.com', '$2y$10$dd6SmtLKkEobR0p5h.ynFOkA4VoZqOUZzyO24SPEp8BbLqPGORVnC', NULL, 'ADMIN', 'ACTIVO', NULL, '2026-02-18 21:47:58'),
(2, 'PR01', 'Juan', 'Mendez', 'profe@epale.com', '$2y$10$dd6SmtLKkEobR0p5h.ynFOkA4VoZqOUZzyO24SPEp8BbLqPGORVnC', NULL, 'PROFESOR', 'ACTIVO', NULL, '2026-02-18 21:47:58'),
(3, '219511643', 'Luis', 'Alumno', 'alumno@epale.com', '$2y$10$dd6SmtLKkEobR0p5h.ynFOkA4VoZqOUZzyO24SPEp8BbLqPGORVnC', '3322777085', 'ALUMNO', 'ACTIVO', NULL, '2026-02-18 21:47:58'),
(4, '222222', 'Ivan Alejandro', 'Padilla Varela', 'test2@gmail.com', '$2y$10$GDGrmQFD53PEfhL3i02SY.KlJUZsfv/uejp6kWhuLKFcSe6ETNovW', '3344553322', 'ALUMNO', 'ACTIVO', NULL, '2026-02-18 21:58:25');

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  ADD CONSTRAINT `calificaciones_ibfk_1` FOREIGN KEY (`inscripcion_id`) REFERENCES `inscripciones` (`inscripcion_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `grupos`
--
ALTER TABLE `grupos`
  ADD CONSTRAINT `grupos_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`materia_id`),
  ADD CONSTRAINT `grupos_ibfk_2` FOREIGN KEY (`profesor_id`) REFERENCES `usuarios` (`usuario_id`),
  ADD CONSTRAINT `grupos_ibfk_3` FOREIGN KEY (`ciclo_id`) REFERENCES `ciclos` (`ciclo_id`);

--
-- Filtros para la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `horarios_ibfk_1` FOREIGN KEY (`nrc`) REFERENCES `grupos` (`nrc`) ON DELETE CASCADE;

--
-- Filtros para la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD CONSTRAINT `inscripciones_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`alumno_id`),
  ADD CONSTRAINT `inscripciones_ibfk_2` FOREIGN KEY (`nrc`) REFERENCES `grupos` (`nrc`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
