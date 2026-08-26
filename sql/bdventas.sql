-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3320
-- Generation Time: Aug 26, 2026 at 12:55 PM
-- Server version: 8.0.30
-- PHP Version: 8.3.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bdventas`
--

-- --------------------------------------------------------

--
-- Table structure for table `categoria`
--

CREATE TABLE `categoria` (
  `id_categoria` int NOT NULL,
  `tipo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categoria`
--

INSERT INTO `categoria` (`id_categoria`, `tipo`) VALUES
(1, 'Fruta'),
(2, 'Verdura'),
(3, 'Grano'),
(4, 'Lacteos');

-- --------------------------------------------------------

--
-- Table structure for table `cliente`
--

CREATE TABLE `cliente` (
  `id_cliente` int NOT NULL,
  `id_persona` int DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cliente`
--

INSERT INTO `cliente` (`id_cliente`, `id_persona`, `fecha_registro`, `estado`) VALUES
(1, 6, '2026-05-23 08:34:48', 0),
(2, 34, '2026-08-19 14:08:42', 1),
(5, 37, '2026-08-19 14:31:52', 1),
(6, 38, '2026-08-19 14:32:33', 1),
(7, 39, '2026-08-19 14:32:48', 1),
(8, 40, '2026-08-19 14:33:03', 1),
(10, 52, '2026-08-25 21:52:19', 1),
(11, 53, '2026-08-25 21:56:44', 1),
(13, 55, '2026-08-25 22:03:34', 1),
(14, 56, '2026-08-25 22:31:32', 1);

-- --------------------------------------------------------

--
-- Table structure for table `compra`
--

CREATE TABLE `compra` (
  `id_compra` int NOT NULL,
  `id_proveedor` int NOT NULL,
  `id_usuario` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `compra`
--

INSERT INTO `compra` (`id_compra`, `id_proveedor`, `id_usuario`, `fecha`, `total`, `estado`) VALUES
(3, 2, 2, '2026-05-27 10:27:48', '32000.00', 1),
(4, 2, 2, '2026-05-27 13:16:18', '20000.00', 1),
(10, 8, 20, '2026-08-23 12:10:28', '2380000.00', 1),
(11, 9, 20, '2026-08-23 12:57:28', '204000.00', 1),
(12, 6, 20, '2026-08-23 13:18:07', '2520000.00', 1),
(13, 7, 20, '2026-08-23 16:58:50', '1140000.00', 1),
(14, 9, 20, '2026-08-23 21:21:16', '3500000.00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `detalle_compra`
--

CREATE TABLE `detalle_compra` (
  `id_detalle` int NOT NULL,
  `id_compra` int NOT NULL,
  `id_producto` int NOT NULL,
  `cantidad` int NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `id_unidad` int NOT NULL DEFAULT '0',
  `precio_compra` int DEFAULT NULL,
  `cantidad_por_unidad` int DEFAULT NULL,
  `id_unidad_contenido` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detalle_compra`
--

INSERT INTO `detalle_compra` (`id_detalle`, `id_compra`, `id_producto`, `cantidad`, `subtotal`, `id_unidad`, `precio_compra`, `cantidad_por_unidad`, `id_unidad_contenido`) VALUES
(6, 10, 8, 34, '2380000.00', 5, 70000, 56, NULL),
(7, 11, 11, 6, '204000.00', 10, 34000, 45, NULL),
(8, 12, 8, 45, '2520000.00', 4, 56000, 56, 6),
(9, 13, 11, 19, '1140000.00', 4, 60000, 11, 7),
(10, 14, 8, 50, '3500000.00', 7, 70000, 81, 6);

-- --------------------------------------------------------

--
-- Table structure for table `detalle_venta`
--

CREATE TABLE `detalle_venta` (
  `id_detalle` int NOT NULL,
  `id_venta` int NOT NULL,
  `id_producto` int NOT NULL,
  `cantidad` int NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `descuento_porcentaje` decimal(5,2) NOT NULL DEFAULT '0.00',
  `descuento_valor` decimal(10,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(10,2) NOT NULL,
  `id_unidad` int DEFAULT NULL,
  `cantidad_por_unidad` int DEFAULT NULL,
  `id_unidad_contenido` int DEFAULT NULL,
  `costo_unitario` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detalle_venta`
--

INSERT INTO `detalle_venta` (`id_detalle`, `id_venta`, `id_producto`, `cantidad`, `precio_venta`, `descuento_porcentaje`, `descuento_valor`, `subtotal`, `id_unidad`, `cantidad_por_unidad`, `id_unidad_contenido`, `costo_unitario`) VALUES
(4, 4, 8, 8, '7000.00', '0.00', '0.00', '56000.00', 1, 1, 7, 1000),
(5, 5, 11, 8, '8000.00', '5.00', '3200.00', '60800.00', 4, 8, 8, 6044),
(6, 6, 11, 11, '4500.00', '10.00', '4950.00', '44550.00', 4, 17, 7, 12845),
(7, 7, 8, 11, '78000.00', '0.00', '0.00', '858000.00', 7, 4, 7, 4000),
(8, 8, 8, 20, '40000.00', '0.00', '0.00', '800000.00', 7, 15, 2, 12963),
(9, 9, 8, 8, '34000.00', '0.00', '0.00', '272000.00', 4, 1, 6, 864),
(10, 10, 11, 17, '45000.00', '0.00', '0.00', '765000.00', 4, 1, 6, 5455),
(11, 11, 11, 21, '23000.00', '0.00', '0.00', '483000.00', 6, 4, 8, 21818);

-- --------------------------------------------------------

--
-- Table structure for table `factura`
--

CREATE TABLE `factura` (
  `id_factura` int NOT NULL,
  `id_venta` int NOT NULL,
  `numero_factura` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_emision` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subtotal` decimal(10,2) NOT NULL,
  `descuento_valor` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL,
  `estado` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `factura`
--

INSERT INTO `factura` (`id_factura`, `id_venta`, `numero_factura`, `fecha_emision`, `subtotal`, `descuento_valor`, `total`, `estado`) VALUES
(1, 1, 'FAC-20260527-00001', '2026-05-27 13:17:12', '0.00', '0.00', '0.00', 0),
(2, 2, 'FAC-20260527-00002', '2026-05-27 15:14:30', '32000.00', '0.00', '32000.00', 0),
(3, 4, 'FAC-000003', '2026-08-23 16:02:37', '56000.00', '0.00', '56000.00', 1),
(4, 5, 'FAC-000004', '2026-08-23 16:56:19', '64000.00', '3200.00', '60800.00', 1),
(5, 6, 'FAC-000005', '2026-08-23 16:57:28', '49500.00', '4950.00', '44550.00', 1),
(6, 7, 'FAC-000006', '2026-08-23 16:58:03', '858000.00', '0.00', '858000.00', 1),
(7, 8, 'FAC-000007', '2026-08-23 21:22:24', '800000.00', '0.00', '800000.00', 1),
(8, 9, 'FAC-000008', '2026-08-25 13:42:58', '272000.00', '0.00', '272000.00', 1),
(9, 10, 'FAC-000009', '2026-08-25 17:44:11', '765000.00', '0.00', '765000.00', 1),
(10, 11, 'FAC-000010', '2026-08-25 17:45:14', '483000.00', '0.00', '483000.00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `inventario`
--

CREATE TABLE `inventario` (
  `id_inventario` int NOT NULL,
  `id_producto` int NOT NULL,
  `stock_actual` int NOT NULL DEFAULT '0',
  `stock_minimo` int DEFAULT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventario`
--

INSERT INTO `inventario` (`id_inventario`, `id_producto`, `stock_actual`, `stock_minimo`, `fecha_actualizacion`) VALUES
(1, 2, 56, NULL, '2026-05-27 07:44:20'),
(2, 3, 22, 7, '2026-08-23 00:00:00'),
(3, 4, 32, NULL, '2026-05-27 13:15:30'),
(4, 5, 34, NULL, '2026-06-02 20:41:34'),
(5, 6, 67, NULL, '2026-06-16 13:03:36'),
(6, 8, 3779, 0, '2026-08-25 13:42:58'),
(7, 11, 127, NULL, '2026-08-25 17:45:14');

-- --------------------------------------------------------

--
-- Table structure for table `persona`
--

CREATE TABLE `persona` (
  `id_persona` int NOT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correo` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `persona`
--

INSERT INTO `persona` (`id_persona`, `nombre`, `telefono`, `correo`, `estado`) VALUES
(5, 'jesus', '3007081694', 'jesus@gmail.com', 1),
(6, 'camila sandra', '3055098909', 'camisan@gmail.com', 1),
(8, 'melanie', '3007081694', 'jorge@gmail.com', 1),
(14, 'melanie', '3009890989', 'mesolevar1@gmail.com', 1),
(23, 'luca', '30098909', 'luca@gmail.com', 1),
(24, 'Melanie', '3009890989', 'levar1@gmail.com', 1),
(26, 'marth', '3009890989', 'mar@gmail.com', 1),
(28, 'valen', '3007081694', 'valen@gmail.com', 1),
(29, 'prepucio', '3009890989', 'papoi@gmail.com', 1),
(30, 'cara', '3007081694', 'cara@gmail.com', 1),
(31, 'mami', '3009890989', 'mami@gmail.com', 1),
(33, 'Sara', '3009890989', 'sara@gmail.com', 1),
(34, 'Nina', '3007081694', 'esolevar1@gmail.com', 1),
(37, 'mmm', '3009890989', 'mmm@hhh.com', 1),
(38, 'martin', '3009890989', 'matir@gmail.com', 1),
(39, 'mateo', '3009890989', 'mateo@gmail.com', 1),
(40, 'samuel', '3009890989', 'samuel@gmail.com', 1),
(41, 'aguila', '3007081694', 'agulita@gmail.com', 1),
(42, 'Bimbo', '3244545434', 'bimbo@gmail.com', 1),
(43, 'FlorHuila', '33345454333', 'flor@gmail.com', 1),
(44, 'Cremelado', '3007081694', 'crema@gmail.com', 1),
(48, 'lulo', '3007081694', 'lulo@gmail.com', 1),
(49, 'mar', '3009890989', 'mar1@gmail.com', 1),
(50, 'eeeeeeeeeeeeeeeeeeeeee', '444444444', '444@gmail.com', 1),
(51, 'mellon', '3009890989', '333@gmail.com', 1),
(52, 'carolina', '3434212356', 'caro@gmail.com', 1),
(53, 'aaaaaaaaaaaaaa', '2222222222222', 'aaaaa@gmail.com', 1),
(55, 'Melanie', '3009890989', 'solear1@gmail.com', 1),
(56, 'eeeeeeeee', 'eeeeeeeee', 'eeeeeeee@ffff.com', 1);

-- --------------------------------------------------------

--
-- Table structure for table `producto`
--

CREATE TABLE `producto` (
  `id_producto` int NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `id_categoria` int NOT NULL,
  `imagen` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `producto`
--

INSERT INTO `producto` (`id_producto`, `nombre`, `descripcion`, `id_categoria`, `imagen`, `estado`) VALUES
(2, 'fresa', 'fresa roja excelente para ensalada de frutas', 1, 'img/productos/prod_1779545325_652.jpg', 0),
(3, 'arandano', 'kkkkkkkkkk', 1, 'img/productos/prod_1779889070_604.jpg', 0),
(4, 'coco', 'coco bien coco para lo coco', 1, 'img/productos/prod_1779905729_655.jpg', 0),
(5, 'jesus alberto lemus vargas', 'gfhjk,', 1, 'img/productos/prod_1780450894_931.jpg', 0),
(6, 'kkkdkd', 'dsasdfghgfds', 4, 'img/productos/prod_1781633016_326.jpg', 0),
(8, 'hgfds', 'gfdsaaaaaaaaaaaaa', 1, 'img/productos/prod_1787355982_343.png', 1),
(11, 'aaaaaaaaaaaaaa', 'ssssssssssssssssssssssss', 2, 'uploads/productos/producto_20260822190544_ef6b597374.png', 1),
(12, 'pera', 'perita muy rica', 1, 'uploads/productos/producto_20260823184730_f4c638c297.png', 1),
(13, 'mmmmmmmmmmmm', 'mmm', 1, 'uploads/productos/producto_20260823200307_04cffe8d9e.png', 1),
(14, 'guayaba', 'guayaba muy rica y dulce idela para juegos refrescantes', 1, 'uploads/productos/producto_20260826023751_5eefd214d2.jpg', 1),
(15, 'cocis', 'sssssssssssssssssssssssssssssssssssssssssssssssssss', 1, 'uploads/productos/producto_20260826024247_97b2df7b31.jpg', 1),
(16, 'cocas', 'aaaaaaaaaaa', 2, 'uploads/productos/producto_20260826030604_863a36c7cf.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `proveedor`
--

CREATE TABLE `proveedor` (
  `id_proveedor` int NOT NULL,
  `id_persona` int NOT NULL,
  `frecuencia_entrega` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proveedor`
--

INSERT INTO `proveedor` (`id_proveedor`, `id_persona`, `frecuencia_entrega`) VALUES
(2, 8, 'cada 15 dias.'),
(5, 14, 'cada 8 dias'),
(6, 41, 'mensual'),
(7, 42, 'cada 8 dias'),
(8, 43, 'cada 15 dias'),
(9, 44, 'Semanal');

-- --------------------------------------------------------

--
-- Table structure for table `rol`
--

CREATE TABLE `rol` (
  `id_rol` int NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rol`
--

INSERT INTO `rol` (`id_rol`, `nombre`) VALUES
(1, 'Administrador'),
(2, 'Vendedor');

-- --------------------------------------------------------

--
-- Table structure for table `unidades_medida`
--

CREATE TABLE `unidades_medida` (
  `id_unidad` int NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unidades_medida`
--

INSERT INTO `unidades_medida` (`id_unidad`, `nombre`) VALUES
(1, 'Unidad'),
(2, 'Docena'),
(3, 'Bulto'),
(4, 'Paca'),
(5, 'Canastilla'),
(6, 'Kilo'),
(7, 'Libra'),
(8, 'Caja'),
(9, 'Bolsa'),
(10, 'Arroba');

-- --------------------------------------------------------

--
-- Table structure for table `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int NOT NULL,
  `contraseña` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_persona` int NOT NULL,
  `id_rol` int NOT NULL,
  `estado` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `contraseña`, `id_persona`, `id_rol`, `estado`) VALUES
(2, '$2y$10$0uAS2qfGjnoxbFixtkLkPuN65Blsm0ubgzFD4XvjWreeqsAwNN8Yu', 5, 1, 1),
(6, '$2y$10$.CWu0Lc2UKhHqV0RBF6K2ulcaJijl4SlnYNYzhdMJyXMocC41mbU2', 23, 1, 1),
(7, '$2y$10$fYaKf4s3zD5wDlJR9F/ItOPy/XxJRG7PjsQbzDM0c6Ac6d28oKN8a', 24, 1, 1),
(9, '$2y$10$lg7cbme8r54HU4TVXYZ0tuPEd99d7/u1bTSkS/o1u29EDCnBKXlQ.', 26, 1, 1),
(11, '$2y$10$in32sZd8e6k/BdAcTZ6AYeOUziiHZIJ/Mv8ky0.otKQZp4Jgd/1cW', 28, 1, 1),
(12, '$2y$10$NqejxvaGoLNaD4Vjp6NJOeLvd7jBDsppbpgZ0OFYYUa/pgbWbqVDy', 29, 1, 1),
(13, '$2y$10$oM1ka8p/5v3XsvSEQW6JPu3BCctF9.GoXjMTE1r2L7pxbIcK9BFbu', 30, 1, 1),
(14, '$2y$10$lESasKBn0PZthM5lXqHt7O1Tf7kC/icaBRgOPHWEW9fbSOJY92o96', 31, 1, 1),
(16, '$2y$10$36QNo9YIabk0MWqYPocwaeSFk2KZhbo7dtHtCB2CQzuw2PWcpaAye', 33, 1, 1),
(18, '$2y$10$OYNAJ24Oc.fUnoROJt6Qc.TkPfhS2bEwU7gUTUkGxkEwbZGUUAPVm', 48, 1, 1),
(19, '$2y$10$yvEYgTbA4HJq3fkvj7WypOhCEuEz.AsW9rmxKwkSug6KQPyqT0106', 49, 1, 1),
(20, '$2y$10$oFSKLwBygCGC3DPw.2nDxehmKCUqrBFo8xhk0kB2SMaGObFCpXaUO', 50, 2, 1),
(21, '$2y$10$b0IDOfPAZwSh5CHA.qA/LOwgH34kyh8vD5nxSb4LJaBOp8jMTtBq.', 51, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `venta`
--

CREATE TABLE `venta` (
  `id_venta` int NOT NULL,
  `id_cliente` int NOT NULL,
  `id_usuario` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `metodo_pago` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venta`
--

INSERT INTO `venta` (`id_venta`, `id_cliente`, `id_usuario`, `fecha`, `total`, `metodo_pago`, `estado`) VALUES
(1, 1, 2, '2026-05-27 13:17:12', '0.00', NULL, 0),
(2, 1, 2, '2026-05-27 15:14:30', '32000.00', NULL, 0),
(4, 2, 20, '2026-08-23 16:02:37', '56000.00', 'efectivo', 1),
(5, 5, 20, '2026-08-23 16:56:19', '60800.00', 'efectivo', 1),
(6, 7, 20, '2026-08-23 16:57:27', '44550.00', 'efectivo', 1),
(7, 5, 20, '2026-08-23 16:58:03', '858000.00', 'tarjeta', 1),
(8, 7, 20, '2026-08-23 21:22:24', '800000.00', 'efectivo', 1),
(9, 5, 20, '2026-08-25 13:42:58', '272000.00', 'efectivo', 1),
(10, 5, 21, '2026-08-25 17:44:11', '765000.00', 'efectivo', 1),
(11, 7, 20, '2026-08-25 17:45:14', '483000.00', 'efectivo', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indexes for table `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`id_cliente`),
  ADD KEY `fk_cliente_persona` (`id_persona`);

--
-- Indexes for table `compra`
--
ALTER TABLE `compra`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `fk_compra_proveedor` (`id_proveedor`),
  ADD KEY `fk_compra_usuario` (`id_usuario`);

--
-- Indexes for table `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `fk_dc_compra` (`id_compra`),
  ADD KEY `fk_dc_precio` (`id_producto`) USING BTREE,
  ADD KEY `fk_detalle_compra_unidad` (`id_unidad`),
  ADD KEY `fk_detalle_compra_unidad_contenido` (`id_unidad_contenido`);

--
-- Indexes for table `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `fk_dv_venta` (`id_venta`),
  ADD KEY `fk_dv_precio` (`id_producto`) USING BTREE,
  ADD KEY `fk_detalle_venta_unidad` (`id_unidad`),
  ADD KEY `fk_detalle_venta_unidad_contenido` (`id_unidad_contenido`);

--
-- Indexes for table `factura`
--
ALTER TABLE `factura`
  ADD PRIMARY KEY (`id_factura`),
  ADD KEY `fk_factura_venta` (`id_venta`);

--
-- Indexes for table `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id_inventario`),
  ADD KEY `fk_inventario_producto` (`id_producto`);

--
-- Indexes for table `persona`
--
ALTER TABLE `persona`
  ADD PRIMARY KEY (`id_persona`);

--
-- Indexes for table `producto`
--
ALTER TABLE `producto`
  ADD PRIMARY KEY (`id_producto`),
  ADD KEY `fk_producto_categoria` (`id_categoria`);

--
-- Indexes for table `proveedor`
--
ALTER TABLE `proveedor`
  ADD PRIMARY KEY (`id_proveedor`),
  ADD KEY `fk_proveedor_persona` (`id_persona`);

--
-- Indexes for table `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indexes for table `unidades_medida`
--
ALTER TABLE `unidades_medida`
  ADD PRIMARY KEY (`id_unidad`);

--
-- Indexes for table `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `fk_usuario_persona` (`id_persona`),
  ADD KEY `fk_usuario_rol` (`id_rol`);

--
-- Indexes for table `venta`
--
ALTER TABLE `venta`
  ADD PRIMARY KEY (`id_venta`),
  ADD KEY `fk_venta_cliente` (`id_cliente`),
  ADD KEY `fk_venta_usuario` (`id_usuario`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categoria`
--
ALTER TABLE `categoria`
  MODIFY `id_categoria` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cliente`
--
ALTER TABLE `cliente`
  MODIFY `id_cliente` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `compra`
--
ALTER TABLE `compra`
  MODIFY `id_compra` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `detalle_compra`
--
ALTER TABLE `detalle_compra`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `detalle_venta`
--
ALTER TABLE `detalle_venta`
  MODIFY `id_detalle` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `factura`
--
ALTER TABLE `factura`
  MODIFY `id_factura` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id_inventario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `persona`
--
ALTER TABLE `persona`
  MODIFY `id_persona` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `producto`
--
ALTER TABLE `producto`
  MODIFY `id_producto` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `proveedor`
--
ALTER TABLE `proveedor`
  MODIFY `id_proveedor` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `rol`
--
ALTER TABLE `rol`
  MODIFY `id_rol` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `unidades_medida`
--
ALTER TABLE `unidades_medida`
  MODIFY `id_unidad` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `venta`
--
ALTER TABLE `venta`
  MODIFY `id_venta` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cliente`
--
ALTER TABLE `cliente`
  ADD CONSTRAINT `fk_cliente_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE;

--
-- Constraints for table `compra`
--
ALTER TABLE `compra`
  ADD CONSTRAINT `fk_compra_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id_proveedor`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_compra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE;

--
-- Constraints for table `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD CONSTRAINT `fk_dc_compra` FOREIGN KEY (`id_compra`) REFERENCES `compra` (`id_compra`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dc_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_compra_unidad` FOREIGN KEY (`id_unidad`) REFERENCES `unidades_medida` (`id_unidad`),
  ADD CONSTRAINT `fk_detalle_compra_unidad_contenido` FOREIGN KEY (`id_unidad_contenido`) REFERENCES `unidades_medida` (`id_unidad`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD CONSTRAINT `fk_detalle_venta_unidad` FOREIGN KEY (`id_unidad`) REFERENCES `unidades_medida` (`id_unidad`),
  ADD CONSTRAINT `fk_detalle_venta_unidad_contenido` FOREIGN KEY (`id_unidad_contenido`) REFERENCES `unidades_medida` (`id_unidad`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dv_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dv_venta` FOREIGN KEY (`id_venta`) REFERENCES `venta` (`id_venta`) ON UPDATE CASCADE;

--
-- Constraints for table `factura`
--
ALTER TABLE `factura`
  ADD CONSTRAINT `fk_factura_venta` FOREIGN KEY (`id_venta`) REFERENCES `venta` (`id_venta`) ON UPDATE CASCADE;

--
-- Constraints for table `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `fk_inventario_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON UPDATE CASCADE;

--
-- Constraints for table `producto`
--
ALTER TABLE `producto`
  ADD CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`) ON UPDATE CASCADE;

--
-- Constraints for table `proveedor`
--
ALTER TABLE `proveedor`
  ADD CONSTRAINT `fk_proveedor_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE;

--
-- Constraints for table `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `fk_usuario_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`) ON UPDATE CASCADE;

--
-- Constraints for table `venta`
--
ALTER TABLE `venta`
  ADD CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
