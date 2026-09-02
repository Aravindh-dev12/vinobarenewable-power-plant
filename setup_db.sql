-- Shared SCADA database for both plants.
-- vinoba-1 and ssv use the same tables; plant_id identifies each plant's rows.
CREATE DATABASE IF NOT EXISTS `vinoba-renewbale`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vinoba-renewbale`;

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
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_plant` (`plant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed logins. Passwords are stored as bcrypt hashes; users sign in with the
-- documented credentials below.
-- Admin: admin@scada.com / admin@123
-- Vinoba user: vinobarenew@scada.com / vinoba@123
-- SSV user: ssvgreen@scada.com / ssv@123
INSERT INTO `users` (`email`,`password`,`role`,`plant_id`) VALUES
('admin@scada.com','$2y$12$EXjfVDTrM7rErGE9LK//Y.wY2empw5RTIuVQsK8nE9yCA7m1/Tz9C','admin',''),
('vinobarenew@scada.com','$2y$12$V1jKdI8V8pJgDw67V/Kgo.x2NwB7xrx2a/8NXI1HWqpefjTRyClVC','user','vinoba-1'),
('ssvgreen@scada.com','$2y$12$nbEf4Q7/A75Cce/AT3W04.A9ttS6OGixElhC7AMGZK4D438LFBY5W','user','ssv');

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

-- Both plants share every telemetry table above. Never create separate per-plant
-- databases or duplicate telemetry tables. Filter/query rows by plant_id.
-- HT/VCB and transformer telemetry are optional. The application continues
-- with inverter data when those devices are not published by a SCADA unit.
