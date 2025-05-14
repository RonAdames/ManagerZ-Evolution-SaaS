-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: ferramentas_mysql:3306
-- Tempo de geração: 03/04/2025 às 06:18
-- Versão do servidor: 9.2.0
-- Versão do PHP: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Banco de dados: `ferramentas`

-- --------------------------------------------------------

-- Estrutura para tabela `instancias`

CREATE TABLE `instancias` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `instance_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `instance_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `integration` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'connecting',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reject_call` tinyint(1) DEFAULT '0',
  `msg_call` text COLLATE utf8mb4_general_ci,
  `groups_ignore` tinyint(1) DEFAULT '0',
  `always_online` tinyint(1) DEFAULT '0',
  `read_messages` tinyint(1) DEFAULT '0',
  `read_status` tinyint(1) DEFAULT '0',
  `sync_full_history` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- Estrutura para tabela `tutorials`

CREATE TABLE `tutorials` (
  `id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `video_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- Estrutura para tabela `users`

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `skill` int NOT NULL DEFAULT '1',
  `max_instancias` int DEFAULT '3',
  `active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- Estrutura para tabela `password_resets`

CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices para as tabelas

-- Índices da tabela `instancias`
ALTER TABLE `instancias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `instance_id` (`instance_id`),
  ADD KEY `user_id` (`user_id`);

-- Índices da tabela `tutorials`
ALTER TABLE `tutorials`
  ADD PRIMARY KEY (`id`);

-- Índices da tabela `users`
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

-- AUTO_INCREMENT para as tabelas

ALTER TABLE `instancias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `tutorials`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

-- Restrições

ALTER TABLE `instancias`
  ADD CONSTRAINT `instancias_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Inserção de dados na tabela `users`
-- SENHA = 123
INSERT INTO `users` (`id`, `username`, `first_name`, `last_name`, `password`, `created_at`, `skill`, `max_instancias`, `active`) VALUES
(1, 'admin', 'Administrador', '', '$2y$10$GnlJOj3UMaZ7CMNFWtkxFeosendO7mGi8UsYfHev4RH1XHO0Rj7ry', '2024-11-05 06:31:29', 2, 777, 1),
(2, 'teste', 'Teste', '', '$2y$10$GnlJOj3UMaZ7CMNFWtkxFeosendO7mGi8UsYfHev4RH1XHO0Rj7ry', '2024-11-05 07:42:22', 1, 3, 1);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
