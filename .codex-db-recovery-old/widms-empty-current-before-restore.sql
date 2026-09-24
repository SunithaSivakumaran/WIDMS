-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: widms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `role` varchar(50) NOT NULL,
  `module` varchar(80) NOT NULL,
  `action` varchar(500) NOT NULL,
  `record_reference` varchar(100) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'done',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_user_date` (`user_id`,`created_at`),
  KEY `idx_activity_date` (`created_at`),
  KEY `idx_activity_module` (`module`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aid_requests`
--

DROP TABLE IF EXISTS `aid_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aid_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `beneficiary_id` int(10) unsigned NOT NULL,
  `identification_method` enum('nic','elders-card','both') DEFAULT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `disability_notes` varchar(500) NOT NULL,
  `prescribed_power` decimal(5,2) DEFAULT NULL,
  `beneficiary_detail_label` varchar(100) DEFAULT NULL,
  `beneficiary_detail_value` varchar(255) DEFAULT NULL,
  `beneficiary_details_json` text DEFAULT NULL,
  `goods_request_ref` int(10) unsigned DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `medical_officer_approved` tinyint(1) NOT NULL DEFAULT 0,
  `grama_niladhari_approved` tinyint(1) NOT NULL DEFAULT 0,
  `social_services_approved` tinyint(1) NOT NULL DEFAULT 0,
  `divisional_secretary_approved` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','pending','approved','rejected','goods-requested','distributed') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `submitted_by` int(10) unsigned NOT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_aid_request_item` (`item_id`),
  KEY `fk_aid_request_submitter` (`submitted_by`),
  KEY `fk_aid_request_reviewer` (`reviewed_by`),
  KEY `idx_aid_request_status` (`status`),
  KEY `idx_aid_request_beneficiary` (`beneficiary_id`),
  CONSTRAINT `fk_aid_request_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`),
  CONSTRAINT `fk_aid_request_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_aid_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aid_request_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aid_requests`
--

LOCK TABLES `aid_requests` WRITE;
/*!40000 ALTER TABLE `aid_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `aid_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beneficiaries`
--

DROP TABLE IF EXISTS `beneficiaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `beneficiaries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `registration_request_id` int(10) unsigned DEFAULT NULL,
  `district_id` int(10) unsigned NOT NULL,
  `ds_division_id` int(10) unsigned NOT NULL,
  `gn_division_id` int(10) unsigned DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `nic` varchar(20) DEFAULT NULL,
  `elders_card_number` varchar(30) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `disability` varchar(255) NOT NULL,
  `status` enum('active','inactive','deceased') NOT NULL DEFAULT 'active',
  `approved_by` int(10) unsigned NOT NULL,
  `approved_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nic` (`nic`),
  UNIQUE KEY `registration_request_id` (`registration_request_id`),
  UNIQUE KEY `uq_beneficiaries_elders_card` (`elders_card_number`),
  KEY `fk_beneficiary_ds` (`ds_division_id`),
  KEY `fk_beneficiary_gn` (`gn_division_id`),
  KEY `fk_beneficiary_approver` (`approved_by`),
  KEY `idx_beneficiary_geography` (`district_id`,`ds_division_id`,`gn_division_id`),
  KEY `idx_beneficiary_status` (`status`),
  CONSTRAINT `fk_beneficiary_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_beneficiary_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`),
  CONSTRAINT `fk_beneficiary_ds` FOREIGN KEY (`ds_division_id`) REFERENCES `ds_divisions` (`id`),
  CONSTRAINT `fk_beneficiary_gn` FOREIGN KEY (`gn_division_id`) REFERENCES `gn_divisions` (`id`),
  CONSTRAINT `fk_beneficiary_request` FOREIGN KEY (`registration_request_id`) REFERENCES `beneficiary_registration_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `beneficiaries`
--

LOCK TABLES `beneficiaries` WRITE;
/*!40000 ALTER TABLE `beneficiaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `beneficiaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beneficiary_registration_requests`
--

DROP TABLE IF EXISTS `beneficiary_registration_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `beneficiary_registration_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `district_id` int(10) unsigned NOT NULL,
  `ds_division_id` int(10) unsigned NOT NULL,
  `gn_division_id` int(10) unsigned NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `nic` varchar(20) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `disability` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `submitted_by` int(10) unsigned NOT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_brequest_district` (`district_id`),
  KEY `fk_brequest_ds` (`ds_division_id`),
  KEY `fk_brequest_gn` (`gn_division_id`),
  KEY `fk_brequest_submitter` (`submitted_by`),
  KEY `fk_brequest_reviewer` (`reviewed_by`),
  KEY `idx_brequest_status` (`status`),
  KEY `idx_brequest_nic` (`nic`),
  CONSTRAINT `fk_brequest_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`),
  CONSTRAINT `fk_brequest_ds` FOREIGN KEY (`ds_division_id`) REFERENCES `ds_divisions` (`id`),
  CONSTRAINT `fk_brequest_gn` FOREIGN KEY (`gn_division_id`) REFERENCES `gn_divisions` (`id`),
  CONSTRAINT `fk_brequest_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_brequest_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `beneficiary_registration_requests`
--

LOCK TABLES `beneficiary_registration_requests` WRITE;
/*!40000 ALTER TABLE `beneficiary_registration_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `beneficiary_registration_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_lens_bulk_order_items`
--

DROP TABLE IF EXISTS `contact_lens_bulk_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_lens_bulk_order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bulk_order_id` int(10) unsigned NOT NULL,
  `aid_request_id` int(10) unsigned NOT NULL,
  `power` decimal(5,2) NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `received_quantity` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `aid_request_id` (`aid_request_id`),
  KEY `idx_cl_bulk_item_power` (`bulk_order_id`,`power`),
  CONSTRAINT `fk_cl_bulk_item_order` FOREIGN KEY (`bulk_order_id`) REFERENCES `contact_lens_bulk_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cl_bulk_item_request` FOREIGN KEY (`aid_request_id`) REFERENCES `aid_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_lens_bulk_order_items`
--

LOCK TABLES `contact_lens_bulk_order_items` WRITE;
/*!40000 ALTER TABLE `contact_lens_bulk_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_lens_bulk_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_lens_bulk_orders`
--

DROP TABLE IF EXISTS `contact_lens_bulk_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_lens_bulk_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_code` varchar(30) NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
  `status` enum('draft','pending-admin-approval','approved','rejected','partially-received','fully-received','completed') NOT NULL DEFAULT 'draft',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_code` (`order_code`),
  KEY `fk_cl_bulk_supplier` (`supplier_id`),
  KEY `fk_cl_bulk_creator` (`created_by`),
  KEY `fk_cl_bulk_reviewer` (`reviewed_by`),
  KEY `idx_cl_bulk_status` (`status`),
  CONSTRAINT `fk_cl_bulk_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_cl_bulk_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cl_bulk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_lens_bulk_orders`
--

LOCK TABLES `contact_lens_bulk_orders` WRITE;
/*!40000 ALTER TABLE `contact_lens_bulk_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_lens_bulk_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_lens_order_history`
--

DROP TABLE IF EXISTS `contact_lens_order_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_lens_order_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `event` varchar(150) NOT NULL,
  `performed_by` int(10) unsigned DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_order_history_user` (`performed_by`),
  KEY `idx_order_history_order_date` (`order_id`,`created_at`),
  CONSTRAINT `fk_order_history_order` FOREIGN KEY (`order_id`) REFERENCES `contact_lens_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_history_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_lens_order_history`
--

LOCK TABLES `contact_lens_order_history` WRITE;
/*!40000 ALTER TABLE `contact_lens_order_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_lens_order_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_lens_order_stock_matches`
--

DROP TABLE IF EXISTS `contact_lens_order_stock_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_lens_order_stock_matches` (
  `order_id` int(10) unsigned NOT NULL,
  `lens_stock_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `matched_at` datetime NOT NULL DEFAULT current_timestamp(),
  `matched_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`order_id`,`lens_stock_id`),
  KEY `fk_lens_match_stock` (`lens_stock_id`),
  KEY `fk_lens_match_user` (`matched_by`),
  CONSTRAINT `fk_lens_match_order` FOREIGN KEY (`order_id`) REFERENCES `contact_lens_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lens_match_stock` FOREIGN KEY (`lens_stock_id`) REFERENCES `contact_lens_stock` (`id`),
  CONSTRAINT `fk_lens_match_user` FOREIGN KEY (`matched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_lens_order_stock_matches`
--

LOCK TABLES `contact_lens_order_stock_matches` WRITE;
/*!40000 ALTER TABLE `contact_lens_order_stock_matches` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_lens_order_stock_matches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_lens_orders`
--

DROP TABLE IF EXISTS `contact_lens_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_lens_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `beneficiary_id` int(10) unsigned NOT NULL,
  `original_power` decimal(5,2) DEFAULT NULL,
  `requested_power` decimal(5,2) NOT NULL,
  `current_power` decimal(5,2) DEFAULT NULL,
  `power_changed` tinyint(1) NOT NULL DEFAULT 0,
  `stock_check_result` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','issued','procurement-required') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `submitted_by` int(10) unsigned NOT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `issued_by` int(10) unsigned DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_lens_order_beneficiary` (`beneficiary_id`),
  KEY `fk_lens_order_submitter` (`submitted_by`),
  KEY `fk_lens_order_reviewer` (`reviewed_by`),
  KEY `fk_lens_order_issuer` (`issued_by`),
  KEY `idx_lens_order_status` (`status`),
  CONSTRAINT `fk_lens_order_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`),
  CONSTRAINT `fk_lens_order_issuer` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lens_order_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lens_order_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_lens_orders`
--

LOCK TABLES `contact_lens_orders` WRITE;
/*!40000 ALTER TABLE `contact_lens_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_lens_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_lens_stock`
--

DROP TABLE IF EXISTS `contact_lens_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_lens_stock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `power` decimal(5,2) NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `last_received` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `power` (`power`),
  KEY `fk_lens_stock_supplier` (`supplier_id`),
  CONSTRAINT `fk_lens_stock_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_lens_stock`
--

LOCK TABLES `contact_lens_stock` WRITE;
/*!40000 ALTER TABLE `contact_lens_stock` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_lens_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_lens_unit_history`
--

DROP TABLE IF EXISTS `contact_lens_unit_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_lens_unit_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lens_unit_id` int(10) unsigned NOT NULL,
  `event_type` enum('received','assigned','handover','distributed','returned-to-vendor') NOT NULL,
  `aid_request_id` int(10) unsigned DEFAULT NULL,
  `power` decimal(5,2) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `performed_by` int(10) unsigned NOT NULL,
  `event_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_cl_history_request` (`aid_request_id`),
  KEY `fk_cl_history_user` (`performed_by`),
  KEY `idx_cl_history_unit_date` (`lens_unit_id`,`event_at`),
  CONSTRAINT `fk_cl_history_request` FOREIGN KEY (`aid_request_id`) REFERENCES `aid_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cl_history_unit` FOREIGN KEY (`lens_unit_id`) REFERENCES `contact_lens_units` (`id`),
  CONSTRAINT `fk_cl_history_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_lens_unit_history`
--

LOCK TABLES `contact_lens_unit_history` WRITE;
/*!40000 ALTER TABLE `contact_lens_unit_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_lens_unit_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_lens_units`
--

DROP TABLE IF EXISTS `contact_lens_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_lens_units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unit_code` varchar(40) NOT NULL,
  `bulk_order_id` int(10) unsigned NOT NULL,
  `bulk_order_item_id` int(10) unsigned NOT NULL,
  `power` decimal(5,2) NOT NULL,
  `status` enum('available','reserved','pending-handover','distributed','returned-to-vendor') NOT NULL DEFAULT 'available',
  `aid_request_id` int(10) unsigned DEFAULT NULL,
  `assigned_by` int(10) unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `sso_id` int(10) unsigned DEFAULT NULL,
  `distributed_by` int(10) unsigned DEFAULT NULL,
  `distributed_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `return_reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unit_code` (`unit_code`),
  KEY `fk_cl_unit_order` (`bulk_order_id`),
  KEY `fk_cl_unit_item` (`bulk_order_item_id`),
  KEY `fk_cl_unit_assigner` (`assigned_by`),
  KEY `fk_cl_unit_sso` (`sso_id`),
  KEY `fk_cl_unit_distributor` (`distributed_by`),
  KEY `idx_cl_unit_match` (`power`,`status`),
  KEY `idx_cl_unit_request` (`aid_request_id`),
  CONSTRAINT `fk_cl_unit_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cl_unit_distributor` FOREIGN KEY (`distributed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cl_unit_item` FOREIGN KEY (`bulk_order_item_id`) REFERENCES `contact_lens_bulk_order_items` (`id`),
  CONSTRAINT `fk_cl_unit_order` FOREIGN KEY (`bulk_order_id`) REFERENCES `contact_lens_bulk_orders` (`id`),
  CONSTRAINT `fk_cl_unit_request` FOREIGN KEY (`aid_request_id`) REFERENCES `aid_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cl_unit_sso` FOREIGN KEY (`sso_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_lens_units`
--

LOCK TABLES `contact_lens_units` WRITE;
/*!40000 ALTER TABLE `contact_lens_units` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_lens_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `correction_requests`
--

DROP TABLE IF EXISTS `correction_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `correction_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `stock_receipt_id` int(10) unsigned DEFAULT NULL,
  `record_reference` varchar(100) NOT NULL,
  `error_type` enum('wrong-unit-cost','wrong-quantity','wrong-bill-number','wrong-supplier','wrong-date','wrong-cost','wrong-item','wrong-payment-amount','wrong-check-number','wrong-payment-date','other') NOT NULL,
  `current_value` text NOT NULL,
  `proposed_correction` text NOT NULL,
  `request_reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_reason` text DEFAULT NULL,
  `submitted_by` int(10) unsigned NOT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_correction_reviewer` (`reviewed_by`),
  KEY `idx_correction_status` (`status`),
  KEY `idx_correction_submitter` (`submitted_by`),
  CONSTRAINT `fk_correction_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_correction_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `correction_requests`
--

LOCK TABLES `correction_requests` WRITE;
/*!40000 ALTER TABLE `correction_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `correction_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disability_aid_item_fields`
--

DROP TABLE IF EXISTS `disability_aid_item_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disability_aid_item_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `disability_aid_item_id` int(10) unsigned NOT NULL,
  `field_label` varchar(100) NOT NULL,
  `field_type` enum('text','number','date','image','pdf') NOT NULL DEFAULT 'text',
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disability_aid_item_field` (`disability_aid_item_id`,`field_label`),
  CONSTRAINT `fk_disability_aid_item_field_rule` FOREIGN KEY (`disability_aid_item_id`) REFERENCES `disability_aid_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disability_aid_item_fields`
--

LOCK TABLES `disability_aid_item_fields` WRITE;
/*!40000 ALTER TABLE `disability_aid_item_fields` DISABLE KEYS */;
/*!40000 ALTER TABLE `disability_aid_item_fields` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disability_aid_items`
--

DROP TABLE IF EXISTS `disability_aid_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disability_aid_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `disability_type_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `restriction_months` int(10) unsigned NOT NULL DEFAULT 0,
  `beneficiary_field_label` varchar(100) DEFAULT NULL,
  `beneficiary_field_type` enum('text','number') NOT NULL DEFAULT 'text',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disability_aid_item` (`disability_type_id`,`item_id`),
  KEY `fk_disability_aid_inventory` (`item_id`),
  KEY `fk_disability_aid_creator` (`created_by`),
  CONSTRAINT `fk_disability_aid_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_disability_aid_inventory` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_disability_aid_type` FOREIGN KEY (`disability_type_id`) REFERENCES `disability_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disability_aid_items`
--

LOCK TABLES `disability_aid_items` WRITE;
/*!40000 ALTER TABLE `disability_aid_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `disability_aid_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disability_item_prohibitions`
--

DROP TABLE IF EXISTS `disability_item_prohibitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disability_item_prohibitions` (
  `disability_aid_item_id` int(10) unsigned NOT NULL,
  `prohibited_item_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`disability_aid_item_id`,`prohibited_item_id`),
  KEY `fk_item_prohibition_item` (`prohibited_item_id`),
  CONSTRAINT `fk_item_prohibition_item` FOREIGN KEY (`prohibited_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_prohibition_rule` FOREIGN KEY (`disability_aid_item_id`) REFERENCES `disability_aid_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disability_item_prohibitions`
--

LOCK TABLES `disability_item_prohibitions` WRITE;
/*!40000 ALTER TABLE `disability_item_prohibitions` DISABLE KEYS */;
/*!40000 ALTER TABLE `disability_item_prohibitions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disability_types`
--

DROP TABLE IF EXISTS `disability_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disability_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disability_type_name` (`name`),
  KEY `fk_disability_type_creator` (`created_by`),
  CONSTRAINT `fk_disability_type_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disability_types`
--

LOCK TABLES `disability_types` WRITE;
/*!40000 ALTER TABLE `disability_types` DISABLE KEYS */;
INSERT INTO `disability_types` VALUES (1,'Vision Impairment','active',1,NULL,'2026-09-16 09:32:12','2026-09-16 09:32:12');
/*!40000 ALTER TABLE `disability_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distributions`
--

DROP TABLE IF EXISTS `distributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `distributions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aid_request_id` int(10) unsigned DEFAULT NULL,
  `beneficiary_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `distribution_type` enum('direct','request-based') NOT NULL,
  `source` enum('officer-pool','vision-camp') NOT NULL DEFAULT 'officer-pool',
  `notes` varchar(1000) DEFAULT NULL,
  `distributed_by` int(10) unsigned NOT NULL,
  `distributed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `aid_request_id` (`aid_request_id`),
  KEY `fk_distribution_item` (`item_id`),
  KEY `fk_distribution_officer` (`distributed_by`),
  KEY `idx_distribution_beneficiary` (`beneficiary_id`),
  KEY `idx_distribution_date` (`distributed_at`),
  CONSTRAINT `fk_distribution_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`),
  CONSTRAINT `fk_distribution_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_distribution_officer` FOREIGN KEY (`distributed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_distribution_request` FOREIGN KEY (`aid_request_id`) REFERENCES `aid_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distributions`
--

LOCK TABLES `distributions` WRITE;
/*!40000 ALTER TABLE `distributions` DISABLE KEYS */;
/*!40000 ALTER TABLE `distributions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `districts`
--

DROP TABLE IF EXISTS `districts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `districts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_district_creator` (`created_by`),
  CONSTRAINT `fk_district_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `districts`
--

LOCK TABLES `districts` WRITE;
/*!40000 ALTER TABLE `districts` DISABLE KEYS */;
/*!40000 ALTER TABLE `districts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `division_inventory`
--

DROP TABLE IF EXISTS `division_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `division_inventory` (
  `ds_division_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ds_division_id`,`item_id`),
  KEY `fk_divstock_item` (`item_id`),
  CONSTRAINT `fk_divstock_ds` FOREIGN KEY (`ds_division_id`) REFERENCES `ds_divisions` (`id`),
  CONSTRAINT `fk_divstock_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `division_inventory`
--

LOCK TABLES `division_inventory` WRITE;
/*!40000 ALTER TABLE `division_inventory` DISABLE KEYS */;
/*!40000 ALTER TABLE `division_inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ds_divisions`
--

DROP TABLE IF EXISTS `ds_divisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ds_divisions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `district_id` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `division_type` enum('official','service-centre') NOT NULL DEFAULT 'official',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ds_district_name` (`district_id`,`name`),
  KEY `fk_ds_creator` (`created_by`),
  CONSTRAINT `fk_ds_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_ds_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ds_divisions`
--

LOCK TABLES `ds_divisions` WRITE;
/*!40000 ALTER TABLE `ds_divisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `ds_divisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eligibility_rules`
--

DROP TABLE IF EXISTS `eligibility_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eligibility_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `restriction_months` int(10) unsigned NOT NULL DEFAULT 0,
  `allow_multiple` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `updated_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_id` (`category_id`),
  KEY `fk_rule_user` (`updated_by`),
  CONSTRAINT `fk_rule_category` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`id`),
  CONSTRAINT `fk_rule_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eligibility_rules`
--

LOCK TABLES `eligibility_rules` WRITE;
/*!40000 ALTER TABLE `eligibility_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `eligibility_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gn_divisions`
--

DROP TABLE IF EXISTS `gn_divisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gn_divisions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ds_division_id` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gn_ds_name` (`ds_division_id`,`name`),
  KEY `fk_gn_creator` (`created_by`),
  CONSTRAINT `fk_gn_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_gn_ds` FOREIGN KEY (`ds_division_id`) REFERENCES `ds_divisions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gn_divisions`
--

LOCK TABLES `gn_divisions` WRITE;
/*!40000 ALTER TABLE `gn_divisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `gn_divisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goods_fulfillments`
--

DROP TABLE IF EXISTS `goods_fulfillments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_fulfillments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `goods_request_id` int(10) unsigned NOT NULL,
  `aid_request_id` int(10) unsigned NOT NULL,
  `subject_officer_id` int(10) unsigned NOT NULL,
  `sso_id` int(10) unsigned DEFAULT NULL,
  `lens_unit_identifier` varchar(40) DEFAULT NULL,
  `status` enum('with-subject-officer','pending-sso-handover','distributed') NOT NULL DEFAULT 'with-subject-officer',
  `handed_to_sso_at` datetime DEFAULT NULL,
  `distributed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `aid_request_id` (`aid_request_id`),
  UNIQUE KEY `lens_unit_identifier` (`lens_unit_identifier`),
  KEY `fk_fulfillment_goods` (`goods_request_id`),
  KEY `idx_fulfillment_subject_status` (`subject_officer_id`,`status`),
  KEY `idx_fulfillment_sso_status` (`sso_id`,`status`),
  CONSTRAINT `fk_fulfillment_aid` FOREIGN KEY (`aid_request_id`) REFERENCES `aid_requests` (`id`),
  CONSTRAINT `fk_fulfillment_goods` FOREIGN KEY (`goods_request_id`) REFERENCES `goods_requests` (`id`),
  CONSTRAINT `fk_fulfillment_sso` FOREIGN KEY (`sso_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fulfillment_subject` FOREIGN KEY (`subject_officer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goods_fulfillments`
--

LOCK TABLES `goods_fulfillments` WRITE;
/*!40000 ALTER TABLE `goods_fulfillments` DISABLE KEYS */;
/*!40000 ALTER TABLE `goods_fulfillments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goods_request_aid_requests`
--

DROP TABLE IF EXISTS `goods_request_aid_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_request_aid_requests` (
  `goods_request_id` int(10) unsigned NOT NULL,
  `aid_request_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`goods_request_id`,`aid_request_id`),
  UNIQUE KEY `uq_goods_batch_aid_request` (`aid_request_id`),
  CONSTRAINT `fk_goods_batch_aid` FOREIGN KEY (`aid_request_id`) REFERENCES `aid_requests` (`id`),
  CONSTRAINT `fk_goods_batch_request` FOREIGN KEY (`goods_request_id`) REFERENCES `goods_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goods_request_aid_requests`
--

LOCK TABLES `goods_request_aid_requests` WRITE;
/*!40000 ALTER TABLE `goods_request_aid_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `goods_request_aid_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goods_requests`
--

DROP TABLE IF EXISTS `goods_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aid_request_id` int(10) unsigned DEFAULT NULL,
  `request_batch_ref` varchar(40) DEFAULT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `destination_ds_division_id` int(10) unsigned NOT NULL,
  `destination_sso_id` int(10) unsigned DEFAULT NULL,
  `justification` varchar(1000) NOT NULL,
  `status` enum('pending-admin-approval','approved-awaiting-dispatch','dispatched','rejected') NOT NULL DEFAULT 'pending-admin-approval',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `requested_by` int(10) unsigned NOT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `dispatched_by` int(10) unsigned DEFAULT NULL,
  `released_to_subject_id` int(10) unsigned DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `allocated_to_sso_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_goods_item` (`item_id`),
  KEY `fk_goods_requester` (`requested_by`),
  KEY `fk_goods_approver` (`approved_by`),
  KEY `fk_goods_dispatcher` (`dispatched_by`),
  KEY `idx_goods_status` (`status`),
  KEY `idx_goods_destination` (`destination_ds_division_id`),
  KEY `idx_goods_request_batch_ref` (`request_batch_ref`),
  KEY `idx_goods_destination_sso` (`destination_sso_id`),
  CONSTRAINT `fk_goods_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_goods_destination` FOREIGN KEY (`destination_ds_division_id`) REFERENCES `ds_divisions` (`id`),
  CONSTRAINT `fk_goods_destination_sso` FOREIGN KEY (`destination_sso_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_goods_dispatcher` FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_goods_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_goods_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goods_requests`
--

LOCK TABLES `goods_requests` WRITE;
/*!40000 ALTER TABLE `goods_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `goods_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'General Aid',
  `category_id` int(10) unsigned DEFAULT NULL,
  `variety` varchar(100) NOT NULL DEFAULT '',
  `quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_item_variety` (`item_name`,`variety`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_categories`
--

DROP TABLE IF EXISTS `item_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `distribution_type` enum('direct','request-based') NOT NULL,
  `returnable` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_category_creator` (`created_by`),
  CONSTRAINT `fk_category_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_categories`
--

LOCK TABLES `item_categories` WRITE;
/*!40000 ALTER TABLE `item_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `item_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_returns`
--

DROP TABLE IF EXISTS `item_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_returns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `distribution_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `item_condition` enum('good','damaged','unusable') NOT NULL,
  `reusable` tinyint(1) NOT NULL,
  `restore_to` enum('officer-pool','central-stock','removed') NOT NULL,
  `processed_by` int(10) unsigned NOT NULL,
  `processed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_return_processor` (`processed_by`),
  KEY `idx_return_distribution` (`distribution_id`),
  CONSTRAINT `fk_return_distribution` FOREIGN KEY (`distribution_id`) REFERENCES `distributions` (`id`),
  CONSTRAINT `fk_return_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_returns`
--

LOCK TABLES `item_returns` WRITE;
/*!40000 ALTER TABLE `item_returns` DISABLE KEYS */;
/*!40000 ALTER TABLE `item_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lens_requests`
--

DROP TABLE IF EXISTS `lens_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lens_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `camp_id` int(10) unsigned NOT NULL,
  `attendee_id` int(10) unsigned DEFAULT NULL,
  `beneficiary_name` varchar(150) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `original_power` decimal(5,2) NOT NULL,
  `new_power` decimal(5,2) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `status` enum('pending','approved','rejected','fulfilled') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_lens_request_camp` (`camp_id`),
  KEY `fk_lens_request_attendee` (`attendee_id`),
  KEY `fk_lens_request_reviewer` (`reviewed_by`),
  KEY `idx_lens_request_status` (`status`),
  KEY `idx_lens_request_nic` (`nic`),
  CONSTRAINT `fk_lens_request_attendee` FOREIGN KEY (`attendee_id`) REFERENCES `vision_camp_attendees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lens_request_camp` FOREIGN KEY (`camp_id`) REFERENCES `vision_camps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lens_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lens_requests`
--

LOCK TABLES `lens_requests` WRITE;
/*!40000 ALTER TABLE `lens_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `lens_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lens_unit_history`
--

DROP TABLE IF EXISTS `lens_unit_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lens_unit_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` int(10) unsigned NOT NULL,
  `event` varchar(150) NOT NULL,
  `performed_by` int(10) unsigned DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_lens_history_user` (`performed_by`),
  KEY `idx_lens_history_unit_date` (`unit_id`,`created_at`),
  CONSTRAINT `fk_lens_history_unit` FOREIGN KEY (`unit_id`) REFERENCES `lens_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lens_history_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lens_unit_history`
--

LOCK TABLES `lens_unit_history` WRITE;
/*!40000 ALTER TABLE `lens_unit_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `lens_unit_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lens_units`
--

DROP TABLE IF EXISTS `lens_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lens_units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `camp_id` int(10) unsigned NOT NULL,
  `power` decimal(5,2) NOT NULL,
  `status` enum('available','reserved','issued','damaged','returned') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lens_unit_camp_status` (`camp_id`,`status`),
  KEY `idx_lens_unit_power` (`power`),
  CONSTRAINT `fk_lens_unit_camp` FOREIGN KEY (`camp_id`) REFERENCES `vision_camps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lens_units`
--

LOCK TABLES `lens_units` WRITE;
/*!40000 ALTER TABLE `lens_units` DISABLE KEYS */;
/*!40000 ALTER TABLE `lens_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_reads`
--

DROP TABLE IF EXISTS `notification_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_reads` (
  `user_id` int(10) unsigned NOT NULL,
  `notification_key` varchar(160) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`notification_key`),
  CONSTRAINT `fk_notification_read_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_reads`
--

LOCK TABLES `notification_reads` WRITE;
/*!40000 ALTER TABLE `notification_reads` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_reads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `officer_pools`
--

DROP TABLE IF EXISTS `officer_pools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `officer_pools` (
  `officer_id` int(10) unsigned NOT NULL,
  `ds_division_id` int(10) unsigned DEFAULT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `allocated` int(10) unsigned NOT NULL DEFAULT 0,
  `distributed` int(10) unsigned NOT NULL DEFAULT 0,
  `reused` int(10) unsigned NOT NULL DEFAULT 0,
  `returned` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`officer_id`,`item_id`),
  KEY `fk_pool_item` (`item_id`),
  CONSTRAINT `fk_pool_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_pool_officer` FOREIGN KEY (`officer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `officer_pools`
--

LOCK TABLES `officer_pools` WRITE;
/*!40000 ALTER TABLE `officer_pools` DISABLE KEYS */;
/*!40000 ALTER TABLE `officer_pools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pool_allocations`
--

DROP TABLE IF EXISTS `pool_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pool_allocations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `officer_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `allocated_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_allocation_officer` (`officer_id`),
  KEY `fk_allocation_item` (`item_id`),
  KEY `fk_allocation_user` (`allocated_by`),
  CONSTRAINT `fk_allocation_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_allocation_officer` FOREIGN KEY (`officer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_allocation_user` FOREIGN KEY (`allocated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pool_allocations`
--

LOCK TABLES `pool_allocations` WRITE;
/*!40000 ALTER TABLE `pool_allocations` DISABLE KEYS */;
/*!40000 ALTER TABLE `pool_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registration_requests`
--

DROP TABLE IF EXISTS `registration_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registration_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('subject-officer','store-keeper','social-service-officer') NOT NULL,
  `division` varchar(120) DEFAULT NULL,
  `district_id` int(10) unsigned DEFAULT NULL,
  `ds_division_id` int(10) unsigned DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `email_status` enum('not-sent','sent','failed') NOT NULL DEFAULT 'not-sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_registration_reviewed_by` (`reviewed_by`),
  KEY `idx_registration_status` (`status`),
  KEY `idx_registration_email` (`email`),
  KEY `fk_registration_district` (`district_id`),
  KEY `fk_registration_ds_division` (`ds_division_id`),
  CONSTRAINT `fk_registration_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_ds_division` FOREIGN KEY (`ds_division_id`) REFERENCES `ds_divisions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registration_requests`
--

LOCK TABLES `registration_requests` WRITE;
/*!40000 ALTER TABLE `registration_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `registration_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_receipts`
--

DROP TABLE IF EXISTS `stock_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_receipts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `power` decimal(5,2) DEFAULT NULL,
  `power_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`power_breakdown`)),
  `unit_cost` decimal(12,2) NOT NULL,
  `total_cost` decimal(14,2) NOT NULL,
  `bill_number` varchar(100) NOT NULL,
  `received_date` date NOT NULL,
  `payment_status` enum('fully-paid','partially-paid','unpaid') NOT NULL,
  `check_number` varchar(100) DEFAULT NULL,
  `paid_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `received_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  KEY `fk_receipt_supplier` (`supplier_id`),
  KEY `fk_receipt_item` (`item_id`),
  KEY `fk_receipt_user` (`received_by`),
  KEY `idx_receipt_date` (`received_date`),
  KEY `idx_receipt_payment` (`payment_status`),
  CONSTRAINT `fk_receipt_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_receipt_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_receipt_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_receipts`
--

LOCK TABLES `stock_receipts` WRITE;
/*!40000 ALTER TABLE `stock_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_authorized_items`
--

DROP TABLE IF EXISTS `supplier_authorized_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_authorized_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `valid_from` date DEFAULT NULL,
  `validity_period` smallint(5) unsigned DEFAULT NULL,
  `validity_unit` enum('months','years') DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deactivation_reason` varchar(500) DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `authorized_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supplier_authorized_supplier_item` (`supplier_id`,`item_id`),
  KEY `fk_supplier_item_item` (`item_id`),
  KEY `fk_supplier_item_user` (`authorized_by`),
  CONSTRAINT `fk_supplier_item_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_item_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_item_user` FOREIGN KEY (`authorized_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_authorized_items`
--

LOCK TABLES `supplier_authorized_items` WRITE;
/*!40000 ALTER TABLE `supplier_authorized_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_authorized_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_payments`
--

DROP TABLE IF EXISTS `supplier_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int(10) unsigned NOT NULL,
  `receipt_id` int(10) unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `check_number` varchar(100) DEFAULT NULL,
  `check_date` date DEFAULT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_payment_user` (`recorded_by`),
  KEY `idx_supplier_payment_supplier` (`supplier_id`),
  KEY `idx_supplier_payment_receipt` (`receipt_id`),
  KEY `idx_supplier_payment_date` (`payment_date`),
  CONSTRAINT `fk_payment_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `stock_receipts` (`id`),
  CONSTRAINT `fk_payment_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_payments`
--

LOCK TABLES `supplier_payments` WRITE;
/*!40000 ALTER TABLE `supplier_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `supplier_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) NOT NULL,
  `contact_person` varchar(120) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `validity_period` smallint(5) unsigned DEFAULT NULL,
  `validity_unit` enum('months','years') DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deactivation_reason` varchar(500) DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_name` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `setting_type` enum('integer','boolean','string') NOT NULL,
  `setting_group` enum('general','notification') NOT NULL,
  `description` varchar(255) NOT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `fk_settings_updated_by` (`updated_by`),
  KEY `idx_settings_group` (`setting_group`),
  CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_notifications`
--

DROP TABLE IF EXISTS `user_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `notification_key` varchar(160) NOT NULL,
  `category` varchar(100) NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` varchar(500) NOT NULL,
  `target_url` varchar(500) NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_notification` (`user_id`,`notification_key`),
  KEY `idx_user_notification_unread` (`user_id`,`read_at`,`created_at`),
  CONSTRAINT `fk_user_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_notifications`
--

LOCK TABLES `user_notifications` WRITE;
/*!40000 ALTER TABLE `user_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(120) NOT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `division` varchar(120) DEFAULT NULL,
  `district_id` int(10) unsigned DEFAULT NULL,
  `ds_division_id` int(10) unsigned DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','subject-officer','store-keeper','social-service-officer') NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deactivation_reason` varchar(500) DEFAULT NULL,
  `deactivated_by` int(10) unsigned DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `fk_user_deactivator` (`deactivated_by`),
  CONSTRAINT `fk_user_deactivator` FOREIGN KEY (`deactivated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vision_camp_attendees`
--

DROP TABLE IF EXISTS `vision_camp_attendees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vision_camp_attendees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `camp_id` int(10) unsigned NOT NULL,
  `beneficiary_id` int(10) unsigned DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `outcome` varchar(120) DEFAULT NULL,
  `lens_power` decimal(5,2) DEFAULT NULL,
  `retest_power` decimal(5,2) DEFAULT NULL,
  `lens_status` enum('not-required','pending','available','ordered','issued') NOT NULL DEFAULT 'not-required',
  `reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_camp_attendee_nic` (`camp_id`,`nic`),
  KEY `fk_camp_attendee_beneficiary` (`beneficiary_id`),
  KEY `idx_camp_attendee_lens_status` (`lens_status`),
  CONSTRAINT `fk_camp_attendee_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_camp_attendee_camp` FOREIGN KEY (`camp_id`) REFERENCES `vision_camps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vision_camp_attendees`
--

LOCK TABLES `vision_camp_attendees` WRITE;
/*!40000 ALTER TABLE `vision_camp_attendees` DISABLE KEYS */;
/*!40000 ALTER TABLE `vision_camp_attendees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vision_camp_beneficiaries`
--

DROP TABLE IF EXISTS `vision_camp_beneficiaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vision_camp_beneficiaries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `camp_id` int(10) unsigned NOT NULL,
  `beneficiary_id` int(10) unsigned DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `outcome` enum('pending','distributed','handed-over','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `processed_by` int(10) unsigned DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_camp_beneficiary_nic` (`camp_id`,`nic`),
  KEY `idx_camp_beneficiary_record` (`beneficiary_id`),
  CONSTRAINT `fk_camp_beneficiary_camp` FOREIGN KEY (`camp_id`) REFERENCES `vision_camps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_camp_beneficiary_record` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vision_camp_beneficiaries`
--

LOCK TABLES `vision_camp_beneficiaries` WRITE;
/*!40000 ALTER TABLE `vision_camp_beneficiaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `vision_camp_beneficiaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vision_camp_handovers`
--

DROP TABLE IF EXISTS `vision_camp_handovers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vision_camp_handovers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `camp_id` int(10) unsigned NOT NULL,
  `camp_beneficiary_id` int(10) unsigned DEFAULT NULL,
  `beneficiary_id` int(10) unsigned DEFAULT NULL,
  `item_id` int(10) unsigned DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `officer_id` int(10) unsigned NOT NULL,
  `status` enum('pending','distributed') NOT NULL DEFAULT 'pending',
  `handed_by` int(10) unsigned NOT NULL,
  `handed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `distributed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_handover_camp` (`camp_id`),
  KEY `fk_handover_beneficiary` (`beneficiary_id`),
  KEY `fk_handover_item` (`item_id`),
  KEY `fk_handover_subject` (`handed_by`),
  KEY `idx_handover_officer_status` (`officer_id`,`status`),
  CONSTRAINT `fk_handover_beneficiary` FOREIGN KEY (`beneficiary_id`) REFERENCES `beneficiaries` (`id`),
  CONSTRAINT `fk_handover_camp` FOREIGN KEY (`camp_id`) REFERENCES `vision_camps` (`id`),
  CONSTRAINT `fk_handover_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_handover_officer` FOREIGN KEY (`officer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_handover_subject` FOREIGN KEY (`handed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vision_camp_handovers`
--

LOCK TABLES `vision_camp_handovers` WRITE;
/*!40000 ALTER TABLE `vision_camp_handovers` DISABLE KEYS */;
/*!40000 ALTER TABLE `vision_camp_handovers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vision_camps`
--

DROP TABLE IF EXISTS `vision_camps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vision_camps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ds_division_id` int(10) unsigned NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
  `justification` varchar(1000) DEFAULT NULL,
  `proposed_date` date DEFAULT NULL,
  `people_identified` int(10) unsigned DEFAULT NULL,
  `attended_count` int(10) unsigned DEFAULT NULL,
  `distributed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `handed_over_count` int(10) unsigned NOT NULL DEFAULT 0,
  `stage` enum('awaiting-vendor-approval','vendor-approved','awaiting-goods-release','distribution-in-progress','completed','rejected') NOT NULL DEFAULT 'awaiting-vendor-approval',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `requested_by` int(10) unsigned NOT NULL,
  `social_service_officer_id` int(10) unsigned DEFAULT NULL,
  `vendor_reviewed_by` int(10) unsigned DEFAULT NULL,
  `vendor_reviewed_at` datetime DEFAULT NULL,
  `goods_released_by` int(10) unsigned DEFAULT NULL,
  `goods_released_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_camp_ds` (`ds_division_id`),
  KEY `fk_camp_supplier` (`supplier_id`),
  KEY `fk_camp_requester` (`requested_by`),
  KEY `fk_camp_vendor_reviewer` (`vendor_reviewed_by`),
  KEY `fk_camp_release_reviewer` (`goods_released_by`),
  KEY `idx_camp_stage` (`stage`),
  CONSTRAINT `fk_camp_ds` FOREIGN KEY (`ds_division_id`) REFERENCES `ds_divisions` (`id`),
  CONSTRAINT `fk_camp_release_reviewer` FOREIGN KEY (`goods_released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_camp_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_camp_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_camp_vendor_reviewer` FOREIGN KEY (`vendor_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vision_camps`
--

LOCK TABLES `vision_camps` WRITE;
/*!40000 ALTER TABLE `vision_camps` DISABLE KEYS */;
/*!40000 ALTER TABLE `vision_camps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `widms_data_migrations`
--

DROP TABLE IF EXISTS `widms_data_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `widms_data_migrations` (
  `migration_key` varchar(120) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `widms_data_migrations`
--

LOCK TABLES `widms_data_migrations` WRITE;
/*!40000 ALTER TABLE `widms_data_migrations` DISABLE KEYS */;
INSERT INTO `widms_data_migrations` VALUES ('2026-09-clear-default-disabilities','2026-09-16 09:32:10'),('2026-09-clear-remaining-inventory','2026-09-16 09:32:10'),('2026-09-item-eligibility-redesign','2026-09-16 09:32:09');
/*!40000 ALTER TABLE `widms_data_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'widms'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-16 15:04:26
