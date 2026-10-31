-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 27-10-2026 a las 21:39:02
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(12, 24, 'LAFI'),
(14, 26, ''),
(15, 27, 'LTIN');

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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(25, 6, '2026-09-08', 'ASISTENCIA'),
(26, 21, '2026-09-08', 'ASISTENCIA'),
(27, 6, '2026-09-11', 'ASISTENCIA'),
(28, 6, '2026-09-18', 'ASISTENCIA'),
(29, 21, '2026-09-18', 'ASISTENCIA');

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
  `rol_destinatario` enum('TODOS','ALUMNO','PROFESOR') NOT NULL DEFAULT 'TODOS',
  `audiencia_ref` varchar(100) DEFAULT NULL,
  `autor_id` int DEFAULT NULL,
  `ciclo_id` int DEFAULT NULL,
  `tipo_comunicado` enum('AVISO','ASIGNACION') NOT NULL DEFAULT 'AVISO',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_expiracion` datetime DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`aviso_id`),
  KEY `fk_avisos_autor` (`autor_id`),
  KEY `idx_avisos_ciclo` (`ciclo_id`),
  KEY `idx_avisos_rol_dest` (`rol_destinatario`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `avisos`
--

INSERT INTO `avisos` (`aviso_id`, `titulo`, `cuerpo`, `tipo_audiencia`, `rol_destinatario`, `audiencia_ref`, `autor_id`, `ciclo_id`, `tipo_comunicado`, `fecha_creacion`, `fecha_inicio`, `fecha_expiracion`, `imagen`) VALUES
(4, 'XD', 'se cancela la clase de mañana', 'MATERIA', 'TODOS', '3', NULL, 1, 'AVISO', '2026-04-23 00:32:15', NULL, NULL, NULL),
(6, 'Prueba Imagen', 'Prueba Imagen: Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris et pharetra urna, a porttitor nisl. Donec posuere erat quis sapien gravida, sed vestibulum tortor bibendum. Donec ullamcorper efficitur turpis vitae vulputate. Maecenas sit amet erat purus. Morbi pellentesque, dui et semper vulputate, lacus dui congue purus, vitae dictum sem orci id ipsum. Ut eget tellus eget leo fermentum aliquam quis nec quam. Praesent diam elit, mollis ut nulla ut, feugiat consectetur orci. Cras mollis, erat a vestibulum laoreet, enim purus iaculis libero, ac aliquam sem est non nulla. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Nam risus velit, hendrerit nec accumsan eu, cursus in ex. Sed facilisis vehicula tincidunt. Nulla ut vulputate eros, sit amet vehicula metus. Proin quis ullamcorper nisl.', 'GLOBAL', 'TODOS', 'ALL', NULL, 1, 'AVISO', '2026-10-13 21:37:47', NULL, NULL, 'img/avisos/0c1cc8b3d29b88e76d1d.webp'),
(7, 'Prueba Correos', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus molestie dolor non tellus facilisis, sed bibendum elit posuere. Aenean convallis mauris sed felis imperdiet, id fringilla ex pellentesque. Cras felis mauris, varius et laoreet sed, sagittis finibus arcu. Sed faucibus mollis maximus. Donec nec leo vel est posuere vehicula et at massa. Curabitur ultrices lobortis libero elementum convallis. Mauris interdum dui a facilisis sagittis. Nulla vestibulum lacus aliquam arcu imperdiet euismod eget nec velit. Vivamus ac suscipit risus. Vivamus a cursus elit. Mauris venenatis eu nisi vitae mollis. Integer accumsan enim efficitur, iaculis metus nec, tristique ligula. Phasellus erat ligula, sagittis id justo a, ultricies gravida nulla. Morbi rhoncus tincidunt mauris, in pellentesque quam dapibus sed.', 'GLOBAL', 'TODOS', 'ALL', NULL, 1, 'AVISO', '2026-10-15 22:13:29', NULL, NULL, 'img/avisos/53e620d0a92e292ec21b.webp'),
(8, 'Prueba Correos 2', 'Pellentesque posuere imperdiet magna vitae finibus. Maecenas vulputate consequat mauris non rhoncus. Quisque accumsan neque in pharetra ultrices. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed sem massa, auctor eu auctor at, aliquet sit amet mi. Vivamus luctus est odio. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Curabitur cursus orci orci, eget porta nisi elementum ornare. Nullam in dolor non purus viverra aliquam vitae sed lectus. Donec eget est non odio ultrices egestas. In feugiat quam at mollis hendrerit. Morbi tristique vel mi eget ullamcorper. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Aliquam leo libero, tristique sed fringilla a, sagittis sodales dui. Duis augue magna, posuere et rhoncus pulvinar, lobortis at felis. Sed nec leo dapibus, ullamcorper est eu, sodales ipsum.', 'GLOBAL', 'TODOS', 'ALL', NULL, 1, 'AVISO', '2026-10-15 22:27:02', NULL, NULL, 'img/avisos/0c7618ad195b6e332dac.webp'),
(9, 'Prueba Correos 3', 'Maecenas nec volutpat elit, in lacinia sem. Proin aliquet fermentum nulla. Pellentesque pellentesque quis risus non pulvinar. Phasellus blandit euismod efficitur. Donec nec volutpat arcu, eu vestibulum orci. Nullam egestas dignissim lobortis. Nullam lacinia pellentesque justo, quis aliquet sem lobortis et. Vivamus molestie hendrerit lectus at fringilla. Praesent tempus urna pretium tincidunt efficitur.\r\n\r\nNunc in ornare nisl. Etiam venenatis ligula sed magna tincidunt finibus. Ut sit amet blandit ex. Vivamus diam justo, luctus sit amet ultrices vel, dapibus at eros. Etiam sed risus lectus. Nunc finibus ac orci in condimentum. Donec in luctus risus. In vitae ante sit amet urna tristique iaculis vehicula id massa. Quisque nisi quam, suscipit vel mattis eget, malesuada sit amet orci. Curabitur purus ex, venenatis a lectus sed, vehicula laoreet nunc. Nam sit amet elit nec enim elementum tincidunt. Cras id dolor metus. Pellentesque blandit tempor convallis. Donec vehicula varius nunc eget sodales.', 'GLOBAL', 'TODOS', 'ALL', NULL, 1, 'AVISO', '2026-10-15 22:33:36', NULL, NULL, 'img/avisos/30e94051300b6612cccd.webp'),
(10, 'Prueba Correos 4', 'Maecenas nec volutpat elit, in lacinia sem. Proin aliquet fermentum nulla. Pellentesque pellentesque quis risus non pulvinar. Phasellus blandit euismod efficitur. Donec nec volutpat arcu, eu vestibulum orci. Nullam egestas dignissim lobortis. Nullam lacinia pellentesque justo, quis aliquet sem lobortis et. Vivamus molestie hendrerit lectus at fringilla. Praesent tempus urna pretium tincidunt efficitur.\r\n\r\nNunc in ornare nisl. Etiam venenatis ligula sed magna tincidunt finibus. Ut sit amet blandit ex. Vivamus diam justo, luctus sit amet ultrices vel, dapibus at eros. Etiam sed risus lectus. Nunc finibus ac orci in condimentum. Donec in luctus risus. In vitae ante sit amet urna tristique iaculis vehicula id massa. Quisque nisi quam, suscipit vel mattis eget, malesuada sit amet orci. Curabitur purus ex, venenatis a lectus sed, vehicula laoreet nunc. Nam sit amet elit nec enim elementum tincidunt. Cras id dolor metus. Pellentesque blandit tempor convallis. Donec vehicula varius nunc eget sodales.\r\n\r\nNulla lacinia mauris a lacus elementum faucibus. Integer vulputate pulvinar nisi. Morbi porttitor bibendum arcu, sed auctor dolor dictum vel. Sed consequat bibendum convallis. Etiam nibh magna, consectetur quis dolor ac, pretium lobortis nisl. Duis laoreet tellus id pellentesque feugiat. Sed cursus ligula eu velit tincidunt, ac vehicula risus elementum. Suspendisse ut nunc nibh. Donec vulputate efficitur ex eu condimentum. Sed accumsan scelerisque lacinia. Duis gravida pellentesque tortor vel dignissim.', 'GLOBAL', 'TODOS', 'ALL', NULL, 1, 'AVISO', '2026-10-15 22:43:45', NULL, NULL, 'img/avisos/2c5bb3c35f6d66364cfa.webp'),
(11, 'Prueba Correos 5', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus molestie dolor non tellus facilisis, sed bibendum elit posuere. Aenean convallis mauris sed felis imperdiet, id fringilla ex pellentesque. Cras felis mauris, varius et laoreet sed, sagittis finibus arcu. Sed faucibus mollis maximus. Donec nec leo vel est posuere vehicula et at massa. Curabitur ultrices lobortis libero elementum convallis. Mauris interdum dui a facilisis sagittis. Nulla vestibulum lacus aliquam arcu imperdiet euismod eget nec velit. Vivamus ac suscipit risus. Vivamus a cursus elit. Mauris venenatis eu nisi vitae mollis. Integer accumsan enim efficitur, iaculis metus nec, tristique ligula. Phasellus erat ligula, sagittis id justo a, ultricies gravida nulla. Morbi rhoncus tincidunt mauris, in pellentesque quam dapibus sed.\r\n\r\nPellentesque posuere imperdiet magna vitae finibus. Maecenas vulputate consequat mauris non rhoncus. Quisque accumsan neque in pharetra ultrices. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed sem massa, auctor eu auctor at, aliquet sit amet mi. Vivamus luctus est odio. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Curabitur cursus orci orci, eget porta nisi elementum ornare. Nullam in dolor non purus viverra aliquam vitae sed lectus. Donec eget est non odio ultrices egestas. In feugiat quam at mollis hendrerit. Morbi tristique vel mi eget ullamcorper. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Aliquam leo libero, tristique sed fringilla a, sagittis sodales dui. Duis augue magna, posuere et rhoncus pulvinar, lobortis at felis. Sed nec leo dapibus, ullamcorper est eu, sodales ipsum.', 'GLOBAL', 'TODOS', 'ALL', NULL, 1, 'AVISO', '2026-10-16 00:13:02', NULL, NULL, 'img/avisos/7faece0f6b4ae7251f29.webp'),
(12, 'Prueba Correos 6', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus molestie dolor non tellus facilisis, sed bibendum elit posuere. Aenean convallis mauris sed felis imperdiet, id fringilla ex pellentesque. Cras felis mauris, varius et laoreet sed, sagittis finibus arcu. Sed faucibus mollis maximus. Donec nec leo vel est posuere vehicula et at massa. Curabitur ultrices lobortis libero elementum convallis. Mauris interdum dui a facilisis sagittis. Nulla vestibulum lacus aliquam arcu imperdiet euismod eget nec velit. Vivamus ac suscipit risus. Vivamus a cursus elit. Mauris venenatis eu nisi vitae mollis. Integer accumsan enim efficitur, iaculis metus nec, tristique ligula. Phasellus erat ligula, sagittis id justo a, ultricies gravida nulla. Morbi rhoncus tincidunt mauris, in pellentesque quam dapibus sed.\r\n\r\nPellentesque posuere imperdiet magna vitae finibus. Maecenas vulputate consequat mauris non rhoncus. Quisque accumsan neque in pharetra ultrices. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed sem massa, auctor eu auctor at, aliquet sit amet mi. Vivamus luctus est odio. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Curabitur cursus orci orci, eget porta nisi elementum ornare. Nullam in dolor non purus viverra aliquam vitae sed lectus. Donec eget est non odio ultrices egestas. In feugiat quam at mollis hendrerit. Morbi tristique vel mi eget ullamcorper. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Aliquam leo libero, tristique sed fringilla a, sagittis sodales dui. Duis augue magna, posuere et rhoncus pulvinar, lobortis at felis. Sed nec leo dapibus, ullamcorper est eu, sodales ipsum.\r\n\r\nMaecenas nec volutpat elit, in lacinia sem. Proin aliquet fermentum nulla. Pellentesque pellentesque quis risus non pulvinar. Phasellus blandit euismod efficitur. Donec nec volutpat arcu, eu vestibulum orci. Nullam egestas dignissim lobortis. Nullam lacinia pellentesque justo, quis aliquet sem lobortis et. Vivamus molestie hendrerit lectus at fringilla. Praesent tempus urna pretium tincidunt efficitur.\r\n\r\nNunc in ornare nisl. Etiam venenatis ligula sed magna tincidunt finibus. Ut sit amet blandit ex. Vivamus diam justo, luctus sit amet ultrices vel, dapibus at eros. Etiam sed risus lectus. Nunc finibus ac orci in condimentum. Donec in luctus risus. In vitae ante sit amet urna tristique iaculis vehicula id massa. Quisque nisi quam, suscipit vel mattis eget, malesuada sit amet orci. Curabitur purus ex, venenatis a lectus sed, vehicula laoreet nunc. Nam sit amet elit nec enim elementum tincidunt. Cras id dolor metus. Pellentesque blandit tempor convallis. Donec vehicula varius nunc eget sodales.\r\n\r\nNulla lacinia mauris a lacus elementum faucibus. Integer vulputate pulvinar nisi. Morbi porttitor bibendum arcu, sed auctor dolor dictum vel. Sed consequat bibendum convallis. Etiam nibh magna, consectetur quis dolor ac, pretium lobortis nisl. Duis laoreet tellus id pellentesque feugiat. Sed cursus ligula eu velit tincidunt, ac vehicula risus elementum. Suspendisse ut nunc nibh. Donec vulputate efficitur ex eu condimentum. Sed accumsan scelerisque lacinia. Duis gravida pellentesque tortor vel dignissim.', 'GLOBAL', 'TODOS', 'ALL', NULL, 1, 'AVISO', '2026-10-16 00:39:54', NULL, NULL, 'img/avisos/75c523d378ae5ee0def3.webp'),
(13, 'Prueba Correos 7', 'Suspendisse quam leo, luctus ac pellentesque vitae, pellentesque at diam. Sed eleifend lorem sit amet ipsum faucibus scelerisque. Donec in arcu gravida, aliquet erat vel, pulvinar velit. Ut non sem convallis, bibendum dui et, condimentum mi. Aliquam in dapibus est, sit amet cursus nisi. Pellentesque lacinia tempor elit. Mauris tristique est eu arcu tincidunt, at bibendum odio cursus. Duis et ultrices turpis. Phasellus pellentesque lobortis eros, eget pulvinar felis auctor non. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Curabitur gravida, sem ultrices fermentum consectetur, ligula lectus consequat nisl, ac volutpat quam enim vitae sem.\r\n\r\nCurabitur vel nulla semper, ultricies elit tempus, pretium odio. Vivamus id arcu ultrices, accumsan mi non, condimentum purus. Praesent aliquam dui vel nibh faucibus, a molestie leo sollicitudin. Praesent euismod diam turpis, sit amet lacinia orci pulvinar in. Nunc tempus auctor felis non sollicitudin. Etiam placerat tellus ut suscipit pretium. Duis lobortis, ipsum non bibendum interdum, lorem massa sodales magna, vitae finibus nisi eros id ex. Aenean nec sapien auctor, bibendum mauris a, tincidunt neque. Maecenas at sem non lorem porta dignissim vitae ut tellus. Donec sed felis libero. Praesent tincidunt, arcu vel rhoncus mattis, nisl neque consequat urna, non maximus arcu leo in quam. In molestie lacus diam, eu tincidunt nunc auctor ac. Fusce cursus auctor ultrices. Aliquam a pulvinar mi.', 'GLOBAL', 'ALUMNO', 'ALL', NULL, 1, 'AVISO', '2026-10-23 01:05:29', NULL, NULL, 'img/avisos/36c7123a65a97a3be292.webp'),
(14, 'Prueba Correos 7', 'Nunc a ipsum nisi. Quisque fermentum eleifend efficitur. Aliquam in ligula at mi convallis lacinia sit amet quis leo. Curabitur nec magna at velit placerat feugiat. Nulla ultrices ullamcorper urna. Nullam in sapien hendrerit, lacinia ligula a, venenatis dui. Integer eleifend, arcu et pharetra facilisis, enim erat varius mi, at imperdiet nulla lectus eu tortor. Phasellus laoreet ante at tempus sollicitudin. Sed pulvinar massa ac arcu mollis, at molestie turpis tincidunt. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Integer ultricies enim nec dolor rhoncus rhoncus. Integer eleifend rutrum turpis eu maximus.\r\n\r\nInteger placerat massa id ipsum tempor sodales ac non tellus. Nulla at orci massa. Cras libero augue, pretium at sagittis at, lacinia a libero. Sed sit amet lorem in ligula tristique imperdiet in eget libero. Duis sit amet mauris eu urna gravida viverra nec in ante. Praesent dapibus ut turpis nec rhoncus. Proin at ex ac nisl euismod eleifend. In finibus tortor quam, id consectetur orci porta sit amet. Nam leo magna, aliquam non mattis id, mollis vel nisi. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Vivamus non vehicula mi.', 'GLOBAL', 'ALUMNO', 'ALL', NULL, 1, 'AVISO', '2026-10-23 01:18:47', NULL, NULL, 'img/avisos/8335b0b513e1531c5d50.webp');

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
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(20, 6, 'QO2', 5.00),
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
(56, 10, 'PLATAFORMA', 44.00),
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
(118, 21, 'QO1', 0.00),
(119, 22, 'CERTIFICACION', 10.00);

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
(1, 1, 'Inglés', '290', 'B2', '2027B', '2027-02-15', '2026-03-20 19:04:32'),
(2, 1, 'Francés', '200', 'C1', '2022B', '2022-09-11', '2026-04-15 22:10:18'),
(3, 1, 'JAPONES', '550', 'C2', '2022B', '2022-08-31', '2026-05-08 21:52:28'),
(4, 1, 'Aleman', '555', 'C2', '2027B', '2027-02-14', '2026-09-02 21:48:57');

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
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(100, 11, 'Certificación', 'CERTIFICACION', 'Examen de Certificación', 10.00, 'fa-certificate', '#6f42c1'),
(101, 12, 'Exámenes', 'Q1', 'Examen 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(102, 12, 'Exámenes', 'Q2', 'Examen 2', 10.00, 'fa-book-open', 'var(--udg-light)'),
(103, 12, 'Exámenes', 'Q3', 'Examen 3', 10.00, 'fa-book-open', 'var(--udg-light)'),
(104, 12, 'Exámenes Orales', 'QO1', 'Examen Oral 1', 5.00, 'fa-comments', '#28a745'),
(105, 12, 'Exámenes Orales', 'QO2', 'Examen Oral 2', 5.00, 'fa-comments', '#28a745'),
(106, 12, 'Proyectos', 'WRITING', 'Proyecto Escrito', 15.00, 'fa-file-signature', '#ffc107'),
(107, 12, 'Participación', 'PARTICIPACION', 'Participación en Clase', 5.00, 'fa-hand-paper', '#17a2b8'),
(108, 12, 'Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 40.00, 'fa-laptop-code', '#dc3545'),
(110, 13, 'Exámenes', 'Q1', 'Examen 1', 10.00, 'fa-book-open', 'var(--udg-light)'),
(111, 13, 'Exámenes', 'Q2', 'Examen 2', 10.00, 'fa-book-open', 'var(--udg-light)'),
(112, 13, 'Exámenes', 'Q3', 'Examen 3', 10.00, 'fa-book-open', 'var(--udg-light)'),
(113, 13, 'Exámenes Orales', 'QO1', 'Examen Oral 1', 5.00, 'fa-comments', '#28a745'),
(114, 13, 'Exámenes Orales', 'QO2', 'Examen Oral 2', 5.00, 'fa-comments', '#28a745'),
(115, 13, 'Proyectos', 'WRITING', 'Proyecto Escrito', 5.00, 'fa-file-signature', '#ffc107'),
(116, 13, 'Participación', 'PARTICIPACION', 'Participación en Clase', 5.00, 'fa-hand-paper', '#17a2b8'),
(117, 13, 'Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 50.00, 'fa-laptop-code', '#dc3545');

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
-- Estructura de tabla para la tabla `equipos_grupo`
--

DROP TABLE IF EXISTS `equipos_grupo`;
CREATE TABLE IF NOT EXISTS `equipos_grupo` (
  `equipo_id` int NOT NULL AUTO_INCREMENT,
  `clave_grupo` varchar(50) NOT NULL,
  `nombre_equipo` varchar(50) NOT NULL,
  `color_hex` varchar(20) NOT NULL,
  `fecha_creacion` datetime NOT NULL,
  PRIMARY KEY (`equipo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `equipos_grupo`
--

INSERT INTO `equipos_grupo` (`equipo_id`, `clave_grupo`, `nombre_equipo`, `color_hex`, `fecha_creacion`) VALUES
(7, '60966', 'Equipo 1', '#3b5bdb', '2026-10-02 17:15:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipo_integrantes`
--

DROP TABLE IF EXISTS `equipo_integrantes`;
CREATE TABLE IF NOT EXISTS `equipo_integrantes` (
  `integrante_id` int NOT NULL AUTO_INCREMENT,
  `equipo_id` int NOT NULL,
  `inscripcion_id` int NOT NULL,
  PRIMARY KEY (`integrante_id`),
  KEY `equipo_id` (`equipo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `equipo_integrantes`
--

INSERT INTO `equipo_integrantes` (`integrante_id`, `equipo_id`, `inscripcion_id`) VALUES
(7, 7, 6),
(8, 7, 22);

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
(1, 1, 'INGLES', 'A2 INICAL', 3, '2022-08-16', '2022-B', '2026-04-16 22:39:49'),
(2, 1, 'Frances', 'A3 AVANZADO', 1, '2022-04-04', '2022-B', '2026-04-30 01:00:54'),
(3, 1, 'JAPONES', 'A2 INICIAL', 2, '2022-01-21', '2022B', '2026-05-07 23:10:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `exportaciones`
--

DROP TABLE IF EXISTS `exportaciones`;
CREATE TABLE IF NOT EXISTS `exportaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `estado` enum('PENDIENTE','PROCESANDO','COMPLETADO','ERROR') DEFAULT 'PENDIENTE',
  `progreso` int DEFAULT '0',
  `archivo_ruta` varchar(255) DEFAULT NULL,
  `fecha_solicitud` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_completado` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `exportaciones`
--

INSERT INTO `exportaciones` (`id`, `admin_id`, `tipo`, `estado`, `progreso`, `archivo_ruta`, `fecha_solicitud`, `fecha_completado`) VALUES
(4, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260929_234416_ID4.zip', '2026-09-29 17:44:16', '2026-09-29 17:44:16'),
(5, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_001756_ID5.zip', '2026-09-29 18:17:56', '2026-09-29 18:17:56'),
(6, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_003106_ID6.zip', '2026-09-29 18:31:05', '2026-09-29 18:31:06'),
(7, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_003722_ID7.zip', '2026-09-29 18:37:22', '2026-09-29 18:37:22'),
(8, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_005335_ID8.zip', '2026-09-29 18:53:35', '2026-09-29 18:53:35'),
(9, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_010219_ID9.zip', '2026-09-29 19:02:19', '2026-09-29 19:02:19'),
(10, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_012059_ID10.zip', '2026-09-29 19:20:59', '2026-09-29 19:21:00'),
(11, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_013459_ID11.zip', '2026-09-29 19:34:59', '2026-09-29 19:34:59'),
(12, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_014526_ID12.zip', '2026-09-29 19:45:26', '2026-09-29 19:45:26'),
(13, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_015108_ID13.zip', '2026-09-29 19:51:08', '2026-09-29 19:51:08'),
(14, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_211757_ID14.zip', '2026-09-30 15:17:56', '2026-09-30 15:17:57'),
(15, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_222341_ID15.zip', '2026-09-30 16:23:40', '2026-09-30 16:23:41'),
(17, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_223930_ID17.zip', '2026-09-30 16:39:29', '2026-09-30 16:39:30'),
(18, 1, 'CARRERA_LAFI', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_Global_20260930_231042_ID18.zip', '2026-09-30 17:10:41', '2026-09-30 17:10:42'),
(19, 1, 'CARRERA_LAFI', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_CARRERA_LAFI_20260930_232422_ID19.zip', '2026-09-30 17:24:22', '2026-09-30 17:24:22'),
(20, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_GLOBAL_20261015_003351_ID20.zip', '2026-10-14 18:33:49', '2026-10-14 18:33:51'),
(21, 1, 'GLOBAL', 'COMPLETADO', 100, 'descarga_masiva/archivos/Exportacion_GLOBAL_20261015_004526_ID21.zip', '2026-10-14 18:45:25', '2026-10-14 18:45:26');

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
(1, NULL, 12, 11, 1, 30, 0, '7', 'ACTIVO'),
(900, NULL, 12, 11, 1, 30, 0, 'grp_6ac6bc5bebdc1', 'ACTIVO'),
(8888, NULL, 5, 16, 1, 30, 0, '6', 'ACTIVO'),
(9090, NULL, 4, 2, 1, 30, 0, '60966', 'ACTIVO'),
(9876, NULL, 1, 11, 1, 30, 0, 'grp_6ac6c1128bb17', 'ACTIVO'),
(9999, NULL, 5, 16, 1, 30, 0, '6', 'ACTIVO'),
(35436, NULL, 3, 16, 1, 30, 0, 'LMAO', 'ACTIVO'),
(44444, NULL, 4, 16, 3, 30, 0, '4', 'CERRADO'),
(60966, NULL, 4, 2, 1, 30, 0, '60966', 'ACTIVO'),
(122222, NULL, 11, 2, 1, 30, 0, '3', 'ACTIVO'),
(122223, NULL, 5, 16, 1, 30, 0, '2', 'ACTIVO'),
(134543, NULL, 11, 2, 1, 30, 0, '1', 'ACTIVO'),
(199517, 'CU183', 7, 8, 1, 30, 0, 'JN.13.1.JN', 'ACTIVO'),
(2133213, NULL, 9, 8, 3, 30, 0, '8', 'CERRADO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_admin`
--

DROP TABLE IF EXISTS `historial_admin`;
CREATE TABLE IF NOT EXISTS `historial_admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `tipo_accion` varchar(50) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `afectado` varchar(255) NOT NULL,
  `detalle` text NOT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `historial_admin`
--

INSERT INTO `historial_admin` (`id`, `admin_id`, `tipo_accion`, `categoria`, `titulo`, `afectado`, `detalle`, `fecha`) VALUES
(17, 1, 'Estado', 'Usuarios', 'Cambio de estado de cuenta', 'Juan Mendez (PR01)', 'Se cambió el estado de Activo a Inactivo al usuario.', '2026-09-14 17:16:12'),
(18, 1, 'Calificación', 'Calificaciones', 'Modificación de calificaciones', 'Luis Macias (3)', 'Se actualizaron manualmente los registros de calificaciones del estudiante desde su expediente.', '2026-09-14 17:16:46'),
(19, 1, 'Edición', 'Usuarios', 'Cambio de rol de usuario', 'DIEGO ROMEO ABRICA (222078445)', 'Se actualizó el rol del usuario de Alumno a Admin.', '2026-09-14 17:17:37'),
(20, 1, 'Edición', 'Usuarios', 'Cambio de rol de usuario', 'DIEGO ROMEO ABRICA (222078445)', 'Se actualizó el rol del usuario de Admin a Alumno.', '2026-09-14 17:17:55'),
(21, 1, 'Baja', 'Grupos', 'Baja de estudiante', '', 'Se dio de baja al estudiante del grupo con NRC 1.', '2026-09-14 18:02:23'),
(22, 1, 'Creación', 'Clases', 'Plantilla de criterios generada', 'IRLANDES 1', 'Se generaron automáticamente los criterios base de evaluación para la materia (100 puntos en total).', '2026-09-14 18:06:01'),
(23, 1, 'Edición', 'Clases', 'Criterios de evaluación actualizados', 'IRLANDES 1', 'Se modificó el criterio de evaluación \'Actividades en Plataforma\' (PLATAFORMA) en la materia.', '2026-09-14 18:06:33'),
(24, 1, 'Edición', 'Clases', 'Criterios de evaluación actualizados', 'IRLANDES 1', 'Se modificó el criterio de evaluación \'Proyecto Escrito\' (WRITING) en la materia.', '2026-09-14 18:06:44'),
(25, 1, 'Edición', 'Clases', 'Criterios de evaluación actualizados', 'IRLANDES 1', 'Se modificó el criterio de evaluación \'Proyecto Escrito\' (WRITING) en la materia.', '2026-09-14 18:06:53'),
(26, 1, 'Creación', 'Clases', 'Criterios de evaluación actualizados', 'IRLANDES 1', 'Se agregó el criterio de evaluación \'Congreso Irlandes\' (PARTICIPACION) por 5 pts a la materia.', '2026-09-14 18:08:32'),
(27, 1, 'Edición', 'Archivos', 'Registro de certificación', 'Luis Macias (3)', 'Se actualizó la certificación de INGLÉS (Nivel: B2) en el expediente del estudiante.', '2026-09-14 18:09:53'),
(28, 1, 'Nivel', 'Usuarios', 'Examen diagnóstico guardado', 'Luis Macias (3)', 'Se editó el resultado del examen diagnóstico de INGLES en el expediente del estudiante.', '2026-09-14 18:10:24'),
(29, 1, 'Edición', 'Archivos', 'Registro de certificación', 'Luis Macias (3)', 'Se actualizó la certificación de INGLÉS (Nivel: B3) en el expediente del estudiante.', '2026-09-14 18:19:25'),
(30, 1, 'Edición', 'Archivos', 'Registro de certificación', 'Luis Macias (3)', 'Se actualizó la certificación de ALEMAN (Nivel: C2) en el expediente del estudiante.', '2026-09-14 18:21:01'),
(31, 1, 'Calificación', 'Calificaciones', 'Modificación de calificaciones', 'Luis Macias (3)', 'PLATAFORMA: 50 pts → 30 pts', '2026-09-14 18:27:37'),
(32, 1, 'Edición', 'Archivos', 'Registro de certificación', 'Luis Macias (3)', 'Se actualizó la certificación de INGLÉS. Nivel: B3 → B1', '2026-09-14 18:28:06'),
(33, 1, 'Edición', 'Archivos', 'Registro de certificación', 'Luis Macias (3)', 'Se actualizó la certificación de INGLÉS. Puntaje: 280 → 290', '2026-09-14 18:28:14'),
(34, 1, 'Nivel', 'Usuarios', 'Examen diagnóstico guardado', 'Luis Macias (3)', 'Se editó el resultado del examen diagnóstico de JAPONES. Nivel: 2 → 3', '2026-09-14 18:28:19'),
(35, 1, 'Calificación', 'Calificaciones', 'Modificación de calificaciones', 'Luis Macias (3)', 'Se actualizaron manualmente los registros de calificaciones del estudiante desde su expediente (PLATAFORMA: 30 pts → 40 pts).', '2026-09-14 18:32:13'),
(36, 1, 'Edición', 'Archivos', 'Registro de certificación', 'Luis Macias (3)', 'Se actualizó la certificación de INGLÉS (Nivel: B1 → B2) en el expediente del estudiante.', '2026-09-14 18:32:27'),
(37, 1, 'Nivel', 'Usuarios', 'Examen diagnóstico guardado', 'Luis Macias (3)', 'Se editó el resultado del examen diagnóstico de JAPONES (Nivel: 3 → 2) en el expediente del estudiante.', '2026-09-14 18:32:35'),
(38, 1, 'Calificación', 'Calificaciones', 'Modificación de calificaciones', 'Luis Macias (3)', 'Se actualizaron manualmente los registros de calificaciones del estudiante desde su expediente (PLATAFORMA: 40 pts → 44 pts).', '2026-09-14 18:36:34'),
(39, 1, 'Edición', 'Clases', 'Criterios de evaluación actualizados', 'IRLANDES 1', 'Se modificó el criterio de evaluación \'Proyecto Escrito\' (WRITING) en la materia.', '2026-09-14 18:37:09'),
(40, 1, 'Edición', 'Clases', 'Criterios de evaluación actualizados', 'IRLANDES 1', 'Se modificó el criterio de evaluación \'Proyecto Escrito\' (WRITING) en la materia.', '2026-09-14 18:37:16'),
(41, 1, 'Edición', 'Clases', 'Criterios de evaluación actualizados', 'IRLANDES 1', 'Se modificó el criterio de evaluación \'Proyecto Escrito\' (Puntos: 10.00 pts → 15 pts) en la materia.', '2026-09-14 18:42:03'),
(42, 1, 'Borrado', 'Clases', 'Criterio eliminado', 'IRLANDES 1', 'Se eliminó el criterio de evaluación \'Congreso Irlandes\' de la materia.', '2026-09-14 18:42:08'),
(43, 1, 'Edición', 'Usuarios', 'Foto de perfil eliminada', 'Luis Macias (3)', 'Se eliminó manualmente la foto de perfil del expediente del usuario.', '2026-09-14 18:46:48'),
(44, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'DIEGO ROMEO ABRICA (222078445)', 'Se actualizaron los datos personales, de contacto o credenciales del usuario en el sistema.', '2026-09-14 18:49:19'),
(45, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'DIEGO ROMEO ABRICA (222078445)', 'Se actualizaron los datos del usuario (Teléfono: 000000 → 00000122).', '2026-09-14 18:59:59'),
(46, 1, 'Estado', 'Usuarios', 'Cambio de estado de cuenta', 'DIEGO ROMEO ABRICA (222078445)', 'Se cambió el estado de cuenta al usuario (Estado: Activo → Inactivo).', '2026-09-14 19:01:43'),
(47, 1, 'Estado', 'Usuarios', 'Cambio de estado de cuenta', 'DIEGO ROMEO ABRICA (222078445)', 'Se cambió el estado de cuenta al usuario (Estado: Inactivo → Activo).', '2026-09-14 19:02:10'),
(48, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'DIEGO ROMEO ABRICA (222078445)', 'Se actualizaron los datos del usuario (Ape. Materno: RIVERA → MARTINEZ).', '2026-09-14 19:02:10'),
(49, 1, 'Creación', 'Usuarios', 'Nuevo usuario creado', 'Cambios 1 (000009)', 'Se registró en el sistema un nuevo usuario con rol de Alumno y correo cambios@gmail.com.', '2026-09-14 19:03:11'),
(50, 1, 'Estado', 'Usuarios', 'Cambio de estado de cuenta', 'Juan Mendez (PR01)', 'Se cambió el estado de cuenta al usuario (Estado: Inactivo → Activo).', '2026-09-15 18:07:20'),
(51, 1, 'Edición', 'Sistema', 'Ciclo finalizado', '2026A', 'Se ha finalizado el semestre actual y se ha enviado al archivo histórico.', '2026-09-22 16:19:53'),
(52, 1, 'Edición', 'Sistema', 'Nuevo ciclo actual', '2026A', 'Se ha establecido como ciclo activo principal. Los demás se archivaron automáticamente.', '2026-09-22 16:20:31'),
(53, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'Luis Macias (3)', 'Se actualizaron los datos del usuario (Correo: alumno@epale.com → ivan.padilla5116@alumnos.udg.mx).', '2026-09-24 18:56:30'),
(54, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'Luis Macias (3)', 'Se actualizaron los datos del usuario (Correo: ivan.padilla5116@alumnos.udg.mx → alumno@epale.com).', '2026-09-24 18:57:36'),
(55, 1, 'Creación', 'Usuarios', 'Nuevo usuario creado', 'Iván Alejandro Padilla (9)', 'Se registró en el sistema un nuevo usuario con rol de Alumno y correo ivan.padilla5116@alumnos.udg.mx.', '2026-09-24 18:58:22'),
(56, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'Admin Principal (AD01)', 'Se actualizaron los datos del usuario (Género:  → MASCULINO).', '2026-09-28 16:19:51'),
(57, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'Admin Principal (AD01)', 'Se actualizaron los datos del usuario (Género: MASCULINO → FEMENINO).', '2026-09-28 16:20:05'),
(58, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'Admin Principal (AD01)', 'Se actualizaron los datos del usuario (Género: FEMENINO → OTRO).', '2026-09-28 16:20:17'),
(59, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'Admin Principal (AD01)', 'Se actualizaron los datos del usuario (Género: OTRO → MASCULINO).', '2026-09-28 16:20:39'),
(60, 1, 'Inscripción', 'Grupos', 'Inscripción de estudiante', 'Admin Principal', 'Se inscribió manualmente al estudiante en el grupo con NRC 60966.', '2026-10-02 17:15:07'),
(61, 1, 'Estado', 'Solicitudes', 'Rechazo de Baja', 'Luis Macias (3)', 'Clase: Aleman (NRC: 60966) • Estado: Pendiente → Rechazada', '2026-10-06 21:47:10'),
(62, 1, 'Creación', 'Clases', 'Plantilla de criterios generada', 'IRLANDES 2', 'Se generaron automáticamente los criterios base de evaluación para la materia (100 puntos en total).', '2026-10-07 15:39:27'),
(63, 1, 'Edición', 'Grupos', 'Cambio de materia en grupo', 'Grupo grp_6ac6bc5bebdc1', 'Se modificó la materia asignada al grupo (Materia: IRLANDES (IR002) → IRLANDES (IR001)).', '2026-10-07 15:41:05'),
(64, 1, 'Estado', 'Altas', 'Aprobación de Alta', 'Luis Macias (3)', 'Clase: IRLANDES (NRC: 900) • Estado: Pendiente → Aprobada', '2026-10-07 16:28:39'),
(65, 1, 'Baja', 'Grupos', 'Baja de estudiante', 'Admin Principal', 'Se dio de baja al estudiante del grupo con NRC 900.', '2026-10-07 16:29:35'),
(66, 1, 'Estado', 'Altas', 'Rechazo de Alta', 'Luis Macias (3)', 'Clase: IRLANDES (NRC: 900) • Estado: Pendiente → Rechazada', '2026-10-07 16:40:41'),
(67, 1, 'Calificación', 'Calificaciones', 'Modificación de calificaciones', 'Luis Macias (3)', 'Se actualizaron manualmente los registros de calificaciones del estudiante desde su expediente (CERTIFICACION: N/A → 10 pts).', '2026-10-08 16:48:28'),
(68, 1, 'Edición', 'Usuarios', 'Cambio de rol de usuario', 'Iván Alejandro Padilla (9)', 'Se actualizó el rol del usuario (Rol: Alumno → Profesor).', '2026-10-14 18:48:22'),
(69, 1, 'Edición', 'Usuarios', 'Cambio de rol de usuario', 'Iván Alejandro Padilla (9)', 'Se actualizó el rol del usuario (Rol: Profesor → Alumno).', '2026-10-14 18:48:55'),
(70, 1, 'Estado', 'Solicitudes', 'Rechazo de Baja', 'Luis Macias (3)', 'Clase: ALEMAN (NRC: 60966) • Estado: Pendiente → Rechazada', '2026-10-20 16:14:33'),
(71, 1, 'Estado', 'Solicitudes', 'Rechazo de Baja', 'Luis Macias (3)', 'Clase: ALEMAN (NRC: 60966) • Estado: Pendiente → Rechazada', '2026-10-20 16:18:40'),
(72, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'Cambios 1 (000009)', 'Se actualizaron los datos del usuario (Correo: cambios@gmail.com → cambiosasxx@gmail.com).', '2026-10-23 15:33:56'),
(73, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'DIEGO ROMEO ABRICA (222078445)', 'Se actualizaron los datos del usuario (Correo: dictamenes@gmail.com → dictamenewqws@gmail.com).', '2026-10-23 15:34:04'),
(74, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'MARIA GOMEZ (123456789)', 'Se actualizaron los datos del usuario (Correo: maria@gmail.com → mariawqee@gmail.com).', '2026-10-23 15:34:11'),
(75, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'JAIME ALFREDO PEREZ (228922367)', 'Se actualizaron los datos del usuario (Correo: jaime@hotmail.com → jaimesds@hotmail.com).', '2026-10-23 15:34:19'),
(76, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'Cambios 1 (000009)', 'Se actualizaron los datos del usuario (Correo: cambiosasxx@gmail.com → cambios@epale.com).', '2026-10-23 15:34:48'),
(77, 1, 'Edición', 'Usuarios', 'Datos de usuario actualizados', 'MARIA GOMEZ (123456789)', 'Se actualizaron los datos del usuario (Correo: mariawqee@gmail.com → maria@epale.com).', '2026-10-23 15:34:56'),
(78, 1, 'Edición', 'Sistema', 'Nuevo ciclo actual', '2027B', 'Se ha establecido como ciclo activo principal. Los demás se archivaron automáticamente.', '2026-10-23 18:38:08'),
(79, 1, 'Edición', 'Sistema', 'Nuevo ciclo actual', '2026B', 'Se ha establecido como ciclo activo principal. Los demás se archivaron automáticamente.', '2026-10-23 18:50:22'),
(80, 1, 'Edición', 'Sistema', 'Nuevo ciclo actual', '2026A', 'Se ha establecido como ciclo activo principal. Los demás se archivaron automáticamente.', '2026-10-23 18:50:27');

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
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `horarios`
--

INSERT INTO `horarios` (`horario_id`, `nrc`, `dias_patron`, `hora_inicio`, `hora_fin`, `modalidad`, `aula`) VALUES
(5, 60966, 'L-I', '09:00:00', '11:00:00', 'PRESENCIAL', 'N-202'),
(12, 9999, 'M-J', '11:00:00', '13:00:00', 'PRESENCIAL', 'N-930'),
(22, 199517, 'V', '13:00:00', '15:00:00', 'PRESENCIAL', 'N391'),
(24, 134543, 'L-M', '13:00:00', '14:00:00', 'PRESENCIAL', 'N-239'),
(25, 35436, 'V', '13:00:00', '14:00:00', 'PRESENCIAL', 'A-259'),
(26, 122222, 'I-J', '11:00:00', '12:00:00', 'VIRTUAL', 'Zoom'),
(27, 122223, 'V', '09:00:00', '10:00:00', 'VIRTUAL', 'Zoom'),
(32, 44444, 'L-M', '23:00:00', '01:00:00', 'PRESENCIAL', 'N-444'),
(33, 2133213, 'S', '09:10:00', '10:13:00', 'PRESENCIAL', 'X234'),
(34, 1, 'V', '10:45:00', '12:45:00', 'PRESENCIAL', 'N-987'),
(35, 9090, 'M-J', '09:00:00', '11:00:00', 'VIRTUAL', 'Zoom'),
(36, 900, 'S', '09:00:00', '11:00:00', 'PRESENCIAL', 'N-777'),
(37, 9876, 'S', '13:00:00', '15:00:00', 'PRESENCIAL', 'N-798'),
(38, 8888, 'L-I', '11:00:00', '13:00:00', 'VIRTUAL', 'MEET');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones`
--

DROP TABLE IF EXISTS `inscripciones`;
CREATE TABLE IF NOT EXISTS `inscripciones` (
  `inscripcion_id` int NOT NULL AUTO_INCREMENT,
  `alumno_id` int NOT NULL,
  `nrc` int NOT NULL,
  `estatus` enum('INSCRITO','SOLICITUD_BAJA','BAJA','SOLICITUD_ALTA','CANCELADA') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calificacion_final` decimal(5,2) DEFAULT '0.00',
  PRIMARY KEY (`inscripcion_id`),
  KEY `alumno_id` (`alumno_id`),
  KEY `nrc` (`nrc`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(20, 5, 1, 'BAJA', 0.00),
(21, 5, 134543, 'INSCRITO', 0.00),
(22, 1, 60966, 'INSCRITO', 0.00),
(23, 1, 122223, 'CANCELADA', 0.00),
(24, 1, 900, 'BAJA', 0.00);

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
) ENGINE=InnoDB AUTO_INCREMENT=202 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `login_intentos`
--

INSERT INTO `login_intentos` (`intento_id`, `identificador`, `ip`, `exitoso`, `fecha_intento`) VALUES
(197, 'admin@epale.com', '::1', 1, '2026-10-26 23:37:45'),
(198, 'profe@epale.com', '::1', 1, '2026-10-26 23:38:07'),
(199, 'profe@epale.com', '::1', 1, '2026-10-27 00:35:00'),
(200, 'alumno@epale.com', '::1', 1, '2026-10-27 00:49:21'),
(201, 'profe@epale.com', '::1', 1, '2026-10-27 00:49:45');

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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`materia_id`, `clave`, `nombre`, `nivel`) VALUES
(1, 'I0004', 'INGLÉS', 4),
(2, 'F0001', 'FRANCÉS', 1),
(3, 'I0001', 'INGLÉS', 1),
(4, 'A0004', 'ALEMAN', 4),
(5, 'I0002', 'INGLÉS', 2),
(7, 'JN4', 'JAPONES NEGOCIOS', 4),
(8, 'I0003', 'INGLÉS', 3),
(9, 'I0014', 'INGLÉS', 4),
(10, 'R0001', 'RUSO', 1),
(11, 'R0014', 'RUSO', 4),
(12, 'IR001', 'IRLANDES', 1),
(13, 'IR002', 'IRLANDES', 2);

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `profesores`
--

INSERT INTO `profesores` (`profesor_id`, `usuario_id`, `nacionalidad`, `experiencia`) VALUES
(1, 16, 'SAYAYIN', 'C1'),
(2, 2, 'MEXICANA', 'C1'),
(3, 11, '', ''),
(4, 8, '', ''),
(5, 27, '', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas_predefinidas`
--

DROP TABLE IF EXISTS `respuestas_predefinidas`;
CREATE TABLE IF NOT EXISTS `respuestas_predefinidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `cuerpo` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `respuestas_predefinidas`
--

INSERT INTO `respuestas_predefinidas` (`id`, `usuario_id`, `titulo`, `cuerpo`) VALUES
(1, 1, 'Falta de cupo', 'Ya no hay cupo'),
(2, 1, 'cruce de horario', 'la materia que solicitas se cruza con una materia que ya tienes agendada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_altas`
--

DROP TABLE IF EXISTS `solicitudes_altas`;
CREATE TABLE IF NOT EXISTS `solicitudes_altas` (
  `solicitud_id` int NOT NULL AUTO_INCREMENT,
  `inscripcion_id` int NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `descripcion` text,
  `horario_externo` varchar(255) NOT NULL,
  `estatus` enum('PENDIENTE','APROBADA','RECHAZADA','CANCELADA') DEFAULT 'PENDIENTE',
  `respuesta_admin` text,
  `fecha_solicitud` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_respuesta` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`solicitud_id`),
  KEY `inscripcion_id` (`inscripcion_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `solicitudes_altas`
--

INSERT INTO `solicitudes_altas` (`solicitud_id`, `inscripcion_id`, `motivo`, `descripcion`, `horario_externo`, `estatus`, `respuesta_admin`, `fecha_solicitud`, `fecha_respuesta`) VALUES
(2, 23, 'sswsw', NULL, '1aae3ed022cf43fbbc8c.webp', 'CANCELADA', NULL, '2026-10-07 01:26:50', '2026-10-07 03:27:45'),
(3, 23, 'fefefeffewfew', NULL, '62733def026cfd982955.webp', 'CANCELADA', NULL, '2026-10-07 03:28:34', '2026-10-07 03:28:45'),
(4, 24, 'La quiero', NULL, '9e3c1179025f8258ece1.webp', 'APROBADA', 'Su solicitud de alta ha sido aprobada.', '2026-10-07 21:41:54', '2026-10-07 22:28:39'),
(5, 24, 'efefefef', NULL, '36a94606334b75c2f977.webp', 'RECHAZADA', 'la materia que solicitas se cruza con una materia que ya tienes agendada', '2026-10-07 22:37:23', '2026-10-07 22:40:41');

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `solicitudes_bajas`
--

INSERT INTO `solicitudes_bajas` (`solicitud_id`, `inscripcion_id`, `motivo`, `descripcion`, `estatus`, `respuesta_admin`, `fecha_solicitud`, `fecha_respuesta`) VALUES
(8, 1, 'Choque de horario', 'Ya no la quiero', 'RECHAZADA', '', '2026-03-13 00:28:39', '2026-03-13 00:28:54'),
(9, 12, 'Cambio de carrera/institución', 'Me voy a cambiar de universidad', 'APROBADA', 'Concedida la baja', '2026-08-25 03:05:42', '2026-08-25 03:06:31'),
(10, 12, 'Inscripción por error', 'Me equivoque', 'APROBADA', 'Aprobada', '2026-08-25 03:39:58', '2026-08-25 03:40:14'),
(11, 10, 'Problemas laborales/personales', 'aas', 'CANCELADA', NULL, '2026-08-25 06:27:09', NULL),
(12, 10, 'Choque de horario', 'hguiguigiug', 'CANCELADA', NULL, '2026-10-02 01:29:55', '2026-10-02 01:30:11'),
(13, 22, 'Choque de horario', 'dededeed', 'RECHAZADA', 'Su solicitud de baja ha sido rechazada.', '2026-10-07 01:27:18', '2026-10-07 03:47:10'),
(14, 22, 'Inscripción por error', 'LMAO', 'RECHAZADA', 'Su solicitud de baja ha sido rechazada.', '2026-10-20 22:14:24', '2026-10-20 22:14:33'),
(15, 22, 'Problemas laborales/personales', 'Madre mia willy', 'RECHAZADA', 'Su solicitud de baja ha sido rechazada.', '2026-10-20 22:18:34', '2026-10-20 22:18:40');

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
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`usuario_id`, `codigo`, `nombre`, `apellido_paterno`, `apellido_materno`, `correo`, `password`, `telefono`, `rol`, `estatus`, `genero`, `periodo_ingreso`, `foto_perfil`, `google_id`, `fecha_creacion`, `fecha_cambio_password`) VALUES
(1, 'AD01', 'Admin', 'Principal', '', 'admin@epale.com', '$2y$10$3p/LyyaWWdWxxb3/4QO97.HeZ7cHUNL.j.9hUZimIeA.Ua7iJN1fq', '', 'ADMIN', 'ACTIVO', 'MASCULINO', '', '2007e295579a24b1fa8c.webp', NULL, '2026-02-18 21:47:58', NULL),
(2, 'PR01', 'Juan', 'Mendez', '', 'profe@epale.com', '$2y$10$uEA8XhqxxVMt73XXtiwSdeMiSFHy5EvFDpDigOCA4aiDLBLDorYpi', '', 'PROFESOR', 'ACTIVO', 'MASCULINO', '', 'prof_2_1773183139.jpg', NULL, '2026-02-18 21:47:58', '2026-09-09 22:07:21'),
(3, '3', 'Luis', 'Macias', 'Mendez', 'alumno@epale.com', '$2y$10$u9Tnus0MXvdfVLPfpO4fSOKU94enh4U5N61AB4j6zk6pzL3YMRjGq', '3322777085', 'ALUMNO', 'ACTIVO', 'MASCULINO', '', '8276b4106ea1ab9c879a.webp', NULL, '2026-02-18 21:47:58', '2026-09-02 21:42:55'),
(4, '2187345', 'Jorge', 'Ledezma', 'Paredes', 'test2@gmail.com', '$2y$10$3p/LyyaWWdWxxb3/4QO97.HeZ7cHUNL.j.9hUZimIeA.Ua7iJN1fq', '3344553322', 'ALUMNO', 'ACTIVO', 'MASCULINO', '', NULL, NULL, '2026-02-18 21:58:25', NULL),
(7, '2', 'Ivan Alejandro', 'Godinez', 'Padilla', 'test3@gmail.com', '$2y$10$WKAXb1YFEvwvBvEAFnt6GO2ujXXSOuU3YuxNvX3SJ5FZj9pDfvmGO', '222334456', 'ALUMNO', 'ACTIVO', NULL, NULL, NULL, NULL, '2026-03-05 20:43:36', NULL),
(8, '01092834', 'Martin', 'Padilla', 'Yunuem', 'test9@gmail.com', '$2y$10$O/UZX7omnuXb5xyfbQMQce4zjAeXzX8N1jmQJTpkJaXXHo2HZ7Kka', '3344222233', 'PROFESOR', 'ACTIVO', NULL, '', NULL, NULL, '2026-03-05 23:03:29', NULL),
(10, '1', 'Valeria', 'Enriquez', 'Ruvalcaba', 'test10@gmail.com', '$2y$10$kyib1jrUJa/5J.AMnJCzIu53yUVFEQbEDCzGjuOIOlxNVJbPsjQl2', '', 'ALUMNO', 'ACTIVO', 'FEMENINO', NULL, NULL, NULL, '2026-03-06 20:27:09', NULL),
(11, '2000', 'pruebaprofe1', 'test2', 'test2', 'testprofe@gmail.com2', '$2y$10$TMceNURFhOrkJEvYwrVkMeDwqudabV.S.uwgWZno/PjLbB0PMTA6a', '3344224455', 'PROFESOR', 'ACTIVO', NULL, '', NULL, NULL, '2026-04-21 22:34:18', NULL),
(15, '228922367', 'JAIME ALFREDO', 'PEREZ', 'DIAZ', 'jaimesds@hotmail.com', '$2y$10$TC0LeJM3tuwW5J1geRqCNuda1I1HS..N1MlpSTzrOcFpD.eHCWTp.', '', 'ALUMNO', 'ACTIVO', 'MASCULINO', '2022B', NULL, NULL, '2026-05-06 21:33:25', NULL),
(16, '123456789', 'MARIA', 'GOMEZ', '', 'maria@epale.com', '$2y$10$riXh.g7rFKK8fPGZQEQW9.M4hd.MZL/yQ/b839f03QzYkLhmimmAy', '', 'PROFESOR', 'ACTIVO', 'FEMENINO', '2026A', NULL, NULL, '2026-05-06 21:33:25', NULL),
(24, '222078445', 'DIEGO ROMEO', 'ABRICA', 'MARTINEZ', 'dictamenewqws@gmail.com', '$2y$10$mLDOsg43Wf6rPT8HQyehPuti0ehbFlbCL66XstQ.dibWAp2GXVhs2', '00000122', 'ALUMNO', 'ACTIVO', 'MASCULINO', '2024A', NULL, NULL, '2026-09-08 17:07:00', NULL),
(26, '000009', 'Cambios', '1', '2', 'cambios@epale.com', '$2y$10$qg2cbMuKBRUFz8LcUS95F.krU48jjvbF6N8UZYxBc.XaXDNCJ6KmK', '', 'ALUMNO', 'ACTIVO', NULL, '', NULL, NULL, '2026-09-15 01:03:11', NULL),
(27, '9', 'Iván Alejandro', 'Padilla', 'Varela', 'ivan.padilla5116@alumnos.udg.mx', '$2y$10$gbN.zC3JYKNDsPo3jl4cfucMOFCLjJwKqEAoYPrWciaqxOTC2wtiq', '', 'ALUMNO', 'ACTIVO', 'MASCULINO', '2026A', NULL, '108982335750158430888', '2026-09-25 00:58:22', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_credenciales_biometricas`
--

DROP TABLE IF EXISTS `usuarios_credenciales_biometricas`;
CREATE TABLE IF NOT EXISTS `usuarios_credenciales_biometricas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `credential_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sign_count` bigint UNSIGNED DEFAULT '0',
  `nombre_dispositivo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Mi dispositivo',
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `ultimo_uso` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credential_id` (`credential_id`),
  KEY `fk_bio_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `avisos`
--
ALTER TABLE `avisos`
  ADD CONSTRAINT `fk_avisos_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `equipo_integrantes`
--
ALTER TABLE `equipo_integrantes`
  ADD CONSTRAINT `fk_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos_grupo` (`equipo_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `grupos`
--
ALTER TABLE `grupos`
  ADD CONSTRAINT `grupos_ibfk_1` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`materia_id`),
  ADD CONSTRAINT `grupos_ibfk_2` FOREIGN KEY (`profesor_id`) REFERENCES `usuarios` (`usuario_id`),
  ADD CONSTRAINT `grupos_ibfk_3` FOREIGN KEY (`ciclo_id`) REFERENCES `ciclos` (`ciclo_id`);

--
-- Filtros para la tabla `historial_admin`
--
ALTER TABLE `historial_admin`
  ADD CONSTRAINT `historial_admin_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`usuario_id`);

--
-- Filtros para la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD CONSTRAINT `inscripciones_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`alumno_id`),
  ADD CONSTRAINT `inscripciones_ibfk_2` FOREIGN KEY (`nrc`) REFERENCES `grupos` (`nrc`);

--
-- Filtros para la tabla `respuestas_predefinidas`
--
ALTER TABLE `respuestas_predefinidas`
  ADD CONSTRAINT `respuestas_predefinidas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitudes_altas`
--
ALTER TABLE `solicitudes_altas`
  ADD CONSTRAINT `solicitudes_altas_ibfk_1` FOREIGN KEY (`inscripcion_id`) REFERENCES `inscripciones` (`inscripcion_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios_credenciales_biometricas`
--
ALTER TABLE `usuarios_credenciales_biometricas`
  ADD CONSTRAINT `fk_bio_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
