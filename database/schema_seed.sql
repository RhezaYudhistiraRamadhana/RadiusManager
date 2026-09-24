-- ============================================================
-- FreeRADIUS + RadiusManager Sample Schema & Seed Data
-- Mirrors the production schema structure from radius.sql
-- For testing and local development without loading full 2.35GB dump
-- ============================================================

CREATE DATABASE IF NOT EXISTS `radius` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `radius`;

-- 1. radcheck: User check attributes (passwords, limits, expiry)
CREATE TABLE IF NOT EXISTS `radcheck` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT '==',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. radreply: User reply attributes
CREATE TABLE IF NOT EXISTS `radreply` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT '=',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. radusergroup: User group assignment
CREATE TABLE IF NOT EXISTS `radusergroup` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `priority` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32)),
  KEY `groupname` (`groupname`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. radgroupcheck: Group check attributes
CREATE TABLE IF NOT EXISTS `radgroupcheck` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT '==',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `groupname` (`groupname`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. radgroupreply: Group reply attributes
CREATE TABLE IF NOT EXISTS `radgroupreply` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT '=',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `groupname` (`groupname`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. nas: Registered RADIUS clients
CREATE TABLE IF NOT EXISTS `nas` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `nasname` varchar(128) NOT NULL,
  `shortname` varchar(32) DEFAULT NULL,
  `type` varchar(30) DEFAULT 'other',
  `ports` int(5) DEFAULT NULL,
  `secret` varchar(60) NOT NULL DEFAULT 'secret',
  `server` varchar(64) DEFAULT NULL,
  `community` varchar(50) DEFAULT NULL,
  `description` varchar(200) DEFAULT 'RADIUS Client',
  PRIMARY KEY (`id`),
  KEY `nasname` (`nasname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. radacct: Accounting & session logs
CREATE TABLE IF NOT EXISTS `radacct` (
  `radacctid` bigint(21) NOT NULL AUTO_INCREMENT,
  `acctsessionid` varchar(64) NOT NULL DEFAULT '',
  `acctuniqueid` varchar(32) NOT NULL DEFAULT '',
  `username` varchar(64) NOT NULL DEFAULT '',
  `realm` varchar(64) DEFAULT '',
  `nasipaddress` varchar(15) NOT NULL DEFAULT '',
  `nasportid` varchar(32) DEFAULT NULL,
  `nasporttype` varchar(32) DEFAULT NULL,
  `acctstarttime` datetime DEFAULT NULL,
  `acctupdatetime` datetime DEFAULT NULL,
  `acctstoptime` datetime DEFAULT NULL,
  `acctinterval` int(12) DEFAULT NULL,
  `acctsessiontime` int(12) unsigned DEFAULT NULL,
  `acctauthentic` varchar(32) DEFAULT NULL,
  `connectinfo_start` varchar(128) DEFAULT NULL,
  `connectinfo_stop` varchar(128) DEFAULT NULL,
  `acctinputoctets` bigint(20) DEFAULT NULL,
  `acctoutputoctets` bigint(20) DEFAULT NULL,
  `calledstationid` varchar(50) NOT NULL DEFAULT '',
  `callingstationid` varchar(50) NOT NULL DEFAULT '',
  `acctterminatecause` varchar(32) NOT NULL DEFAULT '',
  `servicetype` varchar(32) DEFAULT NULL,
  `framedprotocol` varchar(32) DEFAULT NULL,
  `framedipaddress` varchar(15) NOT NULL DEFAULT '',
  `framedipv6address` varchar(45) NOT NULL DEFAULT '',
  `framedipv6prefix` varchar(45) NOT NULL DEFAULT '',
  `framedinterfaceid` varchar(44) NOT NULL DEFAULT '',
  `delegatedipv6prefix` varchar(45) NOT NULL DEFAULT '',
  `class` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`radacctid`),
  UNIQUE KEY `acctuniqueid` (`acctuniqueid`),
  KEY `username` (`username`),
  KEY `framedipaddress` (`framedipaddress`),
  KEY `acctsessionid` (`acctsessionid`),
  KEY `acctsessiontime` (`acctsessiontime`),
  KEY `acctstarttime` (`acctstarttime`),
  KEY `acctstoptime` (`acctstoptime`),
  KEY `nasipaddress` (`nasipaddress`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. radpostauth: Authentication attempts log
CREATE TABLE IF NOT EXISTS `radpostauth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `pass` varchar(64) NOT NULL DEFAULT '',
  `reply` varchar(32) NOT NULL DEFAULT '',
  `authdate` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `class` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `authdate` (`authdate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. userinfo: User profile details
CREATE TABLE IF NOT EXISTS `userinfo` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(128) DEFAULT NULL,
  `firstname` varchar(200) DEFAULT NULL,
  `lastname` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `department` varchar(200) DEFAULT NULL,
  `company` varchar(200) DEFAULT NULL,
  `workphone` varchar(200) DEFAULT NULL,
  `homephone` varchar(200) DEFAULT NULL,
  `mobilephone` varchar(200) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `city` varchar(200) DEFAULT NULL,
  `state` varchar(200) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `zip` varchar(200) DEFAULT NULL,
  `notes` varchar(200) DEFAULT NULL,
  `changeuserinfo` varchar(128) DEFAULT NULL,
  `portalloginpassword` varchar(128) DEFAULT '',
  `enableportallogin` int(32) DEFAULT 0,
  `creationdate` datetime DEFAULT CURRENT_TIMESTAMP,
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 10. operators: Administrative accounts
CREATE TABLE IF NOT EXISTS `operators` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `password` varchar(255) NOT NULL,
  `firstname` varchar(32) NOT NULL DEFAULT '',
  `lastname` varchar(32) NOT NULL DEFAULT '',
  `title` varchar(32) NOT NULL DEFAULT '',
  `department` varchar(32) NOT NULL DEFAULT '',
  `company` varchar(32) NOT NULL DEFAULT '',
  `phone1` varchar(32) NOT NULL DEFAULT '',
  `phone2` varchar(32) NOT NULL DEFAULT '',
  `email1` varchar(32) NOT NULL DEFAULT '',
  `email2` varchar(32) NOT NULL DEFAULT '',
  `messenger1` varchar(32) NOT NULL DEFAULT '',
  `messenger2` varchar(32) NOT NULL DEFAULT '',
  `notes` varchar(128) NOT NULL DEFAULT '',
  `lastlogin` datetime DEFAULT NULL,
  `creationdate` datetime DEFAULT CURRENT_TIMESTAMP,
  `creationby` varchar(128) DEFAULT 'admin',
  `updatedate` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Sample Seed Data
-- ============================================================

-- Operator
INSERT INTO `operators` (`id`, `username`, `password`, `firstname`, `lastname`) VALUES
(1, 'administrator', '4dm1nNamloP', 'System', 'Administrator')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- NAS Devices
INSERT INTO `nas` (`id`, `nasname`, `shortname`, `type`, `ports`, `secret`, `description`) VALUES
(1, '172.16.0.70', 'AP-Polman-Core', 'cisco', 1812, '4dm1nNamloP', 'Core Wireless Controller'),
(2, '167.205.23.60', 'eduroam-itb', 'other', 1812, '4dm1nNamloP', 'Eduroam Gateway')
ON DUPLICATE KEY UPDATE `nasname`=`nasname`;

-- Groups
INSERT INTO `radgroupcheck` (`id`, `groupname`, `attribute`, `op`, `value`) VALUES
(1, 'Pegawai', 'User-Profile', ':=', 'Pegawai'),
(2, 'Mhs2024', 'User-Profile', ':=', 'Mahasiswa'),
(3, 'Pegawai-Eduroam', 'User-Profile', ':=', 'Pegawai')
ON DUPLICATE KEY UPDATE `groupname`=`groupname`;

-- Users (Check attributes)
INSERT INTO `radcheck` (`id`, `username`, `attribute`, `op`, `value`) VALUES
(1, '206412005', 'Cleartext-Password', ':=', '22051981'),
(2, '201403003', 'Cleartext-Password', ':=', '08021969'),
(3, '224312005', 'Cleartext-Password', ':=', 'polman2024')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- User Info
INSERT INTO `userinfo` (`id`, `username`, `firstname`, `lastname`, `email`, `department`) VALUES
(1, '206412005', 'Pando Utomo', 'BPU', 'pando@polman-bandung.ac.id', 'BPU'),
(2, '201403003', 'Dede Sujana', 'BPU', 'edo@polman-bandung.ac.id', 'BPU'),
(3, '224312005', 'Ahmad Fauzi', 'Mahasiswa', 'ahmad.fauzi@student.polman-bandung.ac.id', 'Teknik Mesin')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- User Groups
INSERT INTO `radusergroup` (`id`, `username`, `groupname`, `priority`) VALUES
(1, '206412005', 'Pegawai', 0),
(2, '201403003', 'Pegawai', 0),
(3, '224312005', 'Mhs2024', 0)
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Sample Active Session & Completed Session
INSERT INTO `radacct` (`radacctid`, `acctsessionid`, `acctuniqueid`, `username`, `nasipaddress`, `acctstarttime`, `acctstoptime`, `acctsessiontime`, `acctinputoctets`, `acctoutputoctets`, `framedipaddress`, `callingstationid`) VALUES
(1, 'sess_active_001', 'uniq_001', '206412005', '172.16.0.70', NOW() - INTERVAL 45 MINUTE, NULL, 2700, 10485760, 41943040, '172.16.10.45', 'c2-23-50-d6-5e-51'),
(2, 'sess_done_002',   'uniq_002', '201403003', '172.16.0.70', NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 1 HOUR, 7200, 5242880, 20971520, '172.16.10.50', 'ba-6c-0a-65-ca-2d')
ON DUPLICATE KEY UPDATE `radacctid`=`radacctid`;

-- Sample Auth Log
INSERT INTO `radpostauth` (`id`, `username`, `pass`, `reply`, `authdate`) VALUES
(1, '206412005', '', 'Access-Accept', NOW() - INTERVAL 45 MINUTE),
(2, '201403003', '', 'Access-Accept', NOW() - INTERVAL 3 HOUR),
(3, 'baduser',   '', 'Access-Reject', NOW() - INTERVAL 10 MINUTE)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 11. radippool: IP pool allocations
CREATE TABLE IF NOT EXISTS `radippool` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pool_name` varchar(30) NOT NULL,
  `framedipaddress` varchar(15) NOT NULL DEFAULT '',
  `nasipaddress` varchar(15) NOT NULL DEFAULT '',
  `calledstationid` varchar(30) NOT NULL DEFAULT '',
  `callingstationid` varchar(30) NOT NULL DEFAULT '',
  `expiry_time` datetime NOT NULL DEFAULT current_timestamp(),
  `username` varchar(64) NOT NULL DEFAULT '',
  `pool_key` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `radippool_poolname_expire` (`pool_name`,`expiry_time`),
  KEY `framedipaddress` (`framedipaddress`),
  KEY `radippool_nasip_poolkey_ipaddress` (`nasipaddress`,`pool_key`,`framedipaddress`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample IP Pools (Active & Available Leases)
INSERT INTO `radippool` (`id`, `pool_name`, `framedipaddress`, `nasipaddress`, `calledstationid`, `callingstationid`, `expiry_time`, `username`, `pool_key`) VALUES
(1, 'main_pool',  '172.16.10.45', '172.16.0.70', 'AP-Polman-Core', 'c2-23-50-d6-5e-51', NOW() + INTERVAL 2 HOUR, '206412005', 'sess_active_001'),
(2, 'main_pool',  '172.16.10.46', '172.16.0.70', '', '', NOW() - INTERVAL 1 HOUR, '', ''),
(3, 'main_pool',  '172.16.10.47', '172.16.0.70', '', '', NOW() - INTERVAL 1 HOUR, '', ''),
(4, 'guest_pool', '192.168.20.10', '167.205.23.60', '', '', NOW() - INTERVAL 1 HOUR, '', ''),
(5, 'guest_pool', '192.168.20.11', '167.205.23.60', '', '', NOW() - INTERVAL 1 HOUR, '', '')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 12. rm_audit_log: Administrator activity and audit trail
CREATE TABLE IF NOT EXISTS `rm_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operator` varchar(64) NOT NULL,
  `action` varchar(64) NOT NULL,
  `target` varchar(128) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_operator` (`operator`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 13. rm_plans: Bandwidth tiers and service rate plans
CREATE TABLE IF NOT EXISTS `rm_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL UNIQUE,
  `groupname` varchar(64) NOT NULL,
  `description` text DEFAULT NULL,
  `dl_kbps` int(11) DEFAULT 0,
  `ul_kbps` int(11) DEFAULT 0,
  `data_mb` int(11) DEFAULT 0,
  `time_hours` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_groupname` (`groupname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



