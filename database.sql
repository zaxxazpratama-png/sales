-- ==============================================================================
-- DATABASE SCHEMA & SEED DATA UNTUK CPANEL MYSQL / MARIADB
-- DOMAIN: idpanel.site
-- PROJECT: FORMGOOGLE & TIKORSEMIGOOGLE
-- PT. TALENTA INTEGRITAS NASIONAL
-- ==============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

-- ------------------------------------------------------------------------------
-- 1. TABEL USERS (Superadmin & Team Leader)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` VARCHAR(64) NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
    `tl_code` VARCHAR(50) NOT NULL DEFAULT '',
    `admin_email` VARCHAR(150) NOT NULL DEFAULT '',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Akun Default
INSERT INTO `users` (`id`, `username`, `password`, `role`, `tl_code`, `admin_email`, `status`, `created_at`) VALUES
('usr_superadmin', 'superadmin', '$2y$10$RmRfNIJe0xhlkMlQnRcOi.1IYXHxoB8gM2m0PhSnLrLqkGixlv39C', 'superadmin', '', '', 'active', '2026-08-20 10:00:00'),
('usr_suharta', 'suharta', '$2y$10$omHg7MBAhAW14LnXSQBVLeH7RvaDqBejEZ6KG5rlSwUR2wfnIXw02', 'tl', 'TIN-SUHARTA', '1seopageone@gmail.com', 'active', '2026-08-26 10:23:52')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `admin_email` = VALUES(`admin_email`);

-- ------------------------------------------------------------------------------
-- 2. TABEL SALES (Data Tim Sales Mitra)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
    `id` VARCHAR(64) NOT NULL,
    `sales_code` VARCHAR(50) NOT NULL,
    `nama_sales` VARCHAR(150) NOT NULL,
    `no_wa` VARCHAR(50) NOT NULL,
    `email` VARCHAR(150) NOT NULL DEFAULT '',
    `tl_code` VARCHAR(50) NOT NULL DEFAULT 'TL-MEDAN-01',
    `ttd_path` VARCHAR(255) NOT NULL DEFAULT '',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `email_customer_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sales_code` (`sales_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Sales Bawaan
INSERT INTO `sales` (`id`, `sales_code`, `nama_sales`, `no_wa`, `email`, `tl_code`, `ttd_path`, `status`, `email_customer_enabled`, `created_at`) VALUES
('1', 'SEP-001', 'FIRMAN', '081265753141', 'puja.pangestu@gmail.com', 'TIN-SUHARTA', 'assets/img/ttd_sales_master.png', 'active', 1, '2026-08-20 10:00:00'),
('2', 'SEP-002', 'Budi Santoso', '081234567801', 'budi.santoso@gmail.com', 'TIN-SUHARTA', 'assets/img/ttd_sales_sep_002.png', 'active', 1, '2026-08-20 10:30:00'),
('3', 'SEP-003', 'Dimas Pratama', '081234567802', 'dimas.pratama@gmail.com', 'TIN-SUHARTA', 'assets/img/ttd_sales_sep_003.png', 'active', 1, '2026-08-20 11:00:00'),
('4', 'SEP-004', 'Rian Hidayat', '081234567803', 'rian.hidayat@gmail.com', 'TIN-SUHARTA', '', 'active', 1, '2026-08-20 11:30:00'),
('5', 'SEP-005', 'Siti Rahma', '081234567804', 'siti.rahma@gmail.com', 'TIN-SUHARTA', '', 'active', 1, '2026-08-20 12:00:00'),
('6', 'SEP-006', 'Andi Wijaya', '081234567805', 'andi.wijaya@gmail.com', 'TIN-SUHARTA', '', 'active', 1, '2026-08-20 12:30:00'),
('7', 'SEP-007', 'Dewi Lestari', '081234567806', 'dewi.lestari@gmail.com', 'TIN-SUHARTA', '', 'active', 1, '2026-08-20 13:00:00'),
('8', 'SEP-008', 'Fajar Ramadhan', '081234567807', 'fajar.ramadhan@gmail.com', 'TIN-SUHARTA', '', 'active', 1, '2026-08-20 13:30:00'),
('9', 'SEP-009', 'Eko Prasetyo', '081234567808', 'eko.prasetyo@gmail.com', 'TIN-SUHARTA', '', 'active', 1, '2026-08-20 14:00:00'),
('10', 'SEP-010', 'Mega Putri', '081234567809', 'mega.putri@gmail.com', 'TIN-SUHARTA', '', 'active', 1, '2026-08-20 14:30:00'),
('1787732705404', 'TIN-SUHARTA-TES', 'FERDI', '08774411225588', 'salesferdi@gmail.com', 'TIN-SUHARTA', '', 'active', 1, '2026-08-26 10:25:05'),
('1787758487866', 'MAZALI', 'MAZALI RESMI', '081265757896', 'mazali@gmail.com', 'TIN-SUHARTA', '', 'active', 1, '2026-08-26 17:34:47')
ON DUPLICATE KEY UPDATE `nama_sales` = VALUES(`nama_sales`);

-- ------------------------------------------------------------------------------
-- 3. TABEL ORDERS (Pendaftaran Pelanggan & Pelacakan Status)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `ticket_no` VARCHAR(50) NOT NULL,
    `nama` VARCHAR(150) NOT NULL,
    `nomor_ktp` VARCHAR(50) NOT NULL DEFAULT '',
    `telp` VARCHAR(50) NOT NULL DEFAULT '',
    `email` VARCHAR(150) NOT NULL DEFAULT '',
    `alamat` TEXT NOT NULL,
    `home_id` VARCHAR(100) NOT NULL DEFAULT '',
    `tikor` VARCHAR(100) NOT NULL DEFAULT '',
    `paket` VARCHAR(150) NOT NULL DEFAULT '',
    `total` VARCHAR(50) NOT NULL DEFAULT '',
    `sales_code` VARCHAR(50) NOT NULL DEFAULT '',
    `tl_code` VARCHAR(50) NOT NULL DEFAULT '',
    `jadwal` VARCHAR(100) NOT NULL DEFAULT '',
    `status` VARCHAR(50) NOT NULL DEFAULT 'PENDING',
    `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ticket_no` (`ticket_no`),
    KEY `idx_sales_code` (`sales_code`),
    KEY `idx_tl_code` (`tl_code`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. TABEL SETTINGS (Pengaturan Dinamis Sistem & Paket CBN)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` LONGTEXT NOT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Pengaturan Utama
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('app_config', '{"company_name":"PT. TALENTA INTEGRITAS NASIONAL","app_title":"FORMULIR PENDAFTARAN LAYANAN CBN","app_subtitle":"CBN Service Application Form • Mitra Resmi: PT. TALENTA INTEGRITAS NASIONAL","call_center":"1500 780","wa_helpdesk":"+6287729154041","admin_email":"1seopageone@gmail.com","master_email":"pujapangestu02@gmail.com","apps_script_url":"https://script.google.com/macros/s/AKfycbwIRsM7AJx9q7CdJle7T6LAeTQnllIK8PIBwNQB_LwO42pZrhBgxUTTj12mLGJVHmog/exec","spreadsheet_id":"1cXeq5CkL4QqhsOnAg7bvV7JQvz5gxXnXE1H1JwF9PmQ","drive_folder_id":"12q5pLGP9og9rcfVs_CKwKhTxfufvsN1A","default_notes":"REGULER PROMO JULY 2026 - NAB","ttd_spv_path":"assets/img/ttd_spv_master.png","ppn_percent":11,"packages":[{"id":"cbn_p1_20","name":"Fiber 20","speed":"Speed up to 20 Mbps","price":169000,"biaya_tambahan":5000,"badge":"PROMO","badge_color":"#005696","active":true,"cbn_package":["CBN Fiber July 2026 Package 1 (15 & 20 Mbps) [1]"]},{"id":"cbn_p2_100","name":"Fiber 100","speed":"Speed up to 100 Mbps","price":199000,"biaya_tambahan":5000,"badge":"BEST VALUE","badge_color":"#00a0df","active":true,"cbn_package":["CBN Fiber July 2026 Package 2 (100, 150 & 200 Mbps) [1]","Trend Micro Maximum Security 1 Months - 1 Device (Free) [1]","Free Biaya Pemasangan"]},{"id":"f200","name":"Fiber 200","speed":"Speed up to 200 Mbps","price":199000,"biaya_tambahan":5000,"badge":"","badge_color":"#000000","active":false,"cbn_package":["CBN Fiber July 2026 Package 2 (100, 150 & 200 Mbps) [1]","Trend Micro Maximum Security 1 Months - 1 Device (Free) [1]"]},{"id":"f1g","name":"Fiber 1Gbps","speed":"Speed up to 1.000 Mbps","price":1499000,"biaya_tambahan":5000,"badge":"ULTRA","badge_color":"#8b5cf6","active":true,"cbn_package":[]},{"id":"pro100","name":"Fiber PRO 100","speed":"Simetris 100 Mbps (1:1)","price":699000,"biaya_tambahan":5000,"badge":"PRO 1:1","badge_color":"#0284c7","active":false,"cbn_package":[]}],"addons":{"tv_dens":{"name":"Dens TV+ Apps","price":0,"active":true},"tv_vision":{"name":"Vision - Premium Sports","price":40000,"active":true},"device_smartbox":{"name":"Smartbox Android","price":45000,"active":true},"device_smartbox_v3":{"name":"Smartbox Android V3","price":55000,"active":true}}}')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ------------------------------------------------------------------------------
-- 5. TABEL TIKOR & HEARTBEAT (Untuk Integrasi TIKORSEMIGOOGLE)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tikor_devices` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `sales_code` VARCHAR(50) NOT NULL DEFAULT '',
    `device_name` VARCHAR(150) NOT NULL DEFAULT '',
    `status` ENUM('online', 'offline', 'inactive') NOT NULL DEFAULT 'offline',
    `last_seen` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_device_id` (`device_id`),
    KEY `idx_dev_sales` (`sales_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tikor_heartbeats` (
    `id` BIGINT AUTO_INCREMENT NOT NULL,
    `device_id` VARCHAR(100) NOT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `accuracy` FLOAT DEFAULT 0,
    `speed` FLOAT DEFAULT 0,
    `battery` INT DEFAULT 0,
    `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hb_device` (`device_id`),
    KEY `idx_hb_time` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
