-- ═══════════════════════════════════════════════════════════
-- Eye Disease AI — Base de Datos Completa
-- Sistema de Detección de Enfermedades Oculares con IA
-- ═══════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `eye_ai_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `eye_ai_db`;

-- ─── Tabla: Roles ────────────────────────────────────────
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `name`) VALUES
    (1, 'ADMIN'),
    (2, 'USUARIO');

-- ─── Tabla: Usuarios ─────────────────────────────────────
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(100) NOT NULL,
    `email`         VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role_id`       INT UNSIGNED NOT NULL DEFAULT 2,
    `avatar`        VARCHAR(255) DEFAULT NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT,
    INDEX `idx_email` (`email`),
    INDEX `idx_role`  (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin por defecto: admin@eyeai.com / Admin123!
INSERT INTO `users` (`name`, `email`, `password_hash`, `role_id`) VALUES
    ('Administrador', 'admin@eyeai.com',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
     1);
-- NOTA: El hash anterior es placeholder. Se regenera en la instalación.
-- Password real: Admin123!

-- ─── Tabla: Predicciones ─────────────────────────────────
DROP TABLE IF EXISTS `predictions`;
CREATE TABLE `predictions` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT UNSIGNED NOT NULL,
    `image_path`      VARCHAR(500) NOT NULL,
    `image_original`  VARCHAR(255) NOT NULL,
    `predicted_class` VARCHAR(50) NOT NULL,
    `confidence`      DECIMAL(5,2) NOT NULL,
    `model_used`      VARCHAR(30) NOT NULL DEFAULT 'ResNet50',
    `all_predictions` TEXT DEFAULT NULL,
    `processing_time` DECIMAL(8,2) DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user`  (`user_id`),
    INDEX `idx_class` (`predicted_class`),
    INDEX `idx_date`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Tabla: Logs de Actividad ────────────────────────────
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `action`     VARCHAR(200) NOT NULL,
    `details`    TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(300) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_log` (`user_id`),
    INDEX `idx_action`   (`action`),
    INDEX `idx_date_log` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════════════════════
-- FIN DEL ESQUEMA
-- ═══════════════════════════════════════════════════════════
