-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-10-2025 a las 17:47:50
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
-- Base de datos: `ecommerce_cursos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(500) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amazon_url` varchar(1000) NOT NULL,
  `category` varchar(100) DEFAULT 'English Learning',
  `publication_date` date DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `description`, `cover_image`, `price`, `amazon_url`, `category`, `publication_date`, `is_featured`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(2, 'The Elements of Style', 'William Strunk Jr.', 'Una guía concisa y autoritaria sobre la escritura en inglés. Este clásico libro es esencial para cualquier persona que quiera escribir con claridad y estilo.', 'https://m.media-amazon.com/images/I/51oqf+sYttL._UX300undefined_.jpg', 12.99, 'https://www.amazon.com/Elements-Style-Fourth-William-Strunk/dp/020530902X', 'Writing', '2000-01-01', 1, 1, 2, '2025-09-21 04:55:46', '2025-09-21 14:19:30'),
(12, 'English Grammar in Use', 'Raymond Murphy', 'El libro de gramática inglesa más vendido del mundo. Perfecto para estudiantes de nivel intermedio que quieren mejorar su gramática y precisión.', 'https://m.media-amazon.com/images/I/41gL7EIGFWL._SY445_SX342_ControlCacheEqualizer_.jpg', 29.99, 'https://www.amazon.com/Secret-Secrets-Novel-Robert-Langdon/dp/0385546890/ref=sr_1_5?adgrpid=142337597986&amp;amp;amp;amp;amp;dib=eyJ2IjoiMSJ9.xYMIPW2JeAXjJPBHf9s8zgSn1IAZ2sKIqmRIoVWd8NMwGbzwKiJUhKYJG8hyyY61v0nE1GMrR5jdxwVLri9TEjPkmC-qlQ_VCp4yE2s-I4Y2pvnmOhCIECy8LCHdgw8LQjXgKDQ3-r0VPQ3LUlrUVFvQOiUmmrpac5b6rzSA9WYIv6Wrch_otyLPWDCRUllRSqVgNoRlqp3sqVnw1yXW0vcCSMVmLJfYUFcz81IxRxfnJQ9UnTaCVsNanCkK-_eVtU4eox9iep8YcOTqUpSaZL3LjV78fH_yK29iIHkUmKo.rZfOXQkJGHkJahwm-uPsZ5AraT6Kr7Mu9Guo4O2QrIw&amp;amp;amp;amp;amp;dib_tag=se&amp;amp;amp;amp;amp;hvadid=673108216047&amp;amp;amp;amp;amp;hvdev=c&amp;amp;amp;amp;amp;hvlocphy=9210589&amp;amp;amp;amp;amp;hvnetw=g&amp;amp;amp;amp;amp;hvqmt=e&amp;amp;amp;amp;amp;hvrand=836351706794811641&amp;amp;amp;amp;amp;hvtargid=kwd-28771621&amp;amp;amp;amp;amp;hydadcr=16205_13697387&amp;amp;amp;amp;amp;keywords=amazon+books&amp;amp;amp;amp;amp;mcid=4e3b55edb6ff3fad926ee266920dd6e6&amp;amp;amp;amp;amp;qid=1758451614&amp;amp;amp;amp;amp;sr=8-5', 'Grammar', '2019-01-01', 1, 1, 1, '2025-09-21 05:37:13', '2025-09-21 14:31:27'),
(21, 'The Frozen River: A GMA Book Club Pick: A Novel', 'Ariel Lawhon', 'NEW YORK TIMES BESTSELLER • GMA BOOK CLUB PICK • AN NPR BOOK OF THE YEAR • From the New York Times bestselling author of I Was Anastasia and Code Name Hélène comes a gripping historical mystery inspired by the life and diary of Martha Ballard, a renowned 18th-century midwife who defied the legal system and wrote herself into American history.\r\n\r\n&amp;amp;amp;amp;quot;Fans of Outlander’s Claire Fraser will enjoy Lawhon’s Martha, who is brave and outspoken when it comes to protecting the innocent. . . impressive.&amp;amp;amp;amp;quot;—The Washington Post\r\n\r\n&amp;amp;amp;amp;quot;Once again, Lawhon works storytelling magic with a real-life heroine.&amp;amp;amp;amp;quot; —People Magazine', 'https://m.media-amazon.com/images/I/91ulu+khYLL._AC_UL480_FMwebp_QL65_.jpg', 11.00, 'https://www.amazon.com/Frozen-River-Book-Club-Novel/dp/0593312074/ref=sr_1_7?adgrpid=142337597986&amp;amp;amp;amp;amp;dib=eyJ2IjoiMSJ9.xYMIPW2JeAXjJPBHf9s8zrZUECRMDN54zOiFMnLoVfYrku-yInnvsQj3yqS9k4Hgd3NycBQ-DBbqG1VzeAP4DjPkmC-qlQ_VCp4yE2s-I4Y2pvnmOhCIECy8LCHdgw8LQjXgKDQ3-r0VPQ3LUlrUVFyScxuTAmVGLJkWoYUOkzF7eVYy0tQ18K5Qzn4MY2ECmuYpeIQ0r90Lfo9xpu9y78gkojzXXu3BrTd9IqrMF_vnJQ9UnTaCVsNanCkK-_eVtU4eox9iep8YcOTqUpSaZH3RXYSjPC8H76C1fMIPfN8.vcjvHi9YzOh54ptFV41bFoAw4Qf0wQeRIjbgVxLoUt8&amp;amp;amp;amp;amp;dib_tag=se&amp;amp;amp;amp;amp;hvadid=630312117108&amp;amp;amp;amp;amp;hvdev=c&amp;amp;amp;amp;amp;hvlocphy=9210589&amp;amp;amp;amp;amp;hvnetw=g&amp;amp;amp;amp;amp;hvqmt=e&amp;amp;amp;amp;amp;hvrand=17435365937107481730&amp;amp;amp;amp;amp;hvtargid=kwd-28771621&amp;amp;amp;amp;amp;hydadcr=16205_13567626&amp;amp;amp;amp;amp;keywords=amazon+books&amp;amp;amp;amp;amp;mcid=4e3b55edb6ff3fad926ee266920dd6e6&amp;amp;amp;amp;amp;qid=1758482412&amp;amp;amp;amp;amp;sr=8-7', 'English Learning', '2024-11-05', 1, 1, 1, '2025-09-21 14:21:59', '2025-10-02 10:08:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT 'stripe',
  `payment_intent_id` varchar(255) DEFAULT NULL,
  `stripe_session_id` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `transaction_id`, `amount`, `currency`, `status`, `payment_method`, `payment_intent_id`, `stripe_session_id`, `notes`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'ch_3S9jSmEIqBlXSQrI15A703QT', 1.07, 'usd', 'completed', 'stripe', NULL, NULL, NULL, NULL, '2025-09-21 04:21:50', '2025-09-21 04:21:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_reset_attempts`
--

CREATE TABLE `password_reset_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `playlists`
--

CREATE TABLE `playlists` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail` varchar(500) DEFAULT '',
  `cover_image` varchar(500) DEFAULT '',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `level` varchar(50) DEFAULT 'A1',
  `duration_minutes` int(11) DEFAULT 0,
  `video_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `playlists`
--

INSERT INTO `playlists` (`id`, `title`, `description`, `thumbnail`, `cover_image`, `price`, `level`, `duration_minutes`, `video_count`, `is_active`, `featured`, `created_at`, `updated_at`) VALUES
(1, 'Canal de YouTube', 'Un curso con videos del profe hernán!', 'uploads/thumbnails/1758443992_68cfb9d884e3e.jpg', '', 1.00, 'A1', 0, 2, 1, 0, '2025-09-21 08:39:52', '2025-09-21 08:42:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registration_attempts`
--

CREATE TABLE `registration_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `failure_reason` varchar(100) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `registration_attempts`
--

INSERT INTO `registration_attempts` (`id`, `email`, `ip_address`, `user_agent`, `success`, `failure_reason`, `attempted_at`) VALUES
(1, 'maindevuser@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 OPR/121.0.0.0', 1, NULL, '2025-09-21 07:58:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `google_id` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `first_name`, `last_name`, `role`, `is_active`, `google_id`, `last_login`, `email_verified`, `failed_login_attempts`, `locked_until`, `password_changed_at`, `created_at`, `updated_at`) VALUES
(1, 'bedoyaalberth@gmail.com', '$2y$10$y0uggZCDnBo9hTsY8mTefOgLIC.UtD2dZL/6/.m5Tp702Rb7nM.JO', 'Admin', 'User', 'admin', 1, NULL, '2025-10-02 10:07:53', 1, 0, NULL, NULL, '2025-09-21 07:55:30', '2025-10-02 10:07:53'),
(2, 'maindevuser@gmail.com', '$2y$10$y0uggZCDnBo9hTsY8mTefOgLIC.UtD2dZL/6/.m5Tp702Rb7nM.JO', 'Alberth', 'User', 'user', 1, NULL, '2025-10-02 10:39:20', 0, 0, NULL, NULL, '2025-09-21 02:58:58', '2025-10-02 10:39:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_courses`
--

CREATE TABLE `user_courses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `playlist_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `access_granted_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `last_accessed_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `user_courses`
--

INSERT INTO `user_courses` (`id`, `user_id`, `playlist_id`, `order_id`, `access_granted_at`, `expires_at`, `progress_percentage`, `last_accessed_at`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 1, '2025-09-21 04:21:50', NULL, 0.00, NULL, 1, '2025-09-21 04:21:50', '2025-09-21 04:21:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_video_progress`
--

CREATE TABLE `user_video_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `playlist_id` int(11) NOT NULL,
  `progress_seconds` int(11) DEFAULT 0,
  `completed` tinyint(1) DEFAULT 0,
  `last_position_seconds` int(11) DEFAULT 0,
  `first_watched_at` datetime DEFAULT current_timestamp(),
  `last_watched_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `videos`
--

CREATE TABLE `videos` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `thumbnail_image` varchar(500) DEFAULT NULL,
  `playlist_id` int(11) NOT NULL,
  `duration` varchar(20) DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT 0,
  `order_index` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `view_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `videos`
--

INSERT INTO `videos` (`id`, `title`, `description`, `file_path`, `thumbnail_image`, `playlist_id`, `duration`, `duration_seconds`, `order_index`, `is_active`, `view_count`, `created_at`, `updated_at`) VALUES
(1, 'Control de Ventas - Excel Básico.', 'El profe hernán enseñando excel.', 'uploads/videos/1758444044_68cfba0c3cd0e.mp4', 'uploads/video_thumbnails/1758444044_68cfba0c3d137.jpg', 1, NULL, 0, 0, 1, 0, '2025-09-21 03:40:44', '2025-09-21 03:40:44'),
(2, 'Manual de Aprendiz - El profesor Hernán.', 'Hola! Bienvenidos al manual de aprendiz!', 'uploads/videos/1758444142_68cfba6e0f131.mp4', '', 1, NULL, 0, 0, 1, 0, '2025-09-21 03:42:22', '2025-09-21 03:42:22');

--
-- Disparadores `videos`
--
DELIMITER $$
CREATE TRIGGER `update_playlist_video_count_delete` AFTER DELETE ON `videos` FOR EACH ROW BEGIN
    UPDATE playlists 
    SET video_count = (
        SELECT COUNT(*) 
        FROM videos 
        WHERE playlist_id = OLD.playlist_id AND is_active = 1
    )
    WHERE id = OLD.playlist_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_playlist_video_count_insert` AFTER INSERT ON `videos` FOR EACH ROW BEGIN
    UPDATE playlists 
    SET video_count = (
        SELECT COUNT(*) 
        FROM videos 
        WHERE playlist_id = NEW.playlist_id AND is_active = 1
    )
    WHERE id = NEW.playlist_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_playlist_video_count_update` AFTER UPDATE ON `videos` FOR EACH ROW BEGIN
    -- Actualizar playlist anterior si cambió
    IF OLD.playlist_id != NEW.playlist_id THEN
        UPDATE playlists 
        SET video_count = (
            SELECT COUNT(*) 
            FROM videos 
            WHERE playlist_id = OLD.playlist_id AND is_active = 1
        )
        WHERE id = OLD.playlist_id;
    END IF;
    
    -- Actualizar playlist actual
    UPDATE playlists 
    SET video_count = (
        SELECT COUNT(*) 
        FROM videos 
        WHERE playlist_id = NEW.playlist_id AND is_active = 1
    )
    WHERE id = NEW.playlist_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sync_classes`
--

CREATE TABLE `sync_classes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `meeting_link` varchar(1000) NOT NULL,
  `whatsapp_group_link` varchar(1000) DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('active','inactive','finished') DEFAULT 'active' COMMENT 'active=Activo, inactive=Inactivo, finished=Finalizado',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sync_class_schedules`
--

CREATE TABLE `sync_class_schedules` (
  `id` int(11) NOT NULL,
  `sync_class_id` int(11) NOT NULL,
  `day_of_week` int(11) NOT NULL COMMENT '0=Domingo, 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_sync_classes`
--

CREATE TABLE `user_sync_classes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sync_class_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `access_granted_at` datetime DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `video_files`
--

CREATE TABLE `video_files` (
  `id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `filename` varchar(500) NOT NULL,
  `original_name` varchar(500) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `file_type` varchar(100) DEFAULT '',
  `mime_type` varchar(100) DEFAULT '',
  `download_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `video_files`
--

INSERT INTO `video_files` (`id`, `video_id`, `filename`, `original_name`, `file_path`, `file_size`, `file_type`, `mime_type`, `download_count`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, '1758444252_68cfbadc12f6b_banner.jpg', 'banner.jpg', 'uploads/video_files/1758444252_68cfbadc12f6b_banner.jpg', 99364, 'image/jpeg', '', 0, 1, '2025-09-21 03:44:12', '2025-09-21 03:44:12');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_is_featured` (`is_featured`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_display_order` (`display_order`);

--
-- Indices de la tabla `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_method` (`payment_method`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_processed_at` (`processed_at`),
  ADD KEY `idx_orders_user_status` (`user_id`,`status`,`created_at`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_used_at` (`used_at`);

--
-- Indices de la tabla `password_reset_attempts`
--
ALTER TABLE `password_reset_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_attempted` (`email`,`attempted_at`),
  ADD KEY `idx_ip_attempted` (`ip_address`,`attempted_at`),
  ADD KEY `idx_success` (`success`),
  ADD KEY `idx_attempted_at` (`attempted_at`);

--
-- Indices de la tabla `playlists`
--
ALTER TABLE `playlists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_title` (`title`),
  ADD KEY `idx_level` (`level`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_featured` (`featured`),
  ADD KEY `idx_created_at` (`created_at`);
ALTER TABLE `playlists` ADD FULLTEXT KEY `idx_search` (`title`,`description`);

--
-- Indices de la tabla `registration_attempts`
--
ALTER TABLE `registration_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_attempted` (`email`,`attempted_at`),
  ADD KEY `idx_ip_attempted` (`ip_address`,`attempted_at`),
  ADD KEY `idx_success` (`success`),
  ADD KEY `idx_attempted_at` (`attempted_at`);

--
-- Indices de la tabla `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_severity` (`severity`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ip_address` (`ip_address`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_email_verified` (`email_verified`),
  ADD KEY `idx_locked_until` (`locked_until`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_last_login` (`last_login`);

--
-- Indices de la tabla `user_courses`
--
ALTER TABLE `user_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_playlist` (`user_id`,`playlist_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_playlist_id` (`playlist_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_access_granted_at` (`access_granted_at`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_last_accessed_at` (`last_accessed_at`),
  ADD KEY `idx_user_courses_active` (`user_id`,`is_active`,`expires_at`);

--
-- Indices de la tabla `user_video_progress`
--
ALTER TABLE `user_video_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_video` (`user_id`,`video_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_video_id` (`video_id`),
  ADD KEY `idx_playlist_id` (`playlist_id`),
  ADD KEY `idx_completed` (`completed`),
  ADD KEY `idx_last_watched_at` (`last_watched_at`);

--
-- Indices de la tabla `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_playlist_id` (`playlist_id`),
  ADD KEY `idx_title` (`title`),
  ADD KEY `idx_order_index` (`order_index`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_view_count` (`view_count`),
  ADD KEY `idx_videos_playlist_order` (`playlist_id`,`order_index`,`is_active`);
ALTER TABLE `videos` ADD FULLTEXT KEY `idx_search` (`title`,`description`);

--
-- Indices de la tabla `sync_classes`
--
ALTER TABLE `sync_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_start_date` (`start_date`),
  ADD KEY `idx_end_date` (`end_date`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

ALTER TABLE sync_classes 
ADD COLUMN whatsapp_group_link VARCHAR(500) NULL DEFAULT NULL 
AFTER meeting_link;

--
-- Indices de la tabla `sync_class_schedules`
--
ALTER TABLE `sync_class_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sync_class_id` (`sync_class_id`),
  ADD KEY `idx_day_of_week` (`day_of_week`),
  ADD KEY `idx_start_time` (`start_time`);

--
-- Indices de la tabla `user_sync_classes`
--
ALTER TABLE `user_sync_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_sync_class` (`user_id`,`sync_class_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_sync_class_id` (`sync_class_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indices de la tabla `video_files`
--
ALTER TABLE `video_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_video_id` (`video_id`),
  ADD KEY `idx_filename` (`filename`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_reset_attempts`
--
ALTER TABLE `password_reset_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `playlists`
--
ALTER TABLE `playlists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `registration_attempts`
--
ALTER TABLE `registration_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `user_courses`
--
ALTER TABLE `user_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `user_video_progress`
--
ALTER TABLE `user_video_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `sync_classes`
--
ALTER TABLE `sync_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sync_class_schedules`
--
ALTER TABLE `sync_class_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


UPDATE sync_classes 
SET status = 'upcoming' 
WHERE status = 'inactive';

-- Modificar la columna para incluir los nuevos valores de enum
ALTER TABLE sync_classes 
MODIFY COLUMN status ENUM('active', 'upcoming', 'ending_soon', 'finished') 
DEFAULT 'active';


--
-- AUTO_INCREMENT de la tabla `user_sync_classes`
--
ALTER TABLE `user_sync_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `video_files`
--
ALTER TABLE `video_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `security_logs`
--
ALTER TABLE `security_logs`
  ADD CONSTRAINT `fk_security_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `user_courses`
--
ALTER TABLE `user_courses`
  ADD CONSTRAINT `fk_user_courses_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_courses_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_courses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `user_video_progress`
--
ALTER TABLE `user_video_progress`
  ADD CONSTRAINT `fk_user_video_progress_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_video_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_video_progress_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `videos`
--
ALTER TABLE `videos`
  ADD CONSTRAINT `fk_videos_playlists` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `sync_class_schedules`
--
ALTER TABLE `sync_class_schedules`
  ADD CONSTRAINT `fk_sync_class_schedules_sync_class` FOREIGN KEY (`sync_class_id`) REFERENCES `sync_classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `video_files`
--
ALTER TABLE `video_files`
  ADD CONSTRAINT `fk_video_files_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;