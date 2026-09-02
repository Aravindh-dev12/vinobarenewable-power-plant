CREATE DATABASE IF NOT EXISTS `vinoba-velliyanai-scada`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vinoba-velliyanai-scada`;

DROP TABLE IF EXISTS `plants`;
CREATE TABLE `plants` (
    `id` VARCHAR(50) PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `service_number` VARCHAR(30) NOT NULL DEFAULT '',
    `capacity` DECIMAL(5,2) NOT NULL DEFAULT 2.0,
    `location` VARCHAR(100) NOT NULL DEFAULT 'Karur',
    `theme` VARCHAR(20) DEFAULT 'blue',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plants` (`id`,`name`,`service_number`,`capacity`,`location`,`theme`) VALUES
('vinoba-1','Vinoba Renewable Energy Private Limited','06914430133',2.0,'Karur','violet'),
('ssv','SSV Green Power Private Limited','06914430134',2.0,'Karur','emerald');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'user',
    `plant_id` VARCHAR(50) DEFAULT '',
    `auth_token` VARCHAR(128) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep the existing admin login. Site users should be created from the Admin page
-- and assigned only to vinoba-1 or ssv.
INSERT INTO `users` (`email`,`password`,`role`,`plant_id`) VALUES
('admin@scada.com','$2y$10$yp5n8uCZkpcJLTsUmGHBKutfKB83.HuJk8H2TSaHj2GFYUs1xZA.y','admin','');

DROP TABLE IF EXISTS `vcb_readings`;
CREATE TABLE `vcb_readings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `active_power_total` DECIMAL(10,2) DEFAULT 0,
    `active_power_r` DECIMAL(10,2) DEFAULT 0,
    `active_power_y` DECIMAL(10,2) DEFAULT 0,
    `active_power_b` DECIMAL(10,2) DEFAULT 0,
    `frequency` DECIMAL(6,2) DEFAULT 0,
    `voltage_rn` DECIMAL(8,1) DEFAULT 0,
    `voltage_yn` DECIMAL(8,1) DEFAULT 0,
    `voltage_bn` DECIMAL(8,1) DEFAULT 0,
    `voltage_ry` DECIMAL(8,1) DEFAULT 0,
    `voltage_yb` DECIMAL(8,1) DEFAULT 0,
    `voltage_br` DECIMAL(8,1) DEFAULT 0,
    `current_r` DECIMAL(6,2) DEFAULT 0,
    `current_y` DECIMAL(6,2) DEFAULT 0,
    `current_b` DECIMAL(6,2) DEFAULT 0,
    `pf_q1` DECIMAL(5,3) DEFAULT 0,
    `pf_q2` DECIMAL(5,3) DEFAULT 0,
    `pf_q3` DECIMAL(5,3) DEFAULT 0,
    `voltage_thd_r` DECIMAL(6,2) DEFAULT 0,
    `voltage_thd_y` DECIMAL(6,2) DEFAULT 0,
    `voltage_thd_b` DECIMAL(6,2) DEFAULT 0,
    `active_total_export` DECIMAL(12,2) DEFAULT 0,
    `active_total_import` DECIMAL(12,2) DEFAULT 0,
    `reactive_import_q1q2` DECIMAL(12,2) DEFAULT 0,
    `reactive_export_q3q4` DECIMAL(12,2) DEFAULT 0,
    `today_energy` DECIMAL(12,2) DEFAULT 0,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_vcb_plant` (`plant_id`), INDEX `idx_vcb_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `inverter_readings`;
CREATE TABLE `inverter_readings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `device_name` VARCHAR(100) NOT NULL,
    `ac_active_power` DECIMAL(10,2) DEFAULT 0,
    `ac_reactive_power` DECIMAL(10,2) DEFAULT 0,
    `power_factor` DECIMAL(5,3) DEFAULT 0,
    `ac_voltage_ab` DECIMAL(8,1) DEFAULT 0,
    `ac_voltage_bc` DECIMAL(8,1) DEFAULT 0,
    `ac_voltage_ca` DECIMAL(8,1) DEFAULT 0,
    `ac_frequency` DECIMAL(6,2) DEFAULT 0,
    `phase_current_a` DECIMAL(6,2) DEFAULT 0,
    `phase_current_b` DECIMAL(6,2) DEFAULT 0,
    `phase_current_c` DECIMAL(6,2) DEFAULT 0,
    `inverter_efficiency` DECIMAL(5,1) DEFAULT 0,
    `internal_temp` DECIMAL(5,1) DEFAULT 0,
    `daily_generation` DECIMAL(12,2) DEFAULT 0,
    `total_generation` DECIMAL(15,2) DEFAULT 0,
    `daily_co2_reduction` DECIMAL(10,2) DEFAULT 0,
    `total_co2_reduction` DECIMAL(12,2) DEFAULT 0,
    `daily_working_hours` DECIMAL(5,1) DEFAULT 0,
    `total_working_hours` DECIMAL(10,1) DEFAULT 0,
    `active_strings` INT DEFAULT 0,
    `total_strings` INT DEFAULT 0,
    `status` VARCHAR(20) DEFAULT 'unknown',
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_inv_plant` (`plant_id`), INDEX `idx_inv_device` (`plant_id`,`device_name`), INDEX `idx_inv_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `inverter_strings`;
CREATE TABLE `inverter_strings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `inverter_name` VARCHAR(100) NOT NULL,
    `string_number` INT NOT NULL,
    `current` DECIMAL(6,2) DEFAULT 0,
    `voltage` DECIMAL(6,1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 0,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_str_plant_inv` (`plant_id`,`inverter_name`), INDEX `idx_str_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `transformer_readings`;
CREATE TABLE `transformer_readings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `device_name` VARCHAR(100) NOT NULL,
    `oil_temp` DECIMAL(5,1) DEFAULT NULL,
    `winding_temp` DECIMAL(5,1) DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'normal',
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_trafo_plant` (`plant_id`), INDEX `idx_trafo_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `weather_readings`;
CREATE TABLE `weather_readings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `radiation` DECIMAL(8,2) DEFAULT 0,
    `panel_temp` DECIMAL(5,1) DEFAULT 0,
    `wind_speed` DECIMAL(5,2) DEFAULT 0,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_wx_plant` (`plant_id`), INDEX `idx_wx_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `telemetry_history`;
CREATE TABLE `telemetry_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plant_id` VARCHAR(50) NOT NULL,
    `metric_type` VARCHAR(50) NOT NULL,
    `metric_value` DECIMAL(12,2) DEFAULT 0,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hist_plant_type` (`plant_id`,`metric_type`), INDEX `idx_hist_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- HT/VCB and transformer telemetry are optional. The application will continue
-- with inverter data when those devices are not published by a SCADA unit.
