-- ============================================================
-- RadiusManager — Performance Indexes for FreeRADIUS Database
-- Safe for MySQL 5.7+, MySQL 8.0+, and MariaDB 10.3+
-- Usage: mysql -u root -p radius < install.sql
-- ============================================================

DELIMITER $$

-- Helper procedure to safely add an index only if it does not already exist
DROP PROCEDURE IF EXISTS `rm_add_index_if_missing`$$
CREATE PROCEDURE `rm_add_index_if_missing`(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_columns VARCHAR(255)
)
BEGIN
    DECLARE v_count INT;
    SELECT COUNT(*) INTO v_count
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = p_table
      AND index_name = p_index;

    IF v_count = 0 THEN
        SET @s = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_columns, ')');
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

-- Helper procedure to safely add a column only if it does not already exist
DROP PROCEDURE IF EXISTS `rm_add_column_if_missing`$$
CREATE PROCEDURE `rm_add_column_if_missing`(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_def VARCHAR(255)
)
BEGIN
    DECLARE v_count INT;
    SELECT COUNT(*) INTO v_count
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = p_table
      AND column_name = p_column;

    IF v_count = 0 THEN
        SET @s = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- ── 1. radacct indexes (critical for session queries) ─────────
CALL rm_add_index_if_missing('radacct', 'idx_username', 'username');
CALL rm_add_index_if_missing('radacct', 'idx_framedip', 'framedipaddress');
CALL rm_add_index_if_missing('radacct', 'idx_sessionid', 'acctsessionid');
CALL rm_add_index_if_missing('radacct', 'idx_starttime', 'acctstarttime');
CALL rm_add_index_if_missing('radacct', 'idx_stoptime', 'acctstoptime');
CALL rm_add_index_if_missing('radacct', 'idx_nasip', 'nasipaddress');
CALL rm_add_index_if_missing('radacct', 'idx_user_stoptime', 'username, acctstoptime');
CALL rm_add_index_if_missing('radacct', 'idx_stop_start', 'acctstoptime, acctstarttime');

-- ── 2. radcheck indexes ───────────────────────────────────────
CALL rm_add_index_if_missing('radcheck', 'idx_username', 'username(64)');
CALL rm_add_index_if_missing('radcheck', 'idx_attribute', 'attribute');

-- ── 3. radreply indexes ───────────────────────────────────────
CALL rm_add_index_if_missing('radreply', 'idx_username', 'username(64)');

-- ── 4. radusergroup indexes ───────────────────────────────────
CALL rm_add_index_if_missing('radusergroup', 'idx_username', 'username(64)');
CALL rm_add_index_if_missing('radusergroup', 'idx_groupname', 'groupname(64)');

-- ── 5. radpostauth indexes (critical for 48M+ row tables) ─────
CALL rm_add_index_if_missing('radpostauth', 'idx_username', 'username(64)');
CALL rm_add_index_if_missing('radpostauth', 'idx_authdate', 'authdate');

-- ── 6. userinfo indexes ───────────────────────────────────────
CALL rm_add_index_if_missing('userinfo', 'idx_username', 'username(64)');

-- ── 7. radippool indexes (if table exists) ─────────────────────
CALL rm_add_index_if_missing('radippool', 'idx_username', 'username(64)');

-- Clean up helper procedures
DROP PROCEDURE IF EXISTS `rm_add_index_if_missing`;
DROP PROCEDURE IF EXISTS `rm_add_column_if_missing`;

-- Optional admin table if operators table is not used
CREATE TABLE IF NOT EXISTS `rm_admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 8. rm_audit_log table ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rm_audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `operator` VARCHAR(64) NOT NULL,
    `action` VARCHAR(64) NOT NULL,
    `target` VARCHAR(128) DEFAULT NULL,
    `detail` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_operator` (`operator`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 9. rm_plans table ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rm_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `groupname` VARCHAR(64) NOT NULL,
    `description` TEXT,
    `dl_kbps` INT DEFAULT 0,
    `ul_kbps` INT DEFAULT 0,
    `data_mb` INT DEFAULT 0,
    `time_hours` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_groupname` (`groupname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SELECT 'RadiusManager performance indexes verified successfully.' AS status;
