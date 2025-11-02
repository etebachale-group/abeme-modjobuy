-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 30-08-2025 a las 17:48:27
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
-- Base de datos: `abeme_modjobuy`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `benefit_history`
--

CREATE TABLE `benefit_history` (
  `id` int(11) NOT NULL,
  `partner_name` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `type` enum('earning','payment') NOT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `benefit_history`
--

INSERT INTO `benefit_history` (`id`, `partner_name`, `amount`, `type`, `date`, `description`) VALUES
(1, 'FERNANDO CHALE', 98344.00, 'earning', '2023-01-01 00:00:00', 'Beneficio del periodo January 2023'),
(2, 'FERNANDO CHALE', 116893.00, 'earning', '2023-02-01 00:00:00', 'Beneficio del periodo February 2023'),
(3, 'FERNANDO CHALE', 199585.00, 'earning', '2023-03-01 00:00:00', 'Beneficio del periodo March 2023'),
(4, 'FERNANDO CHALE', 60136.00, 'payment', '2023-01-16 00:00:00', 'Pago realizado 16/01/2023'),
(5, 'FERNANDO CHALE', 51394.00, 'payment', '2023-02-16 00:00:00', 'Pago realizado 16/02/2023'),
(6, 'MARIA CARMEN NSUE', 126791.00, 'earning', '2023-01-01 00:00:00', 'Beneficio del periodo January 2023'),
(7, 'MARIA CARMEN NSUE', 108332.00, 'earning', '2023-02-01 00:00:00', 'Beneficio del periodo February 2023'),
(8, 'MARIA CARMEN NSUE', 72817.00, 'earning', '2023-03-01 00:00:00', 'Beneficio del periodo March 2023'),
(9, 'MARIA CARMEN NSUE', 72841.00, 'payment', '2023-01-16 00:00:00', 'Pago realizado 16/01/2023'),
(10, 'MARIA CARMEN NSUE', 82402.00, 'payment', '2023-02-16 00:00:00', 'Pago realizado 16/02/2023'),
(11, 'GENEROSA ABEME', 136008.00, 'earning', '2023-01-01 00:00:00', 'Beneficio del periodo January 2023'),
(12, 'GENEROSA ABEME', 59683.00, 'earning', '2023-02-01 00:00:00', 'Beneficio del periodo February 2023'),
(13, 'GENEROSA ABEME', 194774.00, 'earning', '2023-03-01 00:00:00', 'Beneficio del periodo March 2023'),
(14, 'GENEROSA ABEME', 64656.00, 'payment', '2023-01-16 00:00:00', 'Pago realizado 16/01/2023'),
(15, 'GENEROSA ABEME', 67521.00, 'payment', '2023-02-16 00:00:00', 'Pago realizado 16/02/2023'),
(16, 'MARIA ISABEL', 63566.00, 'earning', '2023-01-01 00:00:00', 'Beneficio del periodo January 2023'),
(17, 'MARIA ISABEL', 159130.00, 'earning', '2023-02-01 00:00:00', 'Beneficio del periodo February 2023'),
(18, 'MARIA ISABEL', 128635.00, 'earning', '2023-03-01 00:00:00', 'Beneficio del periodo March 2023'),
(19, 'MARIA ISABEL', 48195.00, 'payment', '2023-01-16 00:00:00', 'Pago realizado 16/01/2023'),
(20, 'MARIA ISABEL', 31163.00, 'payment', '2023-02-16 00:00:00', 'Pago realizado 16/02/2023');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_by` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `operation_type` varchar(20) DEFAULT 'subtract',
  `partner_name` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `expenses`
--

INSERT INTO `expenses` (`id`, `description`, `amount`, `paid_by`, `date`, `created_at`, `operation_type`, `partner_name`, `notes`) VALUES
(5, 'Transporte', 4780.00, 'FERNANDO CHALE', '2025-08-13', '2025-08-13 19:45:53', 'subtract', NULL, NULL),
(6, 'Relleno', 31280.00, 'FERNANDO CHALE', '2025-08-14', '2025-08-14 04:05:11', 'add', NULL, NULL),
(7, 'Diferencia ', 6250.00, 'CAJA', '2025-08-14', '2025-08-14 19:03:10', 'subtract', NULL, NULL),
(8, 'Ajustes', 15500.00, 'CAJA', '2025-08-21', '2025-08-21 14:23:42', 'subtract', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partner_benefits`
--

CREATE TABLE `partner_benefits` (
  `id` int(11) NOT NULL,
  `partner_name` varchar(100) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `total_earnings` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `role` varchar(50) DEFAULT 'Socio',
  `join_date` date DEFAULT NULL,
  `last_payment_date` date DEFAULT NULL,
  `last_payment_amount` decimal(15,2) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `partner_benefits`
--

INSERT INTO `partner_benefits` (`id`, `partner_name`, `percentage`, `total_earnings`, `current_balance`, `role`, `join_date`, `last_payment_date`, `last_payment_amount`, `last_updated`) VALUES
(1, 'FERNANDO CHALE', 18.00, 41400.00, 41400.00, 'Socio Principal', '2025-08-14', NULL, NULL, '2025-08-22 18:48:21'),
(2, 'MARIA CARMEN NSUE', 18.00, 41400.00, 41400.00, 'Socio Principal', '2025-08-14', NULL, NULL, '2025-08-22 18:48:10'),
(3, 'GENEROSA ABEME', 30.00, 69000.00, 69000.00, 'Socio Principal', '2025-08-14', NULL, NULL, '2025-08-22 17:50:42'),
(4, 'MARIA ISABEL', 8.00, 18400.00, 18400.00, 'Socio', '2025-08-14', NULL, NULL, '2025-08-22 18:48:05'),
(5, 'CAJA', 16.00, 0.00, 0.00, 'Sistema', '2025-08-14', NULL, NULL, '2025-08-14 11:35:44'),
(6, 'FONDOS DE SOCIOS', 10.00, 0.00, 0.00, 'Sistema', '2025-08-14', NULL, NULL, '2025-08-14 11:35:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partner_payments`
--

CREATE TABLE `partner_payments` (
  `id` int(11) NOT NULL,
  `partner_name` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed` tinyint(1) DEFAULT 0,
  `confirmation_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `previous_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `new_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shipments`
--

CREATE TABLE `shipments` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `group_code` varchar(20) NOT NULL,
  `sender_name` varchar(100) NOT NULL,
  `sender_phone` varchar(20) NOT NULL,
  `receiver_name` varchar(100) NOT NULL,
  `receiver_phone` varchar(20) NOT NULL,
  `product` varchar(100) NOT NULL,
  `weight` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) NOT NULL,
  `sale_price` decimal(10,2) NOT NULL,
  `profit` decimal(10,2) NOT NULL,
  `ship_date` date NOT NULL,
  `est_date` date NOT NULL,
  `status` enum('pending','ontheway','arrived','delayed','delivered') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `advance_payment` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `shipments`
--

INSERT INTO `shipments` (`id`, `code`, `group_code`, `sender_name`, `sender_phone`, `receiver_name`, `receiver_phone`, `product`, `weight`, `shipping_cost`, `sale_price`, `profit`, `ship_date`, `est_date`, `status`, `created_at`, `advance_payment`) VALUES
(101, 'ABM-738112', 'agosto-11-25', 'Fernando ', '+240222040010', 'Fabiola ', '555656220', 'Cosas', 1.00, 0.00, 6500.00, 0.00, '2025-08-11', '2025-08-13', 'delivered', '2025-08-13 12:10:40', 6500.00),
(105, 'ABM-940806', 'agosto-11-25', 'Fernando ', '+240222071114', 'Saydy', '222113832', 'Cosas', 2.00, 0.00, 13000.00, 0.00, '2025-08-11', '2025-08-13', 'delivered', '2025-08-13 12:12:26', 0.00),
(106, 'ABM-335968', 'agosto-11-25', 'Fernando ', '+240222071114', 'Monica', '222505734', 'Cosas', 4.00, 0.00, 26000.00, 0.00, '2025-08-11', '2025-08-13', 'delivered', '2025-08-13 12:14:43', 0.00),
(107, 'ABM-472994', 'agosto-11-25', 'Fernando ', '+240222071114', 'Fabiola', '555656220', 'Cosas', 5.00, 0.00, 32500.00, 0.00, '2025-08-11', '2025-08-13', 'delivered', '2025-08-13 12:15:47', 0.00),
(108, 'ABM-257187', 'agosto-11-25', 'Fernando ', '+240222071114', 'Alma', '555850399', 'Cosas', 10.50, 0.00, 68250.00, 0.00, '2025-08-11', '2025-08-13', 'delivered', '2025-08-13 12:17:02', 0.00),
(109, 'ABM-833080', 'julio-11-25', 'Angelina', '+240222406265', 'Angelina', '222406265', 'paquete', 2.00, 0.00, 13000.00, 0.00, '2025-07-11', '2025-07-14', 'delivered', '2025-08-13 12:46:41', 0.00),
(110, 'ABM-468106', 'julio-11-25', 'Tina', '240555305264', 'Tina', '240555305264', 'paquete', 1.00, 0.00, 6500.00, 0.00, '2025-07-11', '2025-07-14', 'delivered', '2025-08-13 12:48:31', 0.00),
(111, 'ABM-393583', 'julio-11-25', 'Luz Consuelo', '+240222863764', 'Luz Consuelo', '+240222863764', 'paquete', 1.00, 0.00, 6500.00, 0.00, '2025-07-11', '2025-07-14', 'delivered', '2025-08-13 12:50:28', 0.00),
(112, 'ABM-493345', 'julio-11-25', 'Belinda', '+240222504926', 'Belinda', '+240222504926', 'Paquete', 5.00, 0.00, 32500.00, 0.00, '2025-07-11', '2025-07-14', 'delivered', '2025-08-13 12:52:13', 0.00),
(113, 'ABM-929669', 'junio-27-25', 'Cesar', '+240222134242', 'Cesar', '+240222134242', 'paquete', 5.60, 0.00, 36400.00, 0.00, '2025-06-27', '2025-07-02', 'delivered', '2025-08-13 12:56:11', 0.00),
(114, 'ABM-521147', 'julio-12-25', 'Obama Mba', '+240222841231', 'Obama Mba', '+240222841231', 'paquete', 23.00, 0.00, 149500.00, 0.00, '2025-07-12', '2025-07-15', 'delivered', '2025-08-13 13:04:34', 0.00),
(115, 'ABM-330724', 'julio-25-25', 'Mayra', '+240555823680', 'Mayra', '+240555823680', 'paquete', 1.00, 0.00, 6500.00, 0.00, '2025-07-25', '2025-07-28', 'delivered', '2025-08-13 13:06:55', 0.00),
(116, 'ABM-905856', 'julio-25-25', 'Nestor', '+240222150375', 'Nestor', '+240222150375', 'paquete', 1.00, 0.00, 6500.00, 0.00, '2025-07-25', '2025-07-25', 'delivered', '2025-08-13 13:08:59', 0.00),
(117, 'ABM-355807', 'julio-25-25', 'Rabat', '+240222244348', 'Rabat', '+240222244348', 'paquete', 2.00, 0.00, 13000.00, 0.00, '2025-07-25', '2025-07-28', 'delivered', '2025-08-13 13:10:38', 0.00),
(118, 'ABM-796234', 'julio-25-25', 'Luz Consuelo', '+240222244348', 'Luz Consuelo', '+240222244348', 'paquete', 1.00, 0.00, 6500.00, 0.00, '2025-07-25', '2025-07-28', 'delivered', '2025-08-13 13:13:32', 0.00),
(119, 'ABM-620002', 'julio-25-25', 'Molly', '+240222018538', 'Molly', '+240222018538', 'paquete', 3.00, 0.00, 19500.00, 0.00, '2025-07-25', '2025-07-25', 'delivered', '2025-08-13 13:15:49', 0.00),
(120, 'ABM-351824', 'julio-25-25', 'Soledad', '+240222018538', 'Soledad', '+240222018538', 'paquete', 1.00, 0.00, 6500.00, 0.00, '2025-07-25', '2025-07-28', 'delivered', '2025-08-13 13:59:54', 0.00),
(121, 'ABM-466586', 'julio-25-25', 'Filomena', '+240555980012', 'Filomena', '+240555980012', 'paquete', 4.00, 0.00, 26000.00, 0.00, '2025-07-25', '2025-07-28', 'delivered', '2025-08-13 14:01:30', 0.00),
(122, 'ABM-506439', 'julio-30-25', 'Trini', '+240555788093', 'Trini', '+240555788093', 'paquete', 3.00, 0.00, 19500.00, 0.00, '2025-07-30', '2025-08-01', 'delivered', '2025-08-13 14:03:36', 0.00),
(123, 'ABM-124501', 'julio-30-25', 'Restituta', '+233553957141', 'Restituta', '233553957141', 'paquete', 1.00, 0.00, 6500.00, 0.00, '2025-07-30', '2025-08-01', 'delivered', '2025-08-13 14:06:10', 0.00),
(124, 'ABM-419011', 'julio-30-25', 'Estrella', '+240222793847', 'Estrella', '+240222793847', 'paquete', 1.50, 0.00, 9750.00, 0.00, '2025-07-30', '2025-08-01', 'delivered', '2025-08-13 14:08:06', 0.00),
(125, 'ABM-785389', 'julio-30-25', 'Beatriz', '+240222491267', 'Beatriz', '+240222491267', 'paquete', 4.50, 0.00, 29250.00, 0.00, '2025-07-30', '2025-08-01', 'delivered', '2025-08-13 14:09:55', 0.00),
(126, 'ABM-588007', 'agosto-06-25', 'Maria Gloria', '+240222769053', 'Maria Gloria', '+240222769053', 'paquete', 4.00, 0.00, 26000.00, 0.00, '2025-08-06', '2025-08-08', 'delivered', '2025-08-13 14:11:47', 0.00),
(127, 'ABM-905895', 'agosto-06-25', 'Gabriel', '+240222456407', 'Gabriel', '+240222456407', 'paquete', 5.00, 0.00, 32500.00, 0.00, '2025-08-06', '2025-08-08', 'arrived', '2025-08-13 14:13:18', 0.00),
(128, 'ABM-664799', 'agosto-06-25', 'Denis', '+240222829217', 'Denis', '+240222829217', 'paquete', 1.00, 0.00, 6500.00, 0.00, '2025-08-06', '2025-08-08', 'delivered', '2025-08-13 14:15:56', 6500.00),
(129, 'ABM-570016', 'julio-12-25', 'Sonia', '+240222608103', 'Sonia', '+240222608103', 'paquete', 2.00, 0.00, 13000.00, 0.00, '2025-07-12', '2025-07-15', 'delivered', '2025-08-14 03:44:15', 0.00),
(132, 'ABM-508091', 'agosto-18-25', 'Candida', '+240555852726', 'candida', '+240555852726', 'Paquete', 2.00, 0.00, 13000.00, 0.00, '2025-08-18', '2025-08-22', 'arrived', '2025-08-17 21:02:23', 0.00),
(134, 'ABM-829282', 'agosto-18-25', 'Sonia', '+240222531647', 'Sonia', '+240222531647', 'Paquete', 1.00, 0.00, 6500.00, 0.00, '2025-08-18', '2025-08-22', 'arrived', '2025-08-17 21:14:00', 6500.00),
(135, 'ABM-910789', 'agosto-18-25', 'Alicia', '+240222959504', 'Alicia', '222959504', 'Paquete', 1.00, 0.00, 6500.00, 0.00, '2025-08-18', '2025-08-22', 'arrived', '2025-08-17 21:16:17', 0.00),
(136, 'ABM-163534', 'agosto-18-25', 'Eliada', '+233594892986', 'Eliada', '+233594892986', 'Paquete', 1.00, 0.00, 6500.00, 0.00, '2025-08-18', '2025-08-22', 'arrived', '2025-08-17 21:18:35', 0.00),
(137, 'ABM-548171', 'agosto-25-25', 'Trini', '+240555788093', 'Trini', '+240555788093', 'paquete', 13.50, 0.00, 87750.00, 0.00, '2025-08-25', '2025-08-27', 'ontheway', '2025-08-25 00:49:03', 0.00),
(138, 'ABM-892933', 'agosto-25-25', 'Rosana', '+240222735338', 'Rosana', '+240222735338', 'paquete', 6.50, 0.00, 42250.00, 0.00, '2025-08-25', '2025-08-27', 'ontheway', '2025-08-25 00:53:00', 42250.00),
(139, 'ABM-163150', 'agosto-25-25', 'Lisa', '+240222365850', 'Lisa', '+240222365850', 'paquete', 1.00, 0.00, 6500.00, 0.00, '2025-08-25', '2025-08-27', 'ontheway', '2025-08-25 00:54:44', 6500.00),
(141, 'ABM-419739', 'agosto-25-25', 'Alma', '+240555850399', 'Alma', '+240555850399', 'paquete', 2.00, 0.00, 13000.00, 0.00, '2025-08-25', '2025-08-27', 'ontheway', '2025-08-25 01:00:02', 13000.00),
(142, 'ABM-839006', 'agosto-25-25', 'Jesusa', '+240222068818', 'Jesusa', '+240222068818', 'paquete', 7.50, 0.00, 48750.00, 0.00, '2025-08-25', '2025-08-27', 'ontheway', '2025-08-25 01:01:57', 0.00),
(143, 'ABM-806788', 'agosto-25-25', 'Rebeca', '+240222210147', 'Rebeca', '+240222210147', 'paquete', 3.00, 0.00, 19500.00, 0.00, '2025-08-25', '2025-08-27', 'ontheway', '2025-08-25 01:06:06', 0.00),
(144, 'ABM-600610', 'agosto-25-25', 'Fenicia', '+240222189347', 'Fenicia', '+240222189347', 'paquete', 3.00, 0.00, 19500.00, 0.00, '2025-08-25', '2025-08-27', 'ontheway', '2025-08-25 01:07:56', 19500.00),
(145, 'ABM-411707', 'agosto-25-25', 'Dafrosa', '+240551362525', 'Dafrosa', '+240551362525', 'paquete', 1.00, 0.00, 6500.00, 0.00, '2025-08-25', '2025-08-27', 'ontheway', '2025-08-25 01:10:22', 6500.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shipment_groups`
--

CREATE TABLE `shipment_groups` (
  `id` int(11) NOT NULL,
  `group_code` varchar(50) NOT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `shipment_groups`
--

INSERT INTO `shipment_groups` (`id`, `group_code`, `is_archived`, `created_at`) VALUES
(1, '08-10-25', 1, '2025-08-11 06:28:56'),
(2, 'agosto-10-25', 1, '2025-08-11 06:28:56'),
(4, 'agosto-09-25', 1, '2025-08-11 06:50:03'),
(5, 'agosto-08-25', 1, '2025-08-11 06:50:03'),
(6, 'agosto-07-25', 1, '2025-08-11 06:50:03'),
(7, 'agosto-06-25', 1, '2025-08-11 06:50:03'),
(14, 'agosto-12-25', 1, '2025-08-11 12:06:10'),
(15, 'octubre-12-25', 1, '2025-08-11 12:10:54'),
(16, 'agosto-13-25', 1, '2025-08-11 19:31:08'),
(17, 'agosto-11-25', 1, '2025-08-11 21:17:01'),
(18, 'agosto-5-25', 1, '2025-08-12 00:19:19'),
(19, 'julio-14-25', 1, '2025-08-12 00:19:19'),
(20, 'julio-21-25', 1, '2025-08-12 00:19:19'),
(21, 'julio-28-25', 1, '2025-08-12 00:19:19'),
(22, 'julio-7-25', 1, '2025-08-12 00:19:19'),
(23, 'junio-16-25', 1, '2025-08-12 00:19:19'),
(24, 'junio-23-25', 1, '2025-08-12 00:19:19'),
(25, 'junio-30-25', 1, '2025-08-12 00:19:19'),
(26, 'junio-9-25', 1, '2025-08-12 00:19:19'),
(27, 'julio-11-25', 1, '2025-08-13 12:46:45'),
(28, 'junio-27-25', 1, '2025-08-13 12:56:17'),
(29, 'julio-12-25', 1, '2025-08-13 13:04:38'),
(30, 'julio-25-25', 1, '2025-08-13 13:07:00'),
(31, 'julio-30-25', 1, '2025-08-13 14:03:40'),
(32, 'agosto-14-25', 1, '2025-08-14 12:04:37'),
(33, 'agosto-18-25', 0, '2025-08-17 21:02:49'),
(34, 'agosto-25-25', 0, '2025-08-25 00:49:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_metrics`
--

CREATE TABLE `system_metrics` (
  `id` int(11) NOT NULL,
  `metric_name` varchar(255) NOT NULL,
  `metric_value` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `system_metrics`
--

INSERT INTO `system_metrics` (`id`, `metric_name`, `metric_value`) VALUES
(1, 'total_accumulated_benefits', 526660.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `created_at`, `role`) VALUES
(1, 'djesis@abememodjobuy.com', '$2y$10$oP71Od1Ye5sfcqcgs0dmuOt8tp3KAHddtnfxob7b2s8YwE8ZXXCGO', '2025-08-10 00:11:20', 'user'),
(2, 'generosa@abememodjobuy.com', '$2y$10$sOX3/2rqSTu.QJ/9gN0yVO3Jge9lltMi4F2949cG3dT2H/tKqdXZ2', '2025-08-10 02:38:22', 'user'),
(4, 'blanca@abememodjobuy.com', '$2y$10$SfSgaTpN8AEmlmOFI4rKieKMtTA8x0LqEd.m7LOVxWJGe/AdZklb6', '2025-08-10 11:01:58', 'user'),
(7, 'fernandochaleeteba@gmail.com', '$2y$10$ikwF/E713i1Tdie5sn/znu0p.vSQfS4d/PG5vBU7RJB1KlQgUALGm', '2025-08-12 16:01:10', 'user');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `benefit_history`
--
ALTER TABLE `benefit_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partner_name` (`partner_name`);

--
-- Indices de la tabla `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `partner_benefits`
--
ALTER TABLE `partner_benefits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_partner` (`partner_name`);

--
-- Indices de la tabla `partner_payments`
--
ALTER TABLE `partner_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partner_name` (`partner_name`),
  ADD KEY `payment_date_index` (`payment_date`);

--
-- Indices de la tabla `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `group_code_index` (`group_code`);

--
-- Indices de la tabla `shipment_groups`
--
ALTER TABLE `shipment_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_code` (`group_code`);

--
-- Indices de la tabla `system_metrics`
--
ALTER TABLE `system_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `metric_name` (`metric_name`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `benefit_history`
--
ALTER TABLE `benefit_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `partner_benefits`
--
ALTER TABLE `partner_benefits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `partner_payments`
--
ALTER TABLE `partner_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT de la tabla `shipment_groups`
--
ALTER TABLE `shipment_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de la tabla `system_metrics`
--
ALTER TABLE `system_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `benefit_history`
--
ALTER TABLE `benefit_history`
  ADD CONSTRAINT `benefit_history_ibfk_1` FOREIGN KEY (`partner_name`) REFERENCES `partner_benefits` (`partner_name`);

--
-- Filtros para la tabla `partner_payments`
--
ALTER TABLE `partner_payments`
  ADD CONSTRAINT `partner_payments_ibfk_1` FOREIGN KEY (`partner_name`) REFERENCES `partner_benefits` (`partner_name`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
