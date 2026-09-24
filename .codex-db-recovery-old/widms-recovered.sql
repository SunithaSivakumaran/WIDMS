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
) ENGINE=InnoDB AUTO_INCREMENT=365 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 05:58:54'),(2,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 06:11:28'),(3,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 06:11:36'),(4,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 06:22:19'),(5,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 06:22:30'),(6,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 06:25:03'),(7,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 06:44:10'),(8,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 07:08:48'),(9,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 07:09:05'),(10,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 07:11:51'),(11,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 07:38:07'),(12,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 07:49:23'),(13,1,'admin','Users','Approved user registration request','REG-001','approved','2026-09-03 07:49:52'),(14,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 07:53:34'),(15,1,'admin','Users','Approved user registration request','REG-002','approved','2026-09-03 07:53:47'),(16,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 08:03:19'),(17,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 08:04:53'),(18,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 08:09:21'),(19,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 08:12:23'),(20,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 08:21:15'),(21,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 08:32:38'),(22,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 08:44:07'),(23,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 10:13:51'),(24,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 10:28:37'),(25,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 17:45:34'),(26,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 17:46:19'),(27,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 17:46:45'),(28,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 17:47:16'),(29,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-03 18:10:28'),(30,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-04 17:14:23'),(31,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:00:04'),(32,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:06:58'),(33,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:07:57'),(34,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:09:22'),(35,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:11:33'),(36,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:11:46'),(37,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:25:17'),(38,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:38:34'),(39,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:38:46'),(40,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:39:02'),(41,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:39:14'),(42,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 07:46:21'),(43,1,'admin','Users','Rejected user registration request','REG-003','rejected','2026-09-05 07:46:40'),(44,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 08:05:27'),(45,2,'subject-officer','Disability Aid Configuration','Added disability type','vision problem','done','2026-09-05 08:47:41'),(46,2,'subject-officer','Disability Aid Configuration','Configured item eligibility','ITEM-61','done','2026-09-05 08:48:09'),(47,2,'subject-officer','Disability Aid Configuration','Added disability type','vision problem','done','2026-09-05 08:52:16'),(48,2,'subject-officer','Disability Aid Configuration','Configured item eligibility','ITEM-70','done','2026-09-05 08:52:32'),(49,2,'subject-officer','Disability Aid Configuration','Updated full eligibility rule','RULE-2','done','2026-09-05 09:10:58'),(50,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 09:11:34'),(51,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 09:12:16'),(52,2,'subject-officer','Disability Aid Configuration','Updated full eligibility rule','RULE-2','done','2026-09-05 09:14:22'),(53,2,'subject-officer','Disability Aid Configuration','Updated full eligibility rule','RULE-2','done','2026-09-05 09:20:03'),(54,2,'subject-officer','Disability Aid Configuration','Updated full eligibility rule','RULE-2','done','2026-09-05 09:23:32'),(55,2,'subject-officer','Disability Aid Configuration','Updated full eligibility rule','RULE-2','done','2026-09-05 09:24:09'),(56,2,'subject-officer','Disability Aid Configuration','Added disability type','vision problem','done','2026-09-05 09:33:40'),(57,2,'subject-officer','Disability Aid Configuration','Added disability type','hearing problem','done','2026-09-05 09:34:00'),(58,2,'subject-officer','Disability Aid Configuration','Added disability type','Hearing problem','done','2026-09-05 09:34:25'),(59,2,'subject-officer','Disability Aid Configuration','Added disability type','vision proble','done','2026-09-05 09:34:40'),(60,2,'subject-officer','Disability Aid Configuration','Configured item eligibility','ITEM-70','done','2026-09-05 09:34:52'),(61,2,'subject-officer','Disability Aid Configuration','Deleted eligibility rule','RULE-3','deleted','2026-09-05 09:34:58'),(62,2,'subject-officer','Disability Aid Configuration','Configured item eligibility','ITEM-71','done','2026-09-05 10:04:25'),(63,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 10:05:29'),(64,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:09:39'),(65,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:19:59'),(66,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:21:27'),(67,2,'subject-officer','Disability Aid Configuration','Deleted eligibility rule','RULE-4','deleted','2026-09-05 15:21:38'),(68,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:21:47'),(69,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:22:03'),(70,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:27:20'),(71,2,'subject-officer','Disability Aid Configuration','Deleted disability type','vision proble','deleted','2026-09-05 15:28:56'),(72,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:29:10'),(73,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:29:25'),(74,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:29:35'),(75,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:32:51'),(76,2,'subject-officer','Disability Aid Configuration','Configured item eligibility','ITEM-72','done','2026-09-05 15:33:31'),(77,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:33:44'),(78,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:34:00'),(79,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 15:34:26'),(80,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0001','pending','2026-09-05 15:56:24'),(81,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 16:02:34'),(82,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0002','pending','2026-09-05 16:03:39'),(83,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0003','pending','2026-09-05 16:06:29'),(84,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 16:11:10'),(85,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 16:15:46'),(86,4,'social-service-officer','Aid Requests','Updated aid request','AR-0003','pending','2026-09-05 16:17:46'),(87,4,'social-service-officer','Aid Requests','Removed editable aid request','AR-0003','removed','2026-09-05 16:21:05'),(88,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 16:24:53'),(89,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 16:27:34'),(90,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 16:30:18'),(91,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 16:49:37'),(92,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0004','pending','2026-09-05 16:59:15'),(93,4,'social-service-officer','Aid Requests','Removed editable aid request','AR-0004','removed','2026-09-05 17:01:29'),(94,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 17:06:19'),(95,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 17:07:19'),(96,2,'subject-officer','Disability Aid Configuration','Updated full eligibility rule','RULE-2','done','2026-09-05 17:40:22'),(97,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 17:40:28'),(98,4,'social-service-officer','Aid Requests','Removed editable aid request','AR-0002','removed','2026-09-05 17:40:48'),(99,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0005','pending','2026-09-05 17:41:30'),(100,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 17:41:41'),(101,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 17:44:45'),(102,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 17:45:17'),(103,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 20:20:45'),(104,2,'subject-officer','Disability Aid Configuration','Configured item eligibility and beneficiary field','ITEM-90','done','2026-09-05 20:31:15'),(105,2,'subject-officer','Disability Aid Configuration','Updated full eligibility rule','RULE-6','done','2026-09-05 20:31:36'),(106,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 20:31:44'),(107,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 20:32:07'),(108,2,'subject-officer','Disability Aid Configuration','Updated full eligibility rule','RULE-2','done','2026-09-05 20:32:32'),(109,2,'subject-officer','Disability Aid Configuration','Configured item eligibility and beneficiary field','ITEM-91','done','2026-09-05 20:37:37'),(110,2,'subject-officer','Disability Aid Configuration','Updated item rule and beneficiary field','RULE-7','done','2026-09-05 20:47:10'),(111,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 20:47:19'),(112,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-05 20:47:53'),(113,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 06:28:57'),(114,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 06:29:39'),(115,2,'subject-officer','Disability Aid Configuration','Updated item rule and beneficiary fields','RULE-2','done','2026-09-06 06:30:32'),(116,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 06:30:41'),(117,2,'subject-officer','Disability Aid Configuration','Updated item rule and beneficiary fields','RULE-2','done','2026-09-06 06:31:04'),(118,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 06:31:13'),(119,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0006','pending','2026-09-06 06:32:44'),(120,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 06:36:16'),(121,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 06:38:19'),(122,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 06:42:09'),(123,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 06:43:14'),(124,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 06:46:03'),(125,2,'subject-officer','Disability Aid Configuration','Updated item rule and beneficiary fields','RULE-2','done','2026-09-06 06:46:44'),(126,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 06:46:59'),(127,4,'social-service-officer','Aid Requests','Updated aid request','AR-0006','pending','2026-09-06 06:48:33'),(128,4,'social-service-officer','Aid Requests','Updated aid request','AR-0006','pending','2026-09-06 06:58:46'),(129,4,'social-service-officer','Aid Requests','Updated aid request','AR-0006','pending','2026-09-06 07:03:33'),(130,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 07:22:57'),(131,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 07:31:05'),(132,4,'social-service-officer','Aid Requests','Updated aid request','AR-0006','pending','2026-09-06 07:40:42'),(133,4,'social-service-officer','Aid Requests','Updated aid request','AR-0006','pending','2026-09-06 07:41:02'),(134,4,'social-service-officer','Aid Requests','Updated aid request','AR-0006','pending','2026-09-06 07:47:11'),(135,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 07:48:22'),(136,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 07:51:04'),(137,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 08:05:01'),(138,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 08:05:25'),(139,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 08:05:55'),(140,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-06 13:20:37'),(141,2,'subject-officer','Suppliers','Registered supplier and allocated 1 aid item(s) — suki co','SUP-51','done','2026-09-06 13:40:14'),(142,2,'subject-officer','Suppliers','Deactivated supplier — suki co. Reason: for fun','SUP-51','done','2026-09-06 13:50:41'),(143,2,'subject-officer','Suppliers','Reactivated supplier — suki co','SUP-51','done','2026-09-06 13:50:53'),(144,2,'subject-officer','Suppliers','Added a product agreement to supplier — suki co','SUP-51','done','2026-09-06 14:13:02'),(145,2,'subject-officer','Suppliers','Permanently deactivated supplier — suki co. Reason: fun','SUP-51','done','2026-09-06 14:15:12'),(146,2,'subject-officer','Suppliers','Registered supplier and allocated its first product — suki co','SUP-52','done','2026-09-06 14:41:24'),(147,2,'subject-officer','Suppliers','Added a product agreement to supplier — suki co','SUP-52','done','2026-09-06 15:03:18'),(148,2,'subject-officer','Suppliers','Permanently deactivated supplier — suki co. Reason: for fun','SUP-52','done','2026-09-06 15:47:03'),(149,2,'subject-officer','Suppliers','Permanently deactivated product agreement — suki co / specs. Reason: for bfun','SUP-52','done','2026-09-06 15:52:07'),(150,2,'subject-officer','Suppliers','Permanently deactivated product agreement — suki co / Contact Lens. Reason: fun','SUP-52','done','2026-09-06 15:54:37'),(151,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 06:19:24'),(152,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 06:24:17'),(153,2,'subject-officer','Disability Aid Configuration','Updated item rule and beneficiary fields','RULE-2','done','2026-09-07 07:00:32'),(154,2,'subject-officer','Disability Aid Configuration','Updated item rule and beneficiary fields','RULE-7','done','2026-09-07 07:00:49'),(155,2,'subject-officer','Suppliers','Added a product agreement to supplier — suki co','SUP-52','done','2026-09-07 07:14:29'),(156,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 07:14:59'),(157,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 07:15:08'),(158,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 07:15:16'),(159,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 07:18:51'),(160,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0007','pending','2026-09-07 07:19:57'),(161,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0008','pending','2026-09-07 07:24:47'),(162,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0009','pending','2026-09-07 08:09:29'),(163,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0010','pending','2026-09-07 08:11:30'),(164,4,'social-service-officer','Aid Requests','Updated aid request','AR-0010','pending','2026-09-07 08:11:38'),(165,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 08:12:20'),(166,1,'admin','Aid Requests','Approved aid request','AR-0009','approved','2026-09-07 08:15:57'),(167,1,'admin','Aid Requests','Rejected aid request','AR-0010','rejected','2026-09-07 08:29:03'),(168,1,'admin','Aid Requests','Approved aid request','AR-0008','approved','2026-09-07 08:37:48'),(169,1,'admin','Aid Requests','Approved aid request','AR-0007','approved','2026-09-07 08:40:25'),(170,1,'admin','Aid Requests','Approved aid request','AR-0006','approved','2026-09-07 08:43:46'),(171,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 08:44:43'),(172,2,'subject-officer','Disability Aid Configuration','Added disability type','mobility impairment','done','2026-09-07 08:45:34'),(173,2,'subject-officer','Disability Aid Configuration','Configured item eligibility and beneficiary fields','ITEM-92','done','2026-09-07 08:45:56'),(174,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 08:46:05'),(175,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0011','pending','2026-09-07 08:47:56'),(176,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 08:48:27'),(177,2,'subject-officer','Suppliers','Registered supplier and allocated its first product — suki and j','SUP-53','done','2026-09-07 08:48:53'),(178,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 08:51:04'),(179,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 09:16:44'),(180,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 09:43:50'),(181,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 09:44:10'),(182,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 09:46:08'),(183,3,'store-keeper','Inventory','Received 10 items into central stock','BAT-0001','done','2026-09-07 10:06:01'),(184,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 10:07:38'),(185,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 10:08:00'),(186,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 10:10:41'),(187,3,'store-keeper','Inventory','Received 1 item into central stock','BAT-0002','done','2026-09-07 10:15:32'),(188,3,'store-keeper','Inventory','Received 16 items into central stock','BAT-0003','done','2026-09-07 10:18:32'),(189,3,'store-keeper','Inventory','Received 445 items into central stock','BAT-0004','done','2026-09-07 10:20:58'),(190,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 10:33:13'),(191,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 10:33:33'),(192,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 14:04:35'),(193,3,'store-keeper','Inventory','Received 56 items into central stock','BAT-0005','done','2026-09-07 14:10:45'),(194,3,'store-keeper','Inventory','Received 76 items into central stock','BAT-0006','done','2026-09-07 14:11:34'),(195,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 14:24:58'),(196,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 14:26:12'),(197,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 14:26:59'),(198,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 14:52:06'),(199,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 14:53:15'),(200,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 15:49:20'),(201,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 15:50:38'),(202,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 16:25:53'),(203,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 16:29:04'),(204,2,'subject-officer','Suppliers','Added a product agreement to supplier — suki and j','SUP-53','done','2026-09-07 16:29:29'),(205,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 16:29:44'),(206,3,'store-keeper','Inventory','Received 20 items into central stock','BAT-0007','done','2026-09-07 16:30:44'),(207,3,'store-keeper','Inventory','Received 10 items into central stock','BAT-0008','done','2026-09-07 16:32:23'),(208,3,'store-keeper','Inventory','Received 20 items into central stock','BAT-0009','done','2026-09-07 16:33:35'),(209,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 16:54:20'),(210,2,'subject-officer','Disability Aid Configuration','Configured item eligibility and beneficiary fields','ITEM-93','done','2026-09-07 16:55:13'),(211,2,'subject-officer','Suppliers','Added a product agreement to supplier — suki co','SUP-52','done','2026-09-07 16:55:40'),(212,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 16:55:50'),(213,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 16:57:21'),(214,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-07 16:59:30'),(215,3,'store-keeper','Inventory','Received 5 items into central stock','BAT-0010','done','2026-09-07 17:09:27'),(216,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 03:09:52'),(217,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 04:18:10'),(218,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 04:18:51'),(219,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 04:20:37'),(220,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 04:20:49'),(221,3,'store-keeper','Inventory','Received 10 items into central stock','BAT-0011','done','2026-09-08 06:37:53'),(222,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 06:38:31'),(223,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 06:56:29'),(224,1,'admin','Aid Requests','Approved aid request','AR-0001','approved','2026-09-08 06:56:47'),(225,1,'admin','Aid Requests','Approved aid request','AR-0005','approved','2026-09-08 06:56:49'),(226,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 06:58:19'),(227,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 06:58:40'),(228,1,'admin','Aid Requests','Approved aid request','AR-0011','approved','2026-09-08 07:34:58'),(229,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 07:40:04'),(230,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 07:40:15'),(231,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 07:47:55'),(232,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 07:50:54'),(233,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 07:51:07'),(234,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 07:54:41'),(235,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 08:34:55'),(236,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 08:35:03'),(237,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 08:35:38'),(238,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 09:23:23'),(239,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 09:24:37'),(240,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 09:25:50'),(241,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 09:33:27'),(242,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 09:38:34'),(243,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 09:38:44'),(244,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 09:39:03'),(245,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 10:18:45'),(246,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-08 10:35:30'),(247,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 22:35:04'),(248,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 22:35:04'),(249,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 22:52:12'),(250,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 22:53:22'),(251,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 23:00:50'),(252,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 23:24:01'),(253,3,'store-keeper','Corrections','Submitted an inventory correction request','CR-001','pending','2026-09-10 23:25:01'),(254,3,'store-keeper','Corrections','Submitted an inventory correction request','CR-002','pending','2026-09-10 23:25:59'),(255,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 23:29:29'),(256,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 23:33:27'),(257,3,'store-keeper','Corrections','Submitted an inventory correction request','CR-003','pending','2026-09-10 23:33:58'),(258,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 23:39:24'),(259,1,'admin','Corrections','Approved correction request','CR-003','approved','2026-09-10 23:53:11'),(260,1,'admin','Inventory Corrections','Applied Wrong unit cost from CR-003','BAT-0002','done','2026-09-10 23:53:11'),(261,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 23:53:20'),(262,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-10 23:55:28'),(263,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 00:01:41'),(264,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 03:14:22'),(265,1,'admin','Corrections','Rejected correction request','CR-001','rejected','2026-09-11 03:16:30'),(266,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 03:24:41'),(267,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 03:31:26'),(268,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 04:13:16'),(269,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 04:31:15'),(270,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 04:32:10'),(271,4,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 04:32:32'),(272,4,'social-service-officer','Aid Requests','Submitted aid request','AR-0012','pending','2026-09-11 04:33:57'),(273,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 04:45:10'),(274,3,'store-keeper','Corrections','Submitted an inventory correction request','CR-004','pending','2026-09-11 04:45:39'),(275,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 04:46:01'),(276,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 05:34:19'),(277,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 05:40:44'),(278,1,'admin','Users','Created social-service-officer account','USR-15','created','2026-09-11 06:06:02'),(279,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 06:20:32'),(280,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 09:02:02'),(281,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 09:02:15'),(282,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 09:05:26'),(283,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 09:08:25'),(284,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 09:09:31'),(285,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 09:10:42'),(286,1,'admin','Users','User suspended successfully. Social Service Officer','USR-4','suspended','2026-09-11 09:11:10'),(287,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 09:25:12'),(288,13,'social-service-officer','Aid Requests','Submitted aid request','AR-0013','pending','2026-09-11 09:25:56'),(289,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 09:26:12'),(290,1,'admin','Aid Requests','Approved aid request','AR-0013','approved','2026-09-11 09:26:17'),(291,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 09:26:28'),(292,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 09:54:08'),(293,2,'subject-officer','Aid Requests','Submitted aid request','AR-0014','pending','2026-09-11 10:00:01'),(294,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 10:16:15'),(295,1,'admin','Aid Requests','Approved aid request','AR-0014','approved','2026-09-11 10:16:38'),(296,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 10:16:51'),(297,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 10:27:39'),(298,1,'admin','Corrections','Approved correction request','CR-004','approved','2026-09-11 10:28:26'),(299,1,'admin','Inventory Corrections','Applied Wrong payment amount from CR-004','PAY-0004','done','2026-09-11 10:28:26'),(300,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 10:28:32'),(301,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 17:32:57'),(302,2,'subject-officer','Aid Requests','Submitted aid request','AR-0015','pending','2026-09-11 17:33:57'),(303,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 17:34:06'),(304,1,'admin','Aid Requests','Approved aid request','AR-0015','approved','2026-09-11 17:34:15'),(305,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 17:34:28'),(306,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 17:34:59'),(307,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 17:44:51'),(308,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 17:45:55'),(309,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 17:46:52'),(310,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 17:50:14'),(311,1,'admin','Aid Requests','Created and approved direct aid request','AR-0016','approved','2026-09-11 17:50:55'),(312,1,'admin','Aid Requests','Approved aid request','AR-0012','approved','2026-09-11 17:51:31'),(313,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 17:55:37'),(314,1,'admin','Users','Approved user registration request','REG-004','approved','2026-09-11 17:55:43'),(315,1,'admin','Corrections','Rejected correction request','CR-002','rejected','2026-09-11 17:57:35'),(316,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-11 18:35:39'),(317,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-12 14:24:47'),(318,2,'subject-officer','Goods Requests','Submitted 1 goods allocation line(s) for Admin approval','GRB-20260912-1AA73DFEA0D3D98D','pending','2026-09-12 15:05:47'),(319,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 13:58:08'),(320,2,'subject-officer','Goods Requests','Submitted 2 goods allocation line(s) for Admin approval','GRB-20260915-E85F1A3B0F6D6DBE','pending','2026-09-15 13:59:35'),(321,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 13:59:51'),(322,1,'admin','Stock Quota Requests','Approved stock quota request','GRB-20260915-E85F1A3B0F6D6DBE','approved','2026-09-15 14:43:13'),(323,1,'admin','Stock Quota Requests','Rejected stock quota request','GRB-20260912-1AA73DFEA0D3D98D','rejected','2026-09-15 14:43:36'),(324,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 14:43:47'),(325,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 14:44:23'),(326,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 14:45:13'),(327,3,'store-keeper','Dispatch','Stock quota released to the Subject Officer who submitted the request.','GR-0003','dispatched','2026-09-15 14:45:22'),(328,3,'store-keeper','Dispatch','Stock quota released to the Subject Officer who submitted the request.','GR-0004','dispatched','2026-09-15 14:45:35'),(329,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 14:55:09'),(330,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 14:55:25'),(331,2,'subject-officer','Stock Quota Requests','Assigned dispatched stock quota to the planned SSO pool','GR-0004','assigned','2026-09-15 14:55:56'),(332,2,'subject-officer','Stock Quota Requests','Assigned dispatched stock quota to the planned SSO pool','GR-0003','assigned','2026-09-15 14:56:02'),(333,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 14:56:11'),(334,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 15:27:00'),(335,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 15:40:53'),(336,15,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 16:58:37'),(337,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 17:13:43'),(338,15,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 17:21:10'),(339,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 17:21:52'),(340,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 17:22:11'),(341,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 17:23:19'),(342,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 17:45:54'),(343,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 18:08:39'),(344,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-15 18:41:42'),(345,13,'social-service-officer','Distribution','Issued aid from officer pool','DIST-0001','request-based','2026-09-15 18:42:40'),(346,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 04:48:35'),(347,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:04:14'),(348,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:04:48'),(349,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:06:37'),(350,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:07:31'),(351,3,'store-keeper','Inventory','Received 1 item into central stock','BAT-0012','done','2026-09-16 06:08:15'),(352,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:08:22'),(353,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:12:23'),(354,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:12:35'),(355,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:16:49'),(356,2,'subject-officer','Officer Pools','Assigned Social Service Officer to a division','USR-13','done','2026-09-16 06:37:50'),(357,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:38:02'),(358,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:40:50'),(359,3,'store-keeper','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:46:56'),(360,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 06:47:16'),(361,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 07:56:51'),(362,13,'social-service-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 07:57:20'),(363,1,'admin','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 07:57:59'),(364,2,'subject-officer','Authentication','Signed in to WIDMS',NULL,'done','2026-09-16 07:58:21');
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
  `eligibility_override` tinyint(1) NOT NULL DEFAULT 0,
  `direct_request_document` varchar(255) DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aid_requests`
--

LOCK TABLES `aid_requests` WRITE;
/*!40000 ALTER TABLE `aid_requests` DISABLE KEYS */;
INSERT INTO `aid_requests` VALUES (1,2,NULL,70,1,'vision problem',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,1,1,1,0,'approved',NULL,4,1,'2026-09-08 12:26:47','2026-09-05 15:56:24','2026-09-08 06:56:47'),(5,3,NULL,70,2,'vision problem',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,1,1,0,0,'approved',NULL,4,1,'2026-09-08 12:26:49','2026-09-05 17:41:30','2026-09-08 06:56:49'),(6,4,NULL,70,1,'vision problem',NULL,'Prescription Power','-2','[{\"field_id\":8,\"label\":\"Prescription Power\",\"type\":\"number\",\"value\":\"-2\",\"display_value\":\"-2\"},{\"field_id\":9,\"label\":\"doctor report\",\"type\":\"image\",\"value\":\"uploads/aid-documents/aid-d8721f6590b13b06f23f32d7.jpg\",\"display_value\":\"WhatsApp Image 2026-09-04 at 22.31.12.jpeg\"},{\"field_id\":10,\"label\":\"doctor\",\"type\":\"pdf\",\"value\":\"uploads/aid-documents/aid-8ab9b247bfa815f9ed733cf1.pdf\",\"display_value\":\"SC12901.pdf\"}]',NULL,NULL,0,NULL,1,1,1,0,'approved',NULL,4,1,'2026-09-07 14:13:45','2026-09-06 06:32:44','2026-09-07 08:43:45'),(7,5,NULL,70,1,'Vision Impairment',NULL,'Power','+8.60','[{\"field_id\":8,\"label\":\"Power\",\"type\":\"number\",\"value\":\"+8.60\",\"display_value\":\"+8.60\"}]',NULL,NULL,0,NULL,1,1,1,1,'approved',NULL,4,1,'2026-09-07 14:10:25','2026-09-07 07:19:57','2026-09-07 08:40:25'),(8,6,NULL,91,1,'Vision Impairment',NULL,'Power','+6.00','[{\"field_id\":2,\"label\":\"Power\",\"type\":\"number\",\"value\":\"+6.00\",\"display_value\":\"+6.00\"}]',NULL,NULL,0,NULL,1,1,1,1,'approved',NULL,4,1,'2026-09-07 14:07:48','2026-09-07 07:24:47','2026-09-07 08:37:48'),(9,7,NULL,91,1,'Vision Impairment',NULL,'Power','+4.00','[{\"field_id\":2,\"label\":\"Power\",\"type\":\"number\",\"value\":\"+4.00\",\"display_value\":\"+4.00\"}]',NULL,NULL,0,NULL,1,1,1,1,'approved',NULL,4,1,'2026-09-07 13:45:57','2026-09-07 08:09:29','2026-09-07 08:15:57'),(10,8,NULL,91,1,'Vision Impairment',NULL,'Power','+7.00','[{\"field_id\":2,\"label\":\"Power\",\"type\":\"number\",\"value\":\"+7.00\",\"display_value\":\"+7.00\"}]',NULL,NULL,0,NULL,1,0,0,0,'rejected','there is no approval by 4 officilas',4,1,'2026-09-07 13:59:02','2026-09-07 08:11:30','2026-09-07 08:29:02'),(11,9,NULL,92,1,'mobility impairment',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,1,1,1,0,'approved',NULL,4,1,'2026-09-08 13:04:58','2026-09-07 08:47:56','2026-09-08 07:34:58'),(12,10,NULL,92,1,'mobility impairment',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,1,0,0,0,'approved',NULL,4,1,'2026-09-11 23:21:31','2026-09-11 04:33:57','2026-09-11 17:51:31'),(13,11,NULL,92,1,'mobility impairment',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,1,0,0,0,'distributed',NULL,13,1,'2026-09-11 14:56:17','2026-09-11 09:25:56','2026-09-15 18:42:40'),(14,12,NULL,91,1,'Vision Impairment',NULL,'Power','+5.00','[{\"field_id\":2,\"label\":\"Power\",\"type\":\"number\",\"value\":\"+5.00\",\"display_value\":\"+5.00\"}]',NULL,'he doent have nic',1,'uploads/aid-documents/direct-7fd44006846cc1534d9d397b.pdf',1,0,0,0,'approved',NULL,2,1,'2026-09-11 15:46:37','2026-09-11 10:00:01','2026-09-11 10:16:37'),(15,13,NULL,92,1,'mobility impairment',NULL,NULL,NULL,NULL,NULL,'doesnt have a 4 offical approval',1,NULL,1,0,0,0,'approved',NULL,2,1,'2026-09-11 23:04:14','2026-09-11 17:33:57','2026-09-11 17:34:14'),(16,14,NULL,92,1,'mobility impairment',NULL,NULL,NULL,NULL,NULL,'he phycally challanged',1,NULL,1,0,0,0,'approved',NULL,1,1,'2026-09-11 23:20:55','2026-09-11 17:50:55','2026-09-11 17:50:55');
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
  `date_of_birth` date DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `beneficiaries`
--

LOCK TABLES `beneficiaries` WRITE;
/*!40000 ALTER TABLE `beneficiaries` DISABLE KEYS */;
INSERT INTO `beneficiaries` VALUES (2,NULL,1,2,78,'Sunitha Sivakumaran','200183804294',NULL,'2013-02-05','female','0763121801','no 116\r\nMusic college road,','Vision Impairment','active',4,'2026-09-05 21:26:24','2026-09-05 15:56:24','2026-09-07 08:42:59'),(3,NULL,1,11,431,'Sunitha Sivakumaran','746712395V',NULL,'2018-01-30','female','0763121801','no 116\r\nMusic college road,','Vision Impairment','active',4,'2026-09-05 21:33:39','2026-09-05 16:03:39','2026-09-07 08:42:59'),(4,NULL,2,32,1247,'Sunitha Sivakumaran','200183804295','6473524289','2012-03-06','female','0763121801','no 116\r\nMusic college road,','Vision Impairment','active',4,'2026-09-06 12:02:44','2026-09-06 06:32:44','2026-09-07 08:42:59'),(5,NULL,1,52,NULL,'Sunitha Sivakumaran',NULL,'647352428','2009-06-09','female','0763121801','no 116\r\nMusic college road,','Vision Impairment','active',4,'2026-09-07 12:49:57','2026-09-07 07:19:57','2026-09-07 07:19:57'),(6,NULL,2,32,1245,'Sunitha Sivakumaran','726712395V',NULL,'2005-03-16','female','0763121801','no 116\r\nMusic college road,','Vision Impairment','active',4,'2026-09-07 12:54:46','2026-09-07 07:24:46','2026-09-07 07:24:46'),(7,NULL,3,44,1744,'Sunitha Sivakumaran','200183804298',NULL,'2005-06-08','male','0763121801','no 116\r\nMusic college road,','Vision Impairment','active',4,'2026-09-07 13:39:28','2026-09-07 08:09:28','2026-09-07 08:09:28'),(8,NULL,3,44,1775,'Sunitha Sivakumaran','200183804296',NULL,'2025-07-17','female','0763121801','no 116\r\nMusic college road,','Vision Impairment','active',4,'2026-09-07 13:41:29','2026-09-07 08:11:29','2026-09-07 08:11:29'),(9,NULL,2,38,1536,'Sunitha Sivakumaran','200183804297',NULL,'2010-02-24','female','0763121801','no 116\r\nMusic college road,','mobility impairment','active',4,'2026-09-07 14:17:56','2026-09-07 08:47:56','2026-09-07 08:47:56'),(10,NULL,3,44,1760,'Sunitha Sivakumaran','200183804200',NULL,'2016-03-02','male','0763121801','no 116\r\nMusic college road,','mobility impairment','active',4,'2026-09-11 10:03:57','2026-09-11 04:33:57','2026-09-11 04:33:57'),(11,NULL,3,43,1687,'Sunitha Sivakumaran','200183800000',NULL,'2008-06-11','female','0763121801','no 116\r\nMusic college road,','mobility impairment','active',13,'2026-09-11 14:55:56','2026-09-11 09:25:56','2026-09-11 09:25:56'),(12,NULL,1,1,11,'Sunitha Sivakumaran',NULL,NULL,NULL,'female','0763121801','no 116\r\nMusic college road,','Vision Impairment','active',2,'2026-09-11 15:30:01','2026-09-11 10:00:01','2026-09-11 10:00:01'),(13,NULL,1,1,11,'Sunitha Sivakumaran',NULL,NULL,NULL,'male','0763121801','no 116\r\nMusic college road,','mobility impairment','active',2,'2026-09-11 23:03:57','2026-09-11 17:33:57','2026-09-11 17:33:57'),(14,NULL,1,1,12,'Sunitha Sivakumaran',NULL,NULL,NULL,'female','0763121801','no 116\r\nMusic college road,','mobility impairment','active',1,'2026-09-11 23:20:54','2026-09-11 17:50:54','2026-09-11 17:50:54');
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `correction_requests`
--

LOCK TABLES `correction_requests` WRITE;
/*!40000 ALTER TABLE `correction_requests` DISABLE KEYS */;
INSERT INTO `correction_requests` VALUES (1,NULL,'BAT-0001','wrong-bill-number','fyudfyifg','ml;jhug','i dont know','rejected','i dont need',3,1,'2026-09-11 08:46:30','2026-09-10 23:25:01','2026-09-11 03:16:30'),(2,NULL,'PAY-0004','wrong-payment-amount','12563.00','567','i didnt see clearly','rejected','this record changed prevuiouslyalso',3,1,'2026-09-11 23:27:35','2026-09-10 23:25:59','2026-09-11 17:57:35'),(3,NULL,'BAT-0002','wrong-unit-cost','45.00','47','i didnt see','approved',NULL,3,1,'2026-09-11 05:23:11','2026-09-10 23:33:57','2026-09-10 23:53:11'),(4,NULL,'PAY-0004','wrong-payment-amount','12563.00','5647','needed','approved',NULL,3,1,'2026-09-11 15:58:26','2026-09-11 04:45:39','2026-09-11 10:28:26');
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
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disability_aid_item_fields`
--

LOCK TABLES `disability_aid_item_fields` WRITE;
/*!40000 ALTER TABLE `disability_aid_item_fields` DISABLE KEYS */;
INSERT INTO `disability_aid_item_fields` VALUES (2,7,'Power','number',1,1,'2026-09-06 06:29:25'),(8,2,'Power','number',1,1,'2026-09-06 06:46:44');
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
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `beneficiary_field_label` varchar(100) DEFAULT NULL,
  `beneficiary_field_type` enum('text','number') NOT NULL DEFAULT 'text',
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disability_aid_items`
--

LOCK TABLES `disability_aid_items` WRITE;
/*!40000 ALTER TABLE `disability_aid_items` DISABLE KEYS */;
INSERT INTO `disability_aid_items` VALUES (2,39,70,7,1,'Power','number','active',2,'2026-09-05 08:52:32','2026-09-07 06:36:04'),(7,39,91,9,1,'Power','number','active',2,'2026-09-05 20:37:36','2026-09-07 06:36:04'),(14,44,92,0,0,NULL,'text','active',4,'2026-09-11 09:53:47','2026-09-11 09:53:47');
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
INSERT INTO `disability_item_prohibitions` VALUES (2,91),(7,70);
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
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disability_types`
--

LOCK TABLES `disability_types` WRITE;
/*!40000 ALTER TABLE `disability_types` DISABLE KEYS */;
INSERT INTO `disability_types` VALUES (39,'Vision Impairment','active',1,2,'2026-09-05 08:52:16','2026-09-07 06:36:04'),(41,'hearing problem','active',0,2,'2026-09-05 09:34:00','2026-09-05 09:34:00'),(44,'mobility impairment','active',0,2,'2026-09-07 08:45:34','2026-09-07 08:45:34');
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distributions`
--

LOCK TABLES `distributions` WRITE;
/*!40000 ALTER TABLE `distributions` DISABLE KEYS */;
INSERT INTO `distributions` VALUES (1,13,11,92,1,'request-based','officer-pool',NULL,13,'2026-09-16 00:12:40');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `districts`
--

LOCK TABLES `districts` WRITE;
/*!40000 ALTER TABLE `districts` DISABLE KEYS */;
INSERT INTO `districts` VALUES (1,'Galle','active',1,'2026-09-03 05:45:18'),(2,'Matara','active',1,'2026-09-03 05:45:18'),(3,'Hambantota','active',1,'2026-09-03 05:45:18');
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
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ds_divisions`
--

LOCK TABLES `ds_divisions` WRITE;
/*!40000 ALTER TABLE `ds_divisions` DISABLE KEYS */;
INSERT INTO `ds_divisions` VALUES (1,1,'Bentota','official','active',1,'2026-09-03 05:45:19'),(2,1,'Balapitiya','official','active',1,'2026-09-03 05:45:19'),(3,1,'Karandeniya','official','active',1,'2026-09-03 05:45:19'),(4,1,'Elpitiya','official','active',1,'2026-09-03 05:45:19'),(5,1,'Niyagama','official','active',1,'2026-09-03 05:45:19'),(6,1,'Thawalama','official','active',1,'2026-09-03 05:45:19'),(7,1,'Neluwa','official','active',1,'2026-09-03 05:45:19'),(8,1,'Nagoda','official','active',1,'2026-09-03 05:45:19'),(9,1,'Baddegama','official','active',1,'2026-09-03 05:45:19'),(10,1,'Welivitiya-Divitura','official','active',1,'2026-09-03 05:45:19'),(11,1,'Ambalangoda','official','active',1,'2026-09-03 05:45:19'),(12,1,'Gonapinuwala','official','active',1,'2026-09-03 05:45:19'),(13,1,'Hikkaduwa','official','active',1,'2026-09-03 05:45:19'),(14,1,'Galle 4 Gravets','official','active',1,'2026-09-03 05:45:19'),(15,1,'Bope-Poddala','official','active',1,'2026-09-03 05:45:19'),(16,1,'Akmeemana','official','active',1,'2026-09-03 05:45:19'),(17,1,'Yakkalamulla','official','active',1,'2026-09-03 05:45:19'),(18,1,'Imaduwa','official','active',1,'2026-09-03 05:45:19'),(19,1,'Habaraduwa','official','active',1,'2026-09-03 05:45:19'),(20,1,'Wanduramba','official','active',1,'2026-09-03 05:45:19'),(21,1,'Madampagama','official','active',1,'2026-09-03 05:45:19'),(22,1,'Rathgama','official','active',1,'2026-09-03 05:45:19'),(23,2,'Pitabaddara','official','active',1,'2026-09-03 05:45:19'),(24,2,'Kotapola','official','active',1,'2026-09-03 05:45:19'),(25,2,'Pasgoda','official','active',1,'2026-09-03 05:45:19'),(26,2,'Mulatiyana','official','active',1,'2026-09-03 05:45:19'),(27,2,'Athuraliya','official','active',1,'2026-09-03 05:45:19'),(28,2,'Akuressa','official','active',1,'2026-09-03 05:45:19'),(29,2,'Welipitiya','official','active',1,'2026-09-03 05:45:19'),(30,2,'Malimbada','official','active',1,'2026-09-03 05:45:19'),(31,2,'Kaburupitiya','official','active',1,'2026-09-03 05:45:19'),(32,2,'Hakmana','official','active',1,'2026-09-03 05:45:19'),(33,2,'Kirinda Puhulwella','official','active',1,'2026-09-03 05:45:19'),(34,2,'Thihagoda','official','active',1,'2026-09-03 05:45:19'),(35,2,'Weligama','official','active',1,'2026-09-03 05:45:19'),(36,2,'Matara Four Gravets','official','active',1,'2026-09-03 05:45:19'),(37,2,'Devinuwara','official','active',1,'2026-09-03 05:45:19'),(38,2,'Dikwella','official','active',1,'2026-09-03 05:45:19'),(39,3,'Sooriyawewa','official','active',1,'2026-09-03 05:45:19'),(40,3,'Lunugamwehera','official','active',1,'2026-09-03 05:45:19'),(41,3,'Thissamaharama','official','active',1,'2026-09-03 05:45:19'),(42,3,'Hambantota','official','active',1,'2026-09-03 05:45:19'),(43,3,'Ambalantota','official','active',1,'2026-09-03 05:45:19'),(44,3,'Angunakolapelessa','official','active',1,'2026-09-03 05:45:19'),(45,3,'Weeraketiya','official','active',1,'2026-09-03 05:45:19'),(46,3,'Katuwana','official','active',1,'2026-09-03 05:45:19'),(47,3,'Walasmulla','official','active',1,'2026-09-03 05:45:19'),(48,3,'Okewela','official','active',1,'2026-09-03 05:45:19'),(49,3,'Beliatta','official','active',1,'2026-09-03 05:45:19'),(50,3,'Tangalle','official','active',1,'2026-09-03 05:45:19'),(51,1,'Suraliya Sewana Center','service-centre','active',1,'2026-09-05 07:42:03'),(52,1,'Senda Arana Elder Home','service-centre','active',1,'2026-09-05 07:42:03');
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=2122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gn_divisions`
--

LOCK TABLES `gn_divisions` WRITE;
/*!40000 ALTER TABLE `gn_divisions` DISABLE KEYS */;
INSERT INTO `gn_divisions` VALUES (1,1,'Pahurumulla','active',1,'2026-09-03 05:45:19'),(2,1,'Sinharoopagama','active',1,'2026-09-03 05:45:19'),(3,1,'Yathramulla','active',1,'2026-09-03 05:45:19'),(4,1,'Kommala','active',1,'2026-09-03 05:45:19'),(5,1,'Bodhimaluwa','active',1,'2026-09-03 05:45:19'),(6,1,'Dope','active',1,'2026-09-03 05:45:19'),(7,1,'Angagoda','active',1,'2026-09-03 05:45:19'),(8,1,'Warahena','active',1,'2026-09-03 05:45:19'),(9,1,'Kahagalla','active',1,'2026-09-03 05:45:19'),(10,1,'Hunganthota Wadumulla','active',1,'2026-09-03 05:45:19'),(11,1,'Dedduwa','active',1,'2026-09-03 05:45:19'),(12,1,'Athuruwella','active',1,'2026-09-03 05:45:19'),(13,1,'Galbada','active',1,'2026-09-03 05:45:19'),(14,1,'Galagama','active',1,'2026-09-03 05:45:19'),(15,1,'Mullegoda','active',1,'2026-09-03 05:45:19'),(16,1,'Sooriyagama','active',1,'2026-09-03 05:45:19'),(17,1,'Haburugala','active',1,'2026-09-03 05:45:19'),(18,1,'Thunduwa West','active',1,'2026-09-03 05:45:19'),(19,1,'Thunduwa East','active',1,'2026-09-03 05:45:19'),(20,1,'Thotakanatta','active',1,'2026-09-03 05:45:19'),(21,1,'Elakaka','active',1,'2026-09-03 05:45:19'),(22,1,'Ethungagoda','active',1,'2026-09-03 05:45:19'),(23,1,'Gonagalapura','active',1,'2026-09-03 05:45:19'),(24,1,'Olaganduwa','active',1,'2026-09-03 05:45:19'),(25,1,'Yalegama','active',1,'2026-09-03 05:45:19'),(26,1,'Kaikawala','active',1,'2026-09-03 05:45:19'),(27,1,'Habakkala','active',1,'2026-09-03 05:45:19'),(28,1,'Etawalawatta West','active',1,'2026-09-03 05:45:19'),(29,1,'Etawalawatta East','active',1,'2026-09-03 05:45:19'),(30,1,'Dombagahawatta','active',1,'2026-09-03 05:45:19'),(31,1,'Akadegoda','active',1,'2026-09-03 05:45:19'),(32,1,'Kahawegammedda','active',1,'2026-09-03 05:45:19'),(33,1,'Warakamulla','active',1,'2026-09-03 05:45:19'),(34,1,'Kandemulla','active',1,'2026-09-03 05:45:19'),(35,1,'Galthuduwa','active',1,'2026-09-03 05:45:19'),(36,1,'Kolaniya','active',1,'2026-09-03 05:45:19'),(37,1,'Miriswatta','active',1,'2026-09-03 05:45:19'),(38,1,'Viyandoowa','active',1,'2026-09-03 05:45:19'),(39,1,'Mahagoda','active',1,'2026-09-03 05:45:19'),(40,1,'Pilekumbura','active',1,'2026-09-03 05:45:19'),(41,1,'Mahavila West','active',1,'2026-09-03 05:45:19'),(42,1,'Mahavila East','active',1,'2026-09-03 05:45:19'),(43,1,'Moragoda','active',1,'2026-09-03 05:45:19'),(44,1,'Ranthotuvila','active',1,'2026-09-03 05:45:19'),(45,1,'Malawala','active',1,'2026-09-03 05:45:19'),(46,1,'Ihala Malawala','active',1,'2026-09-03 05:45:19'),(47,1,'Kotuwabendahena','active',1,'2026-09-03 05:45:19'),(48,1,'Kuda Uragaha','active',1,'2026-09-03 05:45:19'),(49,1,'Maha Uragaha','active',1,'2026-09-03 05:45:19'),(50,1,'Delkabalagoda','active',1,'2026-09-03 05:45:19'),(51,1,'Hipanwatta','active',1,'2026-09-03 05:45:19'),(52,2,'Doowemodara','active',1,'2026-09-03 05:45:19'),(53,2,'Nanathota Palatha','active',1,'2026-09-03 05:45:19'),(54,2,'Pelegas Palatha','active',1,'2026-09-03 05:45:19'),(55,2,'Wathurawela','active',1,'2026-09-03 05:45:19'),(56,2,'Boraluketiya','active',1,'2026-09-03 05:45:19'),(57,2,'Pathiraja Pedesa','active',1,'2026-09-03 05:45:19'),(58,2,'Polathu Palatha','active',1,'2026-09-03 05:45:19'),(59,2,'Katuvila','active',1,'2026-09-03 05:45:19'),(60,2,'Mahapitiya','active',1,'2026-09-03 05:45:19'),(61,2,'Nape','active',1,'2026-09-03 05:45:19'),(62,2,'Kudagodagama','active',1,'2026-09-03 05:45:19'),(63,2,'Hegalla-Piyagama','active',1,'2026-09-03 05:45:19'),(64,2,'Godapitiya','active',1,'2026-09-03 05:45:19'),(65,2,'Kosgoda','active',1,'2026-09-03 05:45:19'),(66,2,'Ahungalla','active',1,'2026-09-03 05:45:19'),(67,2,'Middaramulla','active',1,'2026-09-03 05:45:19'),(68,2,'Bogahapitiya','active',1,'2026-09-03 05:45:19'),(69,2,'Galvehera','active',1,'2026-09-03 05:45:19'),(70,2,'Pathirajagama','active',1,'2026-09-03 05:45:19'),(71,2,'Madoowa','active',1,'2026-09-03 05:45:19'),(72,2,'Kadiragonna','active',1,'2026-09-03 05:45:19'),(73,2,'Makumbura','active',1,'2026-09-03 05:45:19'),(74,2,'Wathuregama','active',1,'2026-09-03 05:45:19'),(75,2,'Wellabada','active',1,'2026-09-03 05:45:19'),(76,2,'Pathegangoda','active',1,'2026-09-03 05:45:19'),(77,2,'Weliwathugoda','active',1,'2026-09-03 05:45:19'),(78,2,'Brahmanawatta North','active',1,'2026-09-03 05:45:19'),(79,2,'Brahmanawatta South','active',1,'2026-09-03 05:45:19'),(80,2,'Galmangoda','active',1,'2026-09-03 05:45:19'),(81,2,'Wandadoowa','active',1,'2026-09-03 05:45:19'),(82,2,'Heenatiya North','active',1,'2026-09-03 05:45:19'),(83,2,'Mahaladoowa','active',1,'2026-09-03 05:45:19'),(84,2,'Seenigoda','active',1,'2026-09-03 05:45:19'),(85,2,'Heenatiya South','active',1,'2026-09-03 05:45:19'),(86,2,'Mahakarawa','active',1,'2026-09-03 05:45:19'),(87,2,'Elathota','active',1,'2026-09-03 05:45:19'),(88,2,'Balapitiya','active',1,'2026-09-03 05:45:19'),(89,2,'Berathuduwa','active',1,'2026-09-03 05:45:19'),(90,2,'Andadola','active',1,'2026-09-03 05:45:19'),(91,2,'Petiwatta','active',1,'2026-09-03 05:45:19'),(92,2,'Wathugedara','active',1,'2026-09-03 05:45:19'),(93,2,'Paragahathota','active',1,'2026-09-03 05:45:19'),(94,2,'Wathugedara South','active',1,'2026-09-03 05:45:19'),(95,2,'Kurunduwatta','active',1,'2026-09-03 05:45:19'),(96,2,'Walagedara','active',1,'2026-09-03 05:45:19'),(97,2,'Randombe North','active',1,'2026-09-03 05:45:19'),(98,2,'Wadumulla','active',1,'2026-09-03 05:45:19'),(99,2,'Viharagoda','active',1,'2026-09-03 05:45:19'),(100,2,'Kandegoda','active',1,'2026-09-03 05:45:19'),(101,2,'Bogahawatta','active',1,'2026-09-03 05:45:19'),(102,2,'Randombe South','active',1,'2026-09-03 05:45:19'),(103,3,'Walinguruketiya','active',1,'2026-09-03 05:45:19'),(104,3,'Uragasmanhandiya North','active',1,'2026-09-03 05:45:19'),(105,3,'Galpottawala','active',1,'2026-09-03 05:45:19'),(106,3,'Meegaspitiya','active',1,'2026-09-03 05:45:19'),(107,3,'Mendorawala','active',1,'2026-09-03 05:45:19'),(108,3,'Uragasmanhandiya South','active',1,'2026-09-03 05:45:19'),(109,3,'Yatagala','active',1,'2026-09-03 05:45:19'),(110,3,'Uragasmanhandiya East','active',1,'2026-09-03 05:45:19'),(111,3,'Mabingoda','active',1,'2026-09-03 05:45:19'),(112,3,'Hipankanda','active',1,'2026-09-03 05:45:19'),(113,3,'Siripura','active',1,'2026-09-03 05:45:19'),(114,3,'Beligaswella','active',1,'2026-09-03 05:45:19'),(115,3,'Halgahawella','active',1,'2026-09-03 05:45:19'),(116,3,'Lenagal Palatha','active',1,'2026-09-03 05:45:19'),(117,3,'Magala North','active',1,'2026-09-03 05:45:19'),(118,3,'Kaluwalagoda','active',1,'2026-09-03 05:45:19'),(119,3,'Diyapitagallana','active',1,'2026-09-03 05:45:19'),(120,3,'Angulugalla','active',1,'2026-09-03 05:45:19'),(121,3,'Magala South','active',1,'2026-09-03 05:45:19'),(122,3,'Pehembiyakanda','active',1,'2026-09-03 05:45:19'),(123,3,'Unagaswela','active',1,'2026-09-03 05:45:19'),(124,3,'Madakumbura','active',1,'2026-09-03 05:45:19'),(125,3,'Thalgahawatta','active',1,'2026-09-03 05:45:19'),(126,3,'Mandakanda','active',1,'2026-09-03 05:45:19'),(127,3,'Anganaketiya','active',1,'2026-09-03 05:45:19'),(128,3,'Borakanda','active',1,'2026-09-03 05:45:19'),(129,3,'Mahaedanda','active',1,'2026-09-03 05:45:19'),(130,3,'Karandeniya North','active',1,'2026-09-03 05:45:19'),(131,3,'Randenigama','active',1,'2026-09-03 05:45:19'),(132,3,'Mahagoda','active',1,'2026-09-03 05:45:19'),(133,3,'Karandeniya South','active',1,'2026-09-03 05:45:19'),(134,3,'Dangahavila','active',1,'2026-09-03 05:45:19'),(135,3,'Egodawela','active',1,'2026-09-03 05:45:19'),(136,3,'Diviyagahawela','active',1,'2026-09-03 05:45:19'),(137,3,'Kirinuge','active',1,'2026-09-03 05:45:19'),(138,3,'Kiripedda','active',1,'2026-09-03 05:45:19'),(139,3,'Galagoda Atta','active',1,'2026-09-03 05:45:19'),(140,3,'Ihala Kiripedda','active',1,'2026-09-03 05:45:19'),(141,3,'Kurundugaha Hethekma','active',1,'2026-09-03 05:45:19'),(142,3,'Jayabima','active',1,'2026-09-03 05:45:19'),(143,4,'Avittawa','active',1,'2026-09-03 05:45:19'),(144,4,'Pahala Omatta','active',1,'2026-09-03 05:45:19'),(145,4,'Omatta','active',1,'2026-09-03 05:45:19'),(146,4,'Metiviliya','active',1,'2026-09-03 05:45:19'),(147,4,'Himbutugoda','active',1,'2026-09-03 05:45:19'),(148,4,'Indipalegoda','active',1,'2026-09-03 05:45:19'),(149,4,'Dikhena','active',1,'2026-09-03 05:45:19'),(150,4,'Delpona','active',1,'2026-09-03 05:45:19'),(151,4,'Ihala Omatta','active',1,'2026-09-03 05:45:19'),(152,4,'Digala Nagahathenna','active',1,'2026-09-03 05:45:19'),(153,4,'Opatha','active',1,'2026-09-03 05:45:19'),(154,4,'Goluwamulla North','active',1,'2026-09-03 05:45:19'),(155,4,'Goluwamulla West','active',1,'2026-09-03 05:45:19'),(156,4,'Goluwamulla','active',1,'2026-09-03 05:45:19'),(157,4,'Atakohota','active',1,'2026-09-03 05:45:19'),(158,4,'Elpitiya North','active',1,'2026-09-03 05:45:19'),(159,4,'Elpitiya East','active',1,'2026-09-03 05:45:19'),(160,4,'Poojagallena','active',1,'2026-09-03 05:45:19'),(161,4,'Ketandola Udovita','active',1,'2026-09-03 05:45:19'),(162,4,'Sittaragoda','active',1,'2026-09-03 05:45:19'),(163,4,'Amugoda','active',1,'2026-09-03 05:45:19'),(164,4,'Kellapatha','active',1,'2026-09-03 05:45:19'),(165,4,'Thalagaspe','active',1,'2026-09-03 05:45:19'),(166,4,'Thalagaspe West','active',1,'2026-09-03 05:45:19'),(167,4,'Wallambagala North','active',1,'2026-09-03 05:45:19'),(168,4,'Elpitiya South','active',1,'2026-09-03 05:45:19'),(169,4,'Elpitiya Central','active',1,'2026-09-03 05:45:19'),(170,4,'Nawadagala','active',1,'2026-09-03 05:45:19'),(171,4,'Batuwanhena','active',1,'2026-09-03 05:45:19'),(172,4,'Igala','active',1,'2026-09-03 05:45:19'),(173,4,'Igala East','active',1,'2026-09-03 05:45:19'),(174,4,'Pelendagoda','active',1,'2026-09-03 05:45:19'),(175,4,'Wallambagala','active',1,'2026-09-03 05:45:19'),(176,4,'Ambana','active',1,'2026-09-03 05:45:19'),(177,4,'Ambana North','active',1,'2026-09-03 05:45:19'),(178,4,'Pituwala South','active',1,'2026-09-03 05:45:19'),(179,4,'Pituwala North','active',1,'2026-09-03 05:45:19'),(180,4,'Kudagala Kadirandola','active',1,'2026-09-03 05:45:19'),(181,4,'Igala Thalawa East','active',1,'2026-09-03 05:45:19'),(182,4,'Igala Thalawa','active',1,'2026-09-03 05:45:19'),(183,4,'Ella','active',1,'2026-09-03 05:45:19'),(184,4,'Ella Thanabaddegama','active',1,'2026-09-03 05:45:19'),(185,4,'Mahawela Abhayapura','active',1,'2026-09-03 05:45:19'),(186,4,'Pituwala West','active',1,'2026-09-03 05:45:19'),(187,4,'Eramulla','active',1,'2026-09-03 05:45:19'),(188,4,'Pinikahana','active',1,'2026-09-03 05:45:19'),(189,4,'Kahadoowa','active',1,'2026-09-03 05:45:19'),(190,4,'Thibbotuwawa','active',1,'2026-09-03 05:45:19'),(191,4,'Wathuruvila','active',1,'2026-09-03 05:45:19'),(192,4,'Rekadahena','active',1,'2026-09-03 05:45:19'),(193,4,'Kahadoowa South','active',1,'2026-09-03 05:45:19'),(194,5,'Pitigala North','active',1,'2026-09-03 05:45:19'),(195,5,'Uhanovita','active',1,'2026-09-03 05:45:19'),(196,5,'Hattaka','active',1,'2026-09-03 05:45:19'),(197,5,'Kaluarachchigoda','active',1,'2026-09-03 05:45:19'),(198,5,'Pitigala','active',1,'2026-09-03 05:45:19'),(199,5,'Karawwa','active',1,'2026-09-03 05:45:19'),(200,5,'Boraluwahena','active',1,'2026-09-03 05:45:19'),(201,5,'Bangamukanda','active',1,'2026-09-03 05:45:19'),(202,5,'Godamuna North','active',1,'2026-09-03 05:45:19'),(203,5,'Marthupitiya','active',1,'2026-09-03 05:45:19'),(204,5,'Godamuna South','active',1,'2026-09-03 05:45:19'),(205,5,'Liyanagamakanda','active',1,'2026-09-03 05:45:19'),(206,5,'Usbim Colony','active',1,'2026-09-03 05:45:19'),(207,5,'Bambarawana','active',1,'2026-09-03 05:45:19'),(208,5,'Mattaka','active',1,'2026-09-03 05:45:19'),(209,5,'Weihena','active',1,'2026-09-03 05:45:19'),(210,5,'Amaragama','active',1,'2026-09-03 05:45:19'),(211,5,'Naranovita','active',1,'2026-09-03 05:45:19'),(212,5,'Porawagama','active',1,'2026-09-03 05:45:19'),(213,5,'Kimbulawala','active',1,'2026-09-03 05:45:19'),(214,5,'Poddiwala West','active',1,'2026-09-03 05:45:19'),(215,5,'Poddiwala East','active',1,'2026-09-03 05:45:19'),(216,5,'Maraggoda','active',1,'2026-09-03 05:45:19'),(217,5,'Duwegoda','active',1,'2026-09-03 05:45:19'),(218,5,'Porawagama South','active',1,'2026-09-03 05:45:19'),(219,5,'Wattahena','active',1,'2026-09-03 05:45:19'),(220,5,'Manampita','active',1,'2026-09-03 05:45:19'),(221,5,'Niyagama','active',1,'2026-09-03 05:45:19'),(222,5,'Niyagama West','active',1,'2026-09-03 05:45:19'),(223,5,'Polpelaketiya','active',1,'2026-09-03 05:45:19'),(224,5,'Horangalla West','active',1,'2026-09-03 05:45:19'),(225,5,'Horangalla Thalawa','active',1,'2026-09-03 05:45:19'),(226,5,'Horangalla (Akulavila)','active',1,'2026-09-03 05:45:19'),(227,5,'Niyagama South','active',1,'2026-09-03 05:45:19'),(228,6,'Ela Ihala','active',1,'2026-09-03 05:45:19'),(229,6,'Ela Ihala North','active',1,'2026-09-03 05:45:19'),(230,6,'Kumburegoda','active',1,'2026-09-03 05:45:19'),(231,6,'Habarakada East','active',1,'2026-09-03 05:45:19'),(232,6,'Habarakada West','active',1,'2026-09-03 05:45:19'),(233,6,'Kudugalpala','active',1,'2026-09-03 05:45:19'),(234,6,'Batahena','active',1,'2026-09-03 05:45:19'),(235,6,'Thawalama North','active',1,'2026-09-03 05:45:19'),(236,6,'Thawalama Mookalana','active',1,'2026-09-03 05:45:19'),(237,6,'Thawalama South','active',1,'2026-09-03 05:45:19'),(238,6,'Hiniduma North','active',1,'2026-09-03 05:45:19'),(239,6,'Hiniduma West','active',1,'2026-09-03 05:45:19'),(240,6,'Hiniduma South','active',1,'2026-09-03 05:45:19'),(241,6,'Malgalla','active',1,'2026-09-03 05:45:19'),(242,6,'Halvitigala Colony 1','active',1,'2026-09-03 05:45:19'),(243,6,'Halvitigala Colony 2','active',1,'2026-09-03 05:45:19'),(244,6,'Dammala Colony','active',1,'2026-09-03 05:45:19'),(245,6,'Thalangalla East','active',1,'2026-09-03 05:45:19'),(246,6,'Thalangalla West','active',1,'2026-09-03 05:45:19'),(247,6,'Malhathawa','active',1,'2026-09-03 05:45:19'),(248,6,'Panangala West','active',1,'2026-09-03 05:45:19'),(249,6,'Panangala North','active',1,'2026-09-03 05:45:19'),(250,6,'Panangala East','active',1,'2026-09-03 05:45:19'),(251,6,'Thalangalla','active',1,'2026-09-03 05:45:19'),(252,6,'Dammala','active',1,'2026-09-03 05:45:19'),(253,6,'Opatha North','active',1,'2026-09-03 05:45:19'),(254,6,'Opatha West','active',1,'2026-09-03 05:45:19'),(255,6,'Koralegama','active',1,'2026-09-03 05:45:19'),(256,6,'Eppala','active',1,'2026-09-03 05:45:19'),(257,6,'Gallandala','active',1,'2026-09-03 05:45:19'),(258,6,'Opatha South','active',1,'2026-09-03 05:45:19'),(259,6,'Opatha East','active',1,'2026-09-03 05:45:19'),(260,6,'Weerapana North','active',1,'2026-09-03 05:45:19'),(261,6,'Weerapana West','active',1,'2026-09-03 05:45:19'),(262,6,'Weerapana South','active',1,'2026-09-03 05:45:19'),(263,6,'Weerapana East','active',1,'2026-09-03 05:45:19'),(264,7,'Danawala','active',1,'2026-09-03 05:45:19'),(265,7,'Mavita West','active',1,'2026-09-03 05:45:19'),(266,7,'Batuwangala West','active',1,'2026-09-03 05:45:19'),(267,7,'Kosmulla','active',1,'2026-09-03 05:45:19'),(268,7,'Thambalagama','active',1,'2026-09-03 05:45:19'),(269,7,'Ehelapitiya','active',1,'2026-09-03 05:45:19'),(270,7,'Batuwangala','active',1,'2026-09-03 05:45:19'),(271,7,'Mavita East','active',1,'2026-09-03 05:45:19'),(272,7,'Koswatta','active',1,'2026-09-03 05:45:19'),(273,7,'Ebalagedara North','active',1,'2026-09-03 05:45:19'),(274,7,'Embalegedara South','active',1,'2026-09-03 05:45:19'),(275,7,'Mawanana','active',1,'2026-09-03 05:45:19'),(276,7,'Neluwa','active',1,'2026-09-03 05:45:19'),(277,7,'Pahala Maddegama','active',1,'2026-09-03 05:45:19'),(278,7,'Maddegama East','active',1,'2026-09-03 05:45:19'),(279,7,'Happitiya','active',1,'2026-09-03 05:45:19'),(280,7,'Madugeta','active',1,'2026-09-03 05:45:19'),(281,7,'Warukandeniya','active',1,'2026-09-03 05:45:19'),(282,7,'Lankagama','active',1,'2026-09-03 05:45:19'),(283,7,'Dellawa','active',1,'2026-09-03 05:45:19'),(284,7,'Miyanawathura','active',1,'2026-09-03 05:45:19'),(285,7,'Pannimulla','active',1,'2026-09-03 05:45:19'),(286,7,'Ihala Maddegama','active',1,'2026-09-03 05:45:19'),(287,7,'Medagama','active',1,'2026-09-03 05:45:19'),(288,7,'Pahala Gigummaduwa','active',1,'2026-09-03 05:45:19'),(289,7,'Ihala Gigummaduwa','active',1,'2026-09-03 05:45:19'),(290,7,'Lelwala','active',1,'2026-09-03 05:45:19'),(291,7,'Ihala Lelwala','active',1,'2026-09-03 05:45:19'),(292,7,'Panagoda','active',1,'2026-09-03 05:45:19'),(293,7,'Dewalegama West','active',1,'2026-09-03 05:45:19'),(294,7,'Dewalegama East','active',1,'2026-09-03 05:45:19'),(295,7,'Millawa West','active',1,'2026-09-03 05:45:19'),(296,7,'Ihala Millawa','active',1,'2026-09-03 05:45:19'),(297,7,'Pahala Millawa','active',1,'2026-09-03 05:45:19'),(298,8,'Thalgaswala','active',1,'2026-09-03 05:45:19'),(299,8,'Marakanda','active',1,'2026-09-03 05:45:19'),(300,8,'Malamura','active',1,'2026-09-03 05:45:19'),(301,8,'Aluth Thanayamgoda Pahala West','active',1,'2026-09-03 05:45:19'),(302,8,'Mapalagama','active',1,'2026-09-03 05:45:19'),(303,8,'Aluth Thanayamgoda Pahala','active',1,'2026-09-03 05:45:19'),(304,8,'Aluth Thanayamgoda Ihala South','active',1,'2026-09-03 05:45:19'),(305,8,'Aluth Thanayamgoda Ihala','active',1,'2026-09-03 05:45:19'),(306,8,'Ketagoda South','active',1,'2026-09-03 05:45:19'),(307,8,'Ketagoda North','active',1,'2026-09-03 05:45:19'),(308,8,'Parana Thanayamgoda','active',1,'2026-09-03 05:45:19'),(309,8,'Parana Thanayamgoda Central','active',1,'2026-09-03 05:45:19'),(310,8,'Parana Thanayamgoda Pahala','active',1,'2026-09-03 05:45:19'),(311,8,'Gonalagoda East','active',1,'2026-09-03 05:45:19'),(312,8,'Gonalagoda','active',1,'2026-09-03 05:45:19'),(313,8,'Gammeddegoda','active',1,'2026-09-03 05:45:19'),(314,8,'Gammeddegoda South','active',1,'2026-09-03 05:45:19'),(315,8,'Nagoda Ihala','active',1,'2026-09-03 05:45:19'),(316,8,'Nagoda','active',1,'2026-09-03 05:45:19'),(317,8,'Kurupanawa','active',1,'2026-09-03 05:45:19'),(318,8,'Gonadeniya','active',1,'2026-09-03 05:45:19'),(319,8,'Ukovita North','active',1,'2026-09-03 05:45:19'),(320,8,'Udugama West','active',1,'2026-09-03 05:45:19'),(321,8,'Udugama North','active',1,'2026-09-03 05:45:19'),(322,8,'Homadola','active',1,'2026-09-03 05:45:19'),(323,8,'Udugama East','active',1,'2026-09-03 05:45:19'),(324,8,'Udugama','active',1,'2026-09-03 05:45:19'),(325,8,'Ukovita','active',1,'2026-09-03 05:45:19'),(326,8,'Gonadeniya South','active',1,'2026-09-03 05:45:19'),(327,8,'Udalamatta East','active',1,'2026-09-03 05:45:19'),(328,8,'Udalamatta North','active',1,'2026-09-03 05:45:19'),(329,8,'Keppitiyagoda','active',1,'2026-09-03 05:45:19'),(330,8,'Udawelivitiya','active',1,'2026-09-03 05:45:19'),(331,8,'Udawelivitithalawa East','active',1,'2026-09-03 05:45:19'),(332,8,'Udawelivitithalawa','active',1,'2026-09-03 05:45:19'),(333,8,'Udawelivitiya West','active',1,'2026-09-03 05:45:19'),(334,8,'Keppitiyagoda North','active',1,'2026-09-03 05:45:19'),(335,8,'Budapanagama','active',1,'2026-09-03 05:45:19'),(336,8,'Unanvitiya','active',1,'2026-09-03 05:45:19'),(337,8,'Unanvitiya East','active',1,'2026-09-03 05:45:19'),(338,8,'Urala North','active',1,'2026-09-03 05:45:19'),(339,8,'Urala Pahala','active',1,'2026-09-03 05:45:19'),(340,8,'Yatalamatta','active',1,'2026-09-03 05:45:19'),(341,8,'Yatalamatta East','active',1,'2026-09-03 05:45:19'),(342,8,'Udalamatta South','active',1,'2026-09-03 05:45:19'),(343,8,'Udugama South','active',1,'2026-09-03 05:45:19'),(344,8,'Udugama Central','active',1,'2026-09-03 05:45:19'),(345,8,'Aluthwatta','active',1,'2026-09-03 05:45:19'),(346,8,'Hangaranwala','active',1,'2026-09-03 05:45:19'),(347,8,'Yatalamatta West','active',1,'2026-09-03 05:45:19'),(348,8,'Urala Central','active',1,'2026-09-03 05:45:19'),(349,8,'Urala South','active',1,'2026-09-03 05:45:19'),(350,8,'Urala East','active',1,'2026-09-03 05:45:19'),(351,9,'Wavulagala','active',1,'2026-09-03 05:45:19'),(352,9,'Halpathota Central','active',1,'2026-09-03 05:45:19'),(353,9,'Halpathota','active',1,'2026-09-03 05:45:19'),(354,9,'Baddegama Town','active',1,'2026-09-03 05:45:19'),(355,9,'Baddegama North','active',1,'2026-09-03 05:45:19'),(356,9,'Nayapamula','active',1,'2026-09-03 05:45:19'),(357,9,'Kotagoda','active',1,'2026-09-03 05:45:19'),(358,9,'Hemmeliya','active',1,'2026-09-03 05:45:19'),(359,9,'Ellakanda Wathurawa','active',1,'2026-09-03 05:45:19'),(360,9,'Baddegama East','active',1,'2026-09-03 05:45:19'),(361,9,'Yahaladoowa','active',1,'2026-09-03 05:45:19'),(362,9,'Baddegama South','active',1,'2026-09-03 05:45:19'),(363,9,'Gothatuwa','active',1,'2026-09-03 05:45:19'),(364,9,'Majuwana','active',1,'2026-09-03 05:45:19'),(365,9,'Weweldeniya','active',1,'2026-09-03 05:45:19'),(366,9,'Keradewala','active',1,'2026-09-03 05:45:19'),(367,9,'Madoldoowa','active',1,'2026-09-03 05:45:19'),(368,9,'Bataketiya','active',1,'2026-09-03 05:45:19'),(369,9,'Ganegama North','active',1,'2026-09-03 05:45:19'),(370,9,'Sandarawala','active',1,'2026-09-03 05:45:19'),(371,9,'Boralukada','active',1,'2026-09-03 05:45:19'),(372,9,'Mahahengoda','active',1,'2026-09-03 05:45:19'),(373,9,'Mahalapitiya','active',1,'2026-09-03 05:45:19'),(374,9,'Thilaka Udagama','active',1,'2026-09-03 05:45:19'),(375,9,'Pilagoda','active',1,'2026-09-03 05:45:19'),(376,9,'Ganegama East','active',1,'2026-09-03 05:45:19'),(377,9,'Ganegama South','active',1,'2026-09-03 05:45:19'),(378,9,'Ganegama West','active',1,'2026-09-03 05:45:19'),(379,9,'Lelkada','active',1,'2026-09-03 05:45:19'),(380,9,'Ginimellagaha West','active',1,'2026-09-03 05:45:19'),(381,9,'Ginimellagaha East','active',1,'2026-09-03 05:45:19'),(382,9,'Dodangoda','active',1,'2026-09-03 05:45:19'),(383,9,'Thelikada','active',1,'2026-09-03 05:45:19'),(384,9,'Walpita North','active',1,'2026-09-03 05:45:19'),(385,9,'Keembiela','active',1,'2026-09-03 05:45:19'),(386,9,'Kohombanadeniya','active',1,'2026-09-03 05:45:19'),(387,9,'Kasideniya','active',1,'2026-09-03 05:45:19'),(388,9,'Pahala Keembiya','active',1,'2026-09-03 05:45:19'),(389,9,'Warakapitikanda','active',1,'2026-09-03 05:45:19'),(390,9,'Adurathvila','active',1,'2026-09-03 05:45:19'),(391,9,'Walpita South','active',1,'2026-09-03 05:45:19'),(392,9,'Balagoda','active',1,'2026-09-03 05:45:19'),(393,9,'Ginimellagaha South','active',1,'2026-09-03 05:45:19'),(394,9,'Pituwalgoda','active',1,'2026-09-03 05:45:19'),(395,9,'Thelikada Nagaraya','active',1,'2026-09-03 05:45:19'),(396,9,'Horagampita Central','active',1,'2026-09-03 05:45:19'),(397,9,'Gonapura','active',1,'2026-09-03 05:45:19'),(398,9,'Horagampita','active',1,'2026-09-03 05:45:19'),(399,10,'Ethkandura','active',1,'2026-09-03 05:45:19'),(400,10,'Old Colony','active',1,'2026-09-03 05:45:19'),(401,10,'Thanabaddegama','active',1,'2026-09-03 05:45:19'),(402,10,'Maddevila','active',1,'2026-09-03 05:45:19'),(403,10,'Miriswatta','active',1,'2026-09-03 05:45:19'),(404,10,'Nambara Atta','active',1,'2026-09-03 05:45:19'),(405,10,'Divithura East','active',1,'2026-09-03 05:45:19'),(406,10,'Polgahavila','active',1,'2026-09-03 05:45:19'),(407,10,'Divithura','active',1,'2026-09-03 05:45:19'),(408,10,'Ampegama','active',1,'2026-09-03 05:45:19'),(409,10,'Hamingala','active',1,'2026-09-03 05:45:19'),(410,10,'Nugethota','active',1,'2026-09-03 05:45:19'),(411,10,'Agaliya','active',1,'2026-09-03 05:45:19'),(412,10,'Divithura South','active',1,'2026-09-03 05:45:19'),(413,10,'Galahenkanda','active',1,'2026-09-03 05:45:19'),(414,10,'Kuttiyawatta','active',1,'2026-09-03 05:45:19'),(415,10,'Pathawelivitiya North','active',1,'2026-09-03 05:45:19'),(416,10,'Waduwelivitiya North','active',1,'2026-09-03 05:45:19'),(417,10,'Pathawelivitiya','active',1,'2026-09-03 05:45:19'),(418,10,'Waduwelivitiya','active',1,'2026-09-03 05:45:19'),(419,11,'Heppumulla','active',1,'2026-09-03 05:45:19'),(420,11,'Patabendimulla','active',1,'2026-09-03 05:45:19'),(421,11,'Karittakanda','active',1,'2026-09-03 05:45:19'),(422,11,'Kaluwadumulla','active',1,'2026-09-03 05:45:19'),(423,11,'Okanda','active',1,'2026-09-03 05:45:19'),(424,11,'Keraminiya','active',1,'2026-09-03 05:45:19'),(425,11,'Polwatta','active',1,'2026-09-03 05:45:19'),(426,11,'Thalgasgoda','active',1,'2026-09-03 05:45:19'),(427,11,'Thilakapura','active',1,'2026-09-03 05:45:19'),(428,11,'Thanipolgahalanga','active',1,'2026-09-03 05:45:19'),(429,11,'Batapola Central','active',1,'2026-09-03 05:45:19'),(430,11,'Kondagala','active',1,'2026-09-03 05:45:19'),(431,11,'Batapola North','active',1,'2026-09-03 05:45:19'),(432,11,'Kobeithuduwa','active',1,'2026-09-03 05:45:19'),(433,11,'Godahena','active',1,'2026-09-03 05:45:19'),(434,11,'Poramba','active',1,'2026-09-03 05:45:19'),(435,11,'Vilegoda','active',1,'2026-09-03 05:45:19'),(436,11,'Paniyandoowa','active',1,'2026-09-03 05:45:19'),(437,11,'Hirewatta','active',1,'2026-09-03 05:45:19'),(438,11,'Maha Ambalangoda','active',1,'2026-09-03 05:45:19'),(439,11,'Batadoowa','active',1,'2026-09-03 05:45:19'),(440,11,'Batapola West','active',1,'2026-09-03 05:45:19'),(441,11,'Batapola South','active',1,'2026-09-03 05:45:19'),(442,11,'Dorala','active',1,'2026-09-03 05:45:19'),(443,11,'Batapola East','active',1,'2026-09-03 05:45:19'),(444,11,'Polhunnawa','active',1,'2026-09-03 05:45:19'),(445,11,'Nawagama','active',1,'2026-09-03 05:45:19'),(446,11,'Nindana','active',1,'2026-09-03 05:45:19'),(447,11,'Lewdoowa','active',1,'2026-09-03 05:45:19'),(448,11,'Udakerewa','active',1,'2026-09-03 05:45:19'),(449,11,'Domanvila','active',1,'2026-09-03 05:45:19'),(450,11,'Diddeliya','active',1,'2026-09-03 05:45:19'),(451,11,'Eranavila','active',1,'2026-09-03 05:45:19'),(452,11,'Meetiyagoda','active',1,'2026-09-03 05:45:19'),(453,11,'Metiwala','active',1,'2026-09-03 05:45:19'),(454,11,'Walakada','active',1,'2026-09-03 05:45:19'),(455,12,'Karuwalabedda','active',1,'2026-09-03 05:45:19'),(456,12,'Manampita','active',1,'2026-09-03 05:45:19'),(457,12,'Dangaragaha Udumulla','active',1,'2026-09-03 05:45:19'),(458,12,'Banwelgodella','active',1,'2026-09-03 05:45:19'),(459,12,'Eriyagahamulla','active',1,'2026-09-03 05:45:19'),(460,12,'Aluthwala','active',1,'2026-09-03 05:45:19'),(461,12,'Mahagangoda','active',1,'2026-09-03 05:45:19'),(462,12,'Kirindiela','active',1,'2026-09-03 05:45:19'),(463,12,'Thilakagama','active',1,'2026-09-03 05:45:19'),(464,12,'Henagoda','active',1,'2026-09-03 05:45:19'),(465,12,'Gonapeenuwala Central','active',1,'2026-09-03 05:45:19'),(466,12,'Woodland Watta','active',1,'2026-09-03 05:45:19'),(467,12,'Berathuduwa','active',1,'2026-09-03 05:45:19'),(468,12,'Hikkaduwa East','active',1,'2026-09-03 05:45:19'),(469,12,'Arachchikanda','active',1,'2026-09-03 05:45:19'),(470,12,'Gonapeenuwala East','active',1,'2026-09-03 05:45:19'),(471,12,'Gonapeenuwala West','active',1,'2026-09-03 05:45:19'),(472,12,'Dodamkahavila','active',1,'2026-09-03 05:45:19'),(473,12,'Kaluwagaha Colony','active',1,'2026-09-03 05:45:19'),(474,13,'Wellawatta','active',1,'2026-09-03 05:45:19'),(475,13,'Hikkaduwa Nagarikaya','active',1,'2026-09-03 05:45:19'),(476,13,'Wavulagoda West','active',1,'2026-09-03 05:45:19'),(477,13,'Wavulagoda East','active',1,'2026-09-03 05:45:19'),(478,13,'Hikkaduwa West','active',1,'2026-09-03 05:45:19'),(479,13,'Nakanda','active',1,'2026-09-03 05:45:19'),(480,13,'Hikkaduwa Central','active',1,'2026-09-03 05:45:19'),(481,13,'Nalagasdeniya','active',1,'2026-09-03 05:45:19'),(482,13,'Millagoda','active',1,'2026-09-03 05:45:19'),(483,13,'Pannamgoda','active',1,'2026-09-03 05:45:19'),(484,13,'Wewala','active',1,'2026-09-03 05:45:19'),(485,13,'Narigama Wellabada','active',1,'2026-09-03 05:45:19'),(486,13,'Narigama','active',1,'2026-09-03 05:45:19'),(487,13,'Kuda Wewala','active',1,'2026-09-03 05:45:19'),(488,13,'Delgahadoowa','active',1,'2026-09-03 05:45:19'),(489,13,'Katukoliha','active',1,'2026-09-03 05:45:19'),(490,13,'Thiranagama','active',1,'2026-09-03 05:45:19'),(491,13,'Thiranagama Wellabada','active',1,'2026-09-03 05:45:19'),(492,13,'Patuwatha','active',1,'2026-09-03 05:45:19'),(493,13,'Gammaduwatta','active',1,'2026-09-03 05:45:19'),(494,13,'Hennathota','active',1,'2026-09-03 05:45:19'),(495,13,'Pinkanda','active',1,'2026-09-03 05:45:19'),(496,13,'Handaudumulla','active',1,'2026-09-03 05:45:19'),(497,13,'Dodandugoda','active',1,'2026-09-03 05:45:19'),(498,13,'Modara Patuwatha','active',1,'2026-09-03 05:45:19'),(499,13,'Dodandoowa','active',1,'2026-09-03 05:45:19'),(500,13,'Uduhalpitiya','active',1,'2026-09-03 05:45:19'),(501,14,'Ukwatta East','active',1,'2026-09-03 05:45:19'),(502,14,'Ukwatta West','active',1,'2026-09-03 05:45:19'),(503,14,'Maha Hapugala','active',1,'2026-09-03 05:45:19'),(504,14,'Kurunduwatta','active',1,'2026-09-03 05:45:19'),(505,14,'Welipitimodara','active',1,'2026-09-03 05:45:19'),(506,14,'Ginthota West','active',1,'2026-09-03 05:45:19'),(507,14,'Ginthota East','active',1,'2026-09-03 05:45:19'),(508,14,'Piyadigama','active',1,'2026-09-03 05:45:19'),(509,14,'Bope North','active',1,'2026-09-03 05:45:19'),(510,14,'Bope East','active',1,'2026-09-03 05:45:19'),(511,14,'Kumbalwella North','active',1,'2026-09-03 05:45:19'),(512,14,'Madawalamulla North','active',1,'2026-09-03 05:45:19'),(513,14,'Madawalamulla South','active',1,'2026-09-03 05:45:19'),(514,14,'Deddugoda North','active',1,'2026-09-03 05:45:19'),(515,14,'Deddugoda South','active',1,'2026-09-03 05:45:19'),(516,14,'Maitipe','active',1,'2026-09-03 05:45:19'),(517,14,'Welipatha','active',1,'2026-09-03 05:45:19'),(518,14,'Maligaspe','active',1,'2026-09-03 05:45:19'),(519,14,'Dangedara East','active',1,'2026-09-03 05:45:19'),(520,14,'Dangedara West','active',1,'2026-09-03 05:45:19'),(521,14,'Bataganvila','active',1,'2026-09-03 05:45:19'),(522,14,'Galwadugoda','active',1,'2026-09-03 05:45:19'),(523,14,'Richmond Kanda','active',1,'2026-09-03 05:45:19'),(524,14,'Bope West','active',1,'2026-09-03 05:45:19'),(525,14,'Siyambalagahawatta','active',1,'2026-09-03 05:45:19'),(526,14,'Dadalla East','active',1,'2026-09-03 05:45:19'),(527,14,'Dadalla West','active',1,'2026-09-03 05:45:19'),(528,14,'Walawwatta','active',1,'2026-09-03 05:45:19'),(529,14,'Kumbalwella South','active',1,'2026-09-03 05:45:19'),(530,14,'Mahamodara','active',1,'2026-09-03 05:45:19'),(531,14,'Osanagoda','active',1,'2026-09-03 05:45:19'),(532,14,'Kandewatta','active',1,'2026-09-03 05:45:19'),(533,14,'Sanghamittapura','active',1,'2026-09-03 05:45:19'),(534,14,'Madapathala','active',1,'2026-09-03 05:45:19'),(535,14,'Pokunawatta','active',1,'2026-09-03 05:45:19'),(536,14,'Milidduwa','active',1,'2026-09-03 05:45:19'),(537,14,'Ettiligoda South','active',1,'2026-09-03 05:45:19'),(538,14,'Makuluwa','active',1,'2026-09-03 05:45:19'),(539,14,'Pettigalawatta','active',1,'2026-09-03 05:45:19'),(540,14,'Thalapitiya','active',1,'2026-09-03 05:45:19'),(541,14,'Kongaha','active',1,'2026-09-03 05:45:19'),(542,14,'Weliwatta','active',1,'2026-09-03 05:45:19'),(543,14,'Minuwangoda','active',1,'2026-09-03 05:45:19'),(544,14,'Kaluwella','active',1,'2026-09-03 05:45:19'),(545,14,'Cheena Koratuwa','active',1,'2026-09-03 05:45:19'),(546,14,'Fort','active',1,'2026-09-03 05:45:19'),(547,14,'Magalla','active',1,'2026-09-03 05:45:19'),(548,14,'Dewathura','active',1,'2026-09-03 05:45:19'),(549,14,'Katugoda','active',1,'2026-09-03 05:45:19'),(550,14,'Dewata','active',1,'2026-09-03 05:45:19'),(551,15,'Poddala','active',1,'2026-09-03 05:45:19'),(552,15,'Pannamaga','active',1,'2026-09-03 05:45:19'),(553,15,'Mulana West','active',1,'2026-09-03 05:45:19'),(554,15,'Mulana East','active',1,'2026-09-03 05:45:19'),(555,15,'Panvila','active',1,'2026-09-03 05:45:19'),(556,15,'Narawala','active',1,'2026-09-03 05:45:19'),(557,15,'Walawatta','active',1,'2026-09-03 05:45:19'),(558,15,'Magadeniya','active',1,'2026-09-03 05:45:19'),(559,15,'Baswatta','active',1,'2026-09-03 05:45:19'),(560,15,'Addaragoda','active',1,'2026-09-03 05:45:19'),(561,15,'Meepawala','active',1,'2026-09-03 05:45:19'),(562,15,'Penideniya','active',1,'2026-09-03 05:45:19'),(563,15,'Opatha','active',1,'2026-09-03 05:45:19'),(564,15,'Wakwella','active',1,'2026-09-03 05:45:19'),(565,15,'Niladeniya','active',1,'2026-09-03 05:45:19'),(566,15,'Hapugala','active',1,'2026-09-03 05:45:19'),(567,15,'Beraliyadola','active',1,'2026-09-03 05:45:19'),(568,15,'Silvagewatta','active',1,'2026-09-03 05:45:19'),(569,15,'Uluvitike','active',1,'2026-09-03 05:45:19'),(570,15,'Holuwagoda','active',1,'2026-09-03 05:45:19'),(571,15,'Labudoowa','active',1,'2026-09-03 05:45:19'),(572,15,'Kurunda Kanda','active',1,'2026-09-03 05:45:19'),(573,15,'Kurunda','active',1,'2026-09-03 05:45:19'),(574,15,'Thotagoda','active',1,'2026-09-03 05:45:19'),(575,15,'Galketiya','active',1,'2026-09-03 05:45:19'),(576,15,'Godakanda','active',1,'2026-09-03 05:45:19'),(577,15,'Bangalawatta','active',1,'2026-09-03 05:45:19'),(578,15,'Bokaramullagoda','active',1,'2026-09-03 05:45:19'),(579,15,'Thunhiripana','active',1,'2026-09-03 05:45:19'),(580,15,'Abeysundarawatta','active',1,'2026-09-03 05:45:19'),(581,15,'Watareka East','active',1,'2026-09-03 05:45:19'),(582,15,'Pelawatta','active',1,'2026-09-03 05:45:19'),(583,15,'Mampitiya','active',1,'2026-09-03 05:45:19'),(584,15,'Kalegana South','active',1,'2026-09-03 05:45:19'),(585,15,'Kalegana North','active',1,'2026-09-03 05:45:19'),(586,15,'Kithulampitiya','active',1,'2026-09-03 05:45:19'),(587,15,'Kahadoowawatta','active',1,'2026-09-03 05:45:19'),(588,15,'Navinna','active',1,'2026-09-03 05:45:19'),(589,15,'Hirimburagama','active',1,'2026-09-03 05:45:19'),(590,15,'Karapitiya','active',1,'2026-09-03 05:45:19'),(591,15,'Ambagahawatta','active',1,'2026-09-03 05:45:19'),(592,15,'Kapuhempala','active',1,'2026-09-03 05:45:19'),(593,15,'Kerenvila','active',1,'2026-09-03 05:45:19'),(594,15,'Paliwathugoda','active',1,'2026-09-03 05:45:19'),(595,16,'Thalgasyaya','active',1,'2026-09-03 05:45:19'),(596,16,'Niyagama','active',1,'2026-09-03 05:45:19'),(597,16,'Ambagahavila','active',1,'2026-09-03 05:45:19'),(598,16,'Ihalagoda East','active',1,'2026-09-03 05:45:19'),(599,16,'Amalgama','active',1,'2026-09-03 05:45:19'),(600,16,'Ketandola','active',1,'2026-09-03 05:45:19'),(601,16,'Hiyare North','active',1,'2026-09-03 05:45:19'),(602,16,'Kandahena','active',1,'2026-09-03 05:45:19'),(603,16,'Welihena','active',1,'2026-09-03 05:45:19'),(604,16,'Kadurugashena','active',1,'2026-09-03 05:45:19'),(605,16,'Hiyare East','active',1,'2026-09-03 05:45:19'),(606,16,'Ihala Hiyare','active',1,'2026-09-03 05:45:19'),(607,16,'Hiyare South','active',1,'2026-09-03 05:45:19'),(608,16,'Etambagasmulla','active',1,'2026-09-03 05:45:19'),(609,16,'Ihalagoda Colony','active',1,'2026-09-03 05:45:19'),(610,16,'Ihalagoda South','active',1,'2026-09-03 05:45:19'),(611,16,'Ihalagoda West','active',1,'2026-09-03 05:45:19'),(612,16,'Akmeemana','active',1,'2026-09-03 05:45:19'),(613,16,'Ganegoda','active',1,'2026-09-03 05:45:19'),(614,16,'Ganegoda West','active',1,'2026-09-03 05:45:19'),(615,16,'Kirindagoda','active',1,'2026-09-03 05:45:19'),(616,16,'Badungoda Colony','active',1,'2026-09-03 05:45:19'),(617,16,'Badungoda','active',1,'2026-09-03 05:45:19'),(618,16,'Pinnadoowa Colony','active',1,'2026-09-03 05:45:19'),(619,16,'Weliketiya','active',1,'2026-09-03 05:45:19'),(620,16,'Halgasmulla','active',1,'2026-09-03 05:45:19'),(621,16,'Amukotuwa','active',1,'2026-09-03 05:45:19'),(622,16,'Nivithipitigoda','active',1,'2026-09-03 05:45:19'),(623,16,'Thalahitiyawa','active',1,'2026-09-03 05:45:19'),(624,16,'Ankokkawala','active',1,'2026-09-03 05:45:19'),(625,16,'Pinnadoowa','active',1,'2026-09-03 05:45:19'),(626,16,'Manavila','active',1,'2026-09-03 05:45:19'),(627,16,'Walahanduwa','active',1,'2026-09-03 05:45:19'),(628,16,'Yakgaha','active',1,'2026-09-03 05:45:19'),(629,16,'Meegoda','active',1,'2026-09-03 05:45:19'),(630,16,'Pilana','active',1,'2026-09-03 05:45:19'),(631,16,'Pedinnoruwa','active',1,'2026-09-03 05:45:19'),(632,16,'Melegoda','active',1,'2026-09-03 05:45:19'),(633,16,'Bambaragoda','active',1,'2026-09-03 05:45:19'),(634,16,'Anangoda','active',1,'2026-09-03 05:45:19'),(635,16,'Hinidumgoda','active',1,'2026-09-03 05:45:19'),(636,16,'Kerenvila Colony','active',1,'2026-09-03 05:45:19'),(637,16,'Haliwala','active',1,'2026-09-03 05:45:19'),(638,16,'Ettiligoda North','active',1,'2026-09-03 05:45:19'),(639,16,'Ambalanwatta','active',1,'2026-09-03 05:45:19'),(640,16,'Jambuketiya','active',1,'2026-09-03 05:45:19'),(641,16,'Batadoowa','active',1,'2026-09-03 05:45:19'),(642,16,'Kaduruduwa','active',1,'2026-09-03 05:45:19'),(643,16,'Panagamuwa','active',1,'2026-09-03 05:45:19'),(644,16,'Galgamuwa','active',1,'2026-09-03 05:45:19'),(645,16,'Divulana Colony','active',1,'2026-09-03 05:45:19'),(646,16,'Handugoda','active',1,'2026-09-03 05:45:19'),(647,16,'Kalahe','active',1,'2026-09-03 05:45:19'),(648,16,'Wanchawala','active',1,'2026-09-03 05:45:19'),(649,16,'Attaragoda','active',1,'2026-09-03 05:45:19'),(650,16,'Nugadoowa','active',1,'2026-09-03 05:45:19'),(651,16,'Batadoowa West','active',1,'2026-09-03 05:45:19'),(652,16,'Thalpegoda','active',1,'2026-09-03 05:45:19'),(653,16,'Yatagama','active',1,'2026-09-03 05:45:19'),(654,16,'Rathkindagoda','active',1,'2026-09-03 05:45:19'),(655,16,'Metaramba','active',1,'2026-09-03 05:45:19'),(656,16,'Thalpe North','active',1,'2026-09-03 05:45:19'),(657,16,'Nagahawatta','active',1,'2026-09-03 05:45:19'),(658,17,'Nawala','active',1,'2026-09-03 05:45:19'),(659,17,'Thellambura North','active',1,'2026-09-03 05:45:19'),(660,17,'Nakiyadeniya','active',1,'2026-09-03 05:45:19'),(661,17,'Nakiyadeniya North','active',1,'2026-09-03 05:45:19'),(662,17,'Wattehena','active',1,'2026-09-03 05:45:19'),(663,17,'Wathogala','active',1,'2026-09-03 05:45:19'),(664,17,'Moraketiya','active',1,'2026-09-03 05:45:19'),(665,17,'Gahalakoladeniya','active',1,'2026-09-03 05:45:19'),(666,17,'Udumalagala','active',1,'2026-09-03 05:45:19'),(667,17,'Ihala Nakiyadeniya','active',1,'2026-09-03 05:45:19'),(668,17,'Pahala Thellambura','active',1,'2026-09-03 05:45:19'),(669,17,'Ihala Thellambura','active',1,'2026-09-03 05:45:19'),(670,17,'Nevungala','active',1,'2026-09-03 05:45:19'),(671,17,'Nevungala South','active',1,'2026-09-03 05:45:19'),(672,17,'Kottawa','active',1,'2026-09-03 05:45:19'),(673,17,'Thellambura South','active',1,'2026-09-03 05:45:19'),(674,17,'Yakkalamulla East','active',1,'2026-09-03 05:45:19'),(675,17,'Nabadawa','active',1,'2026-09-03 05:45:19'),(676,17,'Yatamalagala','active',1,'2026-09-03 05:45:19'),(677,17,'Ihala Karagoda','active',1,'2026-09-03 05:45:19'),(678,17,'Magedara North','active',1,'2026-09-03 05:45:19'),(679,17,'Magedara East','active',1,'2026-09-03 05:45:19'),(680,17,'Ella Ihala','active',1,'2026-09-03 05:45:19'),(681,17,'Magedara','active',1,'2026-09-03 05:45:19'),(682,17,'Uduwella','active',1,'2026-09-03 05:45:19'),(683,17,'Karagoda','active',1,'2026-09-03 05:45:19'),(684,17,'Pahala Karagoda','active',1,'2026-09-03 05:45:19'),(685,17,'Polpagoda','active',1,'2026-09-03 05:45:19'),(686,17,'Beranagoda','active',1,'2026-09-03 05:45:19'),(687,17,'Yakkalamulla','active',1,'2026-09-03 05:45:19'),(688,17,'Kottawa East','active',1,'2026-09-03 05:45:19'),(689,17,'Kottawa West','active',1,'2026-09-03 05:45:19'),(690,17,'Thalgampala North','active',1,'2026-09-03 05:45:19'),(691,17,'Udubettawa','active',1,'2026-09-03 05:45:19'),(692,17,'Udubettawa West','active',1,'2026-09-03 05:45:19'),(693,17,'Thalgampala','active',1,'2026-09-03 05:45:19'),(694,17,'Hiriyamalkumbura','active',1,'2026-09-03 05:45:19'),(695,17,'Polpagoda West','active',1,'2026-09-03 05:45:19'),(696,17,'Kaludiyawala','active',1,'2026-09-03 05:45:19'),(697,17,'Badungala','active',1,'2026-09-03 05:45:19'),(698,17,'Rathamalaketiya','active',1,'2026-09-03 05:45:19'),(699,17,'Welendawa','active',1,'2026-09-03 05:45:19'),(700,17,'Pahala Walpola','active',1,'2026-09-03 05:45:19'),(701,17,'Ihala Walpala','active',1,'2026-09-03 05:45:19'),(702,18,'Pituwalahena','active',1,'2026-09-03 05:45:19'),(703,18,'Mayakaduwa','active',1,'2026-09-03 05:45:19'),(704,18,'Kabaragala','active',1,'2026-09-03 05:45:19'),(705,18,'Danduwana','active',1,'2026-09-03 05:45:19'),(706,18,'Hatangala','active',1,'2026-09-03 05:45:19'),(707,18,'Bedipita','active',1,'2026-09-03 05:45:19'),(708,18,'Puswelkada','active',1,'2026-09-03 05:45:19'),(709,18,'Ihala Kombala','active',1,'2026-09-03 05:45:19'),(710,18,'Kombala','active',1,'2026-09-03 05:45:19'),(711,18,'Imaduwa Athireka 1','active',1,'2026-09-03 05:45:19'),(712,18,'Paragoda','active',1,'2026-09-03 05:45:19'),(713,18,'Wathawana','active',1,'2026-09-03 05:45:19'),(714,18,'Hawpe North','active',1,'2026-09-03 05:45:19'),(715,18,'Hawpe','active',1,'2026-09-03 05:45:19'),(716,18,'Angulugaha','active',1,'2026-09-03 05:45:19'),(717,18,'Rangoda','active',1,'2026-09-03 05:45:19'),(718,18,'Dorape','active',1,'2026-09-03 05:45:19'),(719,18,'Welikonda','active',1,'2026-09-03 05:45:19'),(720,18,'Pelawatta','active',1,'2026-09-03 05:45:19'),(721,18,'Polhena','active',1,'2026-09-03 05:45:19'),(722,18,'Kahanda','active',1,'2026-09-03 05:45:19'),(723,18,'Kahanda Athireka 1','active',1,'2026-09-03 05:45:19'),(724,18,'Godaudamandiya','active',1,'2026-09-03 05:45:19'),(725,18,'Mawella','active',1,'2026-09-03 05:45:19'),(726,18,'Deegoda Athireka 01','active',1,'2026-09-03 05:45:19'),(727,18,'Imaduwa','active',1,'2026-09-03 05:45:19'),(728,18,'Hettigoda','active',1,'2026-09-03 05:45:19'),(729,18,'Deegoda','active',1,'2026-09-03 05:45:19'),(730,18,'Kalugalagoda','active',1,'2026-09-03 05:45:19'),(731,18,'Ihala Mawella','active',1,'2026-09-03 05:45:19'),(732,18,'Thittagalla East','active',1,'2026-09-03 05:45:19'),(733,18,'Ampavila','active',1,'2026-09-03 05:45:19'),(734,18,'Malalgodapitiya','active',1,'2026-09-03 05:45:19'),(735,18,'Dikkumbura','active',1,'2026-09-03 05:45:19'),(736,18,'Kodagoda South','active',1,'2026-09-03 05:45:19'),(737,18,'Kodagoda East','active',1,'2026-09-03 05:45:19'),(738,18,'Horadugoda','active',1,'2026-09-03 05:45:19'),(739,18,'Ellalagoda','active',1,'2026-09-03 05:45:19'),(740,18,'Andugoda','active',1,'2026-09-03 05:45:19'),(741,18,'Panugalgoda','active',1,'2026-09-03 05:45:19'),(742,18,'Indurannavila','active',1,'2026-09-03 05:45:19'),(743,18,'Atanikitha','active',1,'2026-09-03 05:45:19'),(744,18,'Thittagalla West','active',1,'2026-09-03 05:45:19'),(745,19,'Yaddehimulla','active',1,'2026-09-03 05:45:19'),(746,19,'Bonavistawa','active',1,'2026-09-03 05:45:19'),(747,19,'Unawatuna West','active',1,'2026-09-03 05:45:19'),(748,19,'Unawatuna East','active',1,'2026-09-03 05:45:19'),(749,19,'Maharamba','active',1,'2026-09-03 05:45:19'),(750,19,'Dalawella','active',1,'2026-09-03 05:45:19'),(751,19,'Unawatuna Central','active',1,'2026-09-03 05:45:19'),(752,19,'Thalpe South','active',1,'2026-09-03 05:45:19'),(753,19,'Heenatigala South','active',1,'2026-09-03 05:45:19'),(754,19,'Wellethota','active',1,'2026-09-03 05:45:19'),(755,19,'Halloluwagoda','active',1,'2026-09-03 05:45:19'),(756,19,'Handugoda','active',1,'2026-09-03 05:45:19'),(757,19,'Dodampe','active',1,'2026-09-03 05:45:19'),(758,19,'Attaragoda','active',1,'2026-09-03 05:45:19'),(759,19,'Thalpe East','active',1,'2026-09-03 05:45:19'),(760,19,'Kahawennagama','active',1,'2026-09-03 05:45:19'),(761,19,'Morampitigoda','active',1,'2026-09-03 05:45:19'),(762,19,'Uragasgoda','active',1,'2026-09-03 05:45:19'),(763,19,'Pitidoowa','active',1,'2026-09-03 05:45:19'),(764,19,'Meepe','active',1,'2026-09-03 05:45:19'),(765,19,'Bogahamulugoda','active',1,'2026-09-03 05:45:19'),(766,19,'Happawana','active',1,'2026-09-03 05:45:19'),(767,19,'Annasiwathugoda','active',1,'2026-09-03 05:45:19'),(768,19,'Harumalgoda West','active',1,'2026-09-03 05:45:19'),(769,19,'Lanumodara','active',1,'2026-09-03 05:45:19'),(770,19,'Liyanagoda','active',1,'2026-09-03 05:45:19'),(771,19,'Katukurunda','active',1,'2026-09-03 05:45:19'),(772,19,'Harumalgoda Central','active',1,'2026-09-03 05:45:19'),(773,19,'Godawatta','active',1,'2026-09-03 05:45:19'),(774,19,'Harumalgoda East','active',1,'2026-09-03 05:45:19'),(775,19,'Koggala Athireka I','active',1,'2026-09-03 05:45:19'),(776,19,'Koggala Athireka II','active',1,'2026-09-03 05:45:19'),(777,19,'Koggala','active',1,'2026-09-03 05:45:19'),(778,19,'Kathaluwa West','active',1,'2026-09-03 05:45:19'),(779,19,'Atadahewathugoda','active',1,'2026-09-03 05:45:19'),(780,19,'Kathaluwa East','active',1,'2026-09-03 05:45:19'),(781,19,'Welhengoda','active',1,'2026-09-03 05:45:19'),(782,19,'Kathaluwa Central','active',1,'2026-09-03 05:45:19'),(783,19,'Alawathuthisgoda','active',1,'2026-09-03 05:45:19'),(784,19,'Pelessa','active',1,'2026-09-03 05:45:19'),(785,19,'Korahedigoda','active',1,'2026-09-03 05:45:19'),(786,19,'Ahangama Nakanda','active',1,'2026-09-03 05:45:19'),(787,19,'Kalapuwa','active',1,'2026-09-03 05:45:19'),(788,19,'Ahangamgoda','active',1,'2026-09-03 05:45:19'),(789,19,'Danduhela','active',1,'2026-09-03 05:45:19'),(790,19,'Meegahagoda','active',1,'2026-09-03 05:45:19'),(791,19,'Kahawathugoda','active',1,'2026-09-03 05:45:19'),(792,19,'Meliyagoda','active',1,'2026-09-03 05:45:19'),(793,19,'Piyadigama West','active',1,'2026-09-03 05:45:19'),(794,19,'Wadugegoda','active',1,'2026-09-03 05:45:19'),(795,19,'Karandugoda','active',1,'2026-09-03 05:45:19'),(796,19,'Kalahegoda','active',1,'2026-09-03 05:45:19'),(797,19,'Dommannegoda','active',1,'2026-09-03 05:45:19'),(798,19,'Piyadigama East','active',1,'2026-09-03 05:45:19'),(799,19,'Ahangama Central','active',1,'2026-09-03 05:45:19'),(800,19,'Digaredda','active',1,'2026-09-03 05:45:19'),(801,19,'Thaldoowa','active',1,'2026-09-03 05:45:19'),(802,19,'Ahangama East','active',1,'2026-09-03 05:45:19'),(803,19,'Goviyapana','active',1,'2026-09-03 05:45:19'),(804,20,'Weihena','active',1,'2026-09-03 05:45:19'),(805,20,'Polgahavila','active',1,'2026-09-03 05:45:19'),(806,20,'Indurupathvila','active',1,'2026-09-03 05:45:19'),(807,20,'Kirindalahena','active',1,'2026-09-03 05:45:19'),(808,20,'Pahala Lelwala','active',1,'2026-09-03 05:45:19'),(809,20,'Ihala Lelwala','active',1,'2026-09-03 05:45:19'),(810,20,'Kumbalamalahena','active',1,'2026-09-03 05:45:19'),(811,20,'Wanduramba','active',1,'2026-09-03 05:45:19'),(812,20,'Gulugahakanda','active',1,'2026-09-03 05:45:19'),(813,20,'Panvila','active',1,'2026-09-03 05:45:19'),(814,20,'Kokawala','active',1,'2026-09-03 05:45:19'),(815,20,'Meda Keembiya','active',1,'2026-09-03 05:45:19'),(816,20,'Deiyandara','active',1,'2026-09-03 05:45:19'),(817,20,'Wanduramba South','active',1,'2026-09-03 05:45:19'),(818,20,'Thalawa','active',1,'2026-09-03 05:45:19'),(819,20,'Mabotuwana','active',1,'2026-09-03 05:45:19'),(820,20,'Nattewela','active',1,'2026-09-03 05:45:19'),(821,20,'Thiruwanaketiya','active',1,'2026-09-03 05:45:19'),(822,20,'Pitiharawa','active',1,'2026-09-03 05:45:19'),(823,20,'Meda Keembiya East','active',1,'2026-09-03 05:45:19'),(824,20,'Ihala Keembiya South','active',1,'2026-09-03 05:45:19'),(825,20,'Ihala Keembiya','active',1,'2026-09-03 05:45:19'),(826,21,'Urawatta','active',1,'2026-09-03 05:45:19'),(827,21,'Dewagoda West','active',1,'2026-09-03 05:45:19'),(828,21,'Dewagoda East','active',1,'2026-09-03 05:45:19'),(829,21,'Deldoowa','active',1,'2026-09-03 05:45:19'),(830,21,'Idanthota','active',1,'2026-09-03 05:45:19'),(831,21,'Kuleegoda West','active',1,'2026-09-03 05:45:19'),(832,21,'Galagoda East','active',1,'2026-09-03 05:45:19'),(833,21,'Galagoda West','active',1,'2026-09-03 05:45:19'),(834,21,'Kuleegoda East','active',1,'2026-09-03 05:45:19'),(835,21,'Wellabada','active',1,'2026-09-03 05:45:19'),(836,21,'Usmudulawa','active',1,'2026-09-03 05:45:19'),(837,21,'Wenamulla','active',1,'2026-09-03 05:45:19'),(838,21,'Dimbuldoowa','active',1,'2026-09-03 05:45:19'),(839,21,'Andurangoda','active',1,'2026-09-03 05:45:19'),(840,21,'Galdoowa','active',1,'2026-09-03 05:45:19'),(841,21,'Akurala','active',1,'2026-09-03 05:45:19'),(842,21,'Akurala North','active',1,'2026-09-03 05:45:19'),(843,21,'Akurala South','active',1,'2026-09-03 05:45:19'),(844,21,'Uduwaragoda North','active',1,'2026-09-03 05:45:19'),(845,21,'Uduwaragoda South','active',1,'2026-09-03 05:45:19'),(846,21,'Kahawa','active',1,'2026-09-03 05:45:19'),(847,21,'Weragoda','active',1,'2026-09-03 05:45:19'),(848,21,'Delmar Colony','active',1,'2026-09-03 05:45:19'),(849,21,'Harannagala','active',1,'2026-09-03 05:45:19'),(850,21,'Godagama South','active',1,'2026-09-03 05:45:19'),(851,21,'Godagama North','active',1,'2026-09-03 05:45:19'),(852,21,'Daluwathumulla','active',1,'2026-09-03 05:45:19'),(853,21,'Thelwatta','active',1,'2026-09-03 05:45:19'),(854,21,'Pereliya North','active',1,'2026-09-03 05:45:19'),(855,21,'Pereliya South','active',1,'2026-09-03 05:45:19'),(856,21,'Seenigama','active',1,'2026-09-03 05:45:19'),(857,21,'Seenigama East','active',1,'2026-09-03 05:45:19'),(858,21,'Malawenna','active',1,'2026-09-03 05:45:19'),(859,21,'Kalupe','active',1,'2026-09-03 05:45:19'),(860,21,'Udumulla','active',1,'2026-09-03 05:45:19'),(861,21,'Medagoda','active',1,'2026-09-03 05:45:19'),(862,21,'Werellana','active',1,'2026-09-03 05:45:19'),(863,21,'Thotagamuwa','active',1,'2026-09-03 05:45:19'),(864,22,'Thotavila','active',1,'2026-09-03 05:45:19'),(865,22,'Mawadavila','active',1,'2026-09-03 05:45:19'),(866,22,'Panvila Pahalagoda','active',1,'2026-09-03 05:45:19'),(867,22,'Rejjipura','active',1,'2026-09-03 05:45:19'),(868,22,'Imbula','active',1,'2026-09-03 05:45:19'),(869,22,'Katudampe','active',1,'2026-09-03 05:45:19'),(870,22,'Karawegoda','active',1,'2026-09-03 05:45:19'),(871,22,'Devinigoda','active',1,'2026-09-03 05:45:19'),(872,22,'Bopagoda','active',1,'2026-09-03 05:45:19'),(873,22,'Kandegoda','active',1,'2026-09-03 05:45:19'),(874,22,'Ranapanadeniya','active',1,'2026-09-03 05:45:19'),(875,22,'Mahahegoda','active',1,'2026-09-03 05:45:19'),(876,22,'Medawala','active',1,'2026-09-03 05:45:19'),(877,22,'Hegoda','active',1,'2026-09-03 05:45:19'),(878,22,'Palanthriyagoda','active',1,'2026-09-03 05:45:19'),(879,22,'Maliduwa','active',1,'2026-09-03 05:45:19'),(880,22,'Rathna Udagama','active',1,'2026-09-03 05:45:19'),(881,22,'Ganegoda','active',1,'2026-09-03 05:45:19'),(882,22,'Rathgama Hegoda','active',1,'2026-09-03 05:45:19'),(883,22,'Palliyapitiya','active',1,'2026-09-03 05:45:19'),(884,22,'Gammeddegoda','active',1,'2026-09-03 05:45:19'),(885,22,'Gammeddegoda East','active',1,'2026-09-03 05:45:19'),(886,22,'Gammeddegoda-Rajgama','active',1,'2026-09-03 05:45:19'),(887,22,'Owakanda','active',1,'2026-09-03 05:45:19'),(888,22,'Kapumulugoda','active',1,'2026-09-03 05:45:19'),(889,22,'Dolikanda','active',1,'2026-09-03 05:45:19'),(890,22,'Boossa','active',1,'2026-09-03 05:45:19'),(891,22,'Rupeewala','active',1,'2026-09-03 05:45:19'),(892,22,'Kedala','active',1,'2026-09-03 05:45:19'),(893,22,'Pitiwella South','active',1,'2026-09-03 05:45:19'),(894,22,'Pitiwella North','active',1,'2026-09-03 05:45:19'),(895,22,'Kadurupe','active',1,'2026-09-03 05:45:19'),(896,23,'Kalubovitiyana','active',1,'2026-09-03 05:45:19'),(897,23,'Galabada','active',1,'2026-09-03 05:45:19'),(898,23,'Ambewela','active',1,'2026-09-03 05:45:19'),(899,23,'Dangala West','active',1,'2026-09-03 05:45:19'),(900,23,'Banagala East','active',1,'2026-09-03 05:45:19'),(901,23,'Dangala East','active',1,'2026-09-03 05:45:19'),(902,23,'Banagala West','active',1,'2026-09-03 05:45:19'),(903,23,'Edandukitha West','active',1,'2026-09-03 05:45:19'),(904,23,'Edandukitha East','active',1,'2026-09-03 05:45:19'),(905,23,'Alapaladeniya South','active',1,'2026-09-03 05:45:19'),(906,23,'Alapaladeniya North','active',1,'2026-09-03 05:45:19'),(907,23,'Kiriwelkele North','active',1,'2026-09-03 05:45:19'),(908,23,'Rambukana West','active',1,'2026-09-03 05:45:19'),(909,23,'Kodikaragoda West','active',1,'2026-09-03 05:45:19'),(910,23,'Kodikaragoda East','active',1,'2026-09-03 05:45:19'),(911,23,'Weliwa','active',1,'2026-09-03 05:45:19'),(912,23,'Rambukana East','active',1,'2026-09-03 05:45:19'),(913,23,'Thalapekumbura','active',1,'2026-09-03 05:45:19'),(914,23,'Kudagalahena','active',1,'2026-09-03 05:45:19'),(915,23,'Kiriwelkele South','active',1,'2026-09-03 05:45:19'),(916,23,'Derangala','active',1,'2026-09-03 05:45:19'),(917,23,'Thennahena','active',1,'2026-09-03 05:45:19'),(918,23,'Gorakawela','active',1,'2026-09-03 05:45:19'),(919,23,'Siyambalagoda West','active',1,'2026-09-03 05:45:19'),(920,23,'Kosnilgoda','active',1,'2026-09-03 05:45:19'),(921,23,'Aluwana','active',1,'2026-09-03 05:45:19'),(922,23,'Paradupalla','active',1,'2026-09-03 05:45:19'),(923,23,'Mahapothuvila','active',1,'2026-09-03 05:45:19'),(924,23,'Dankoluwa','active',1,'2026-09-03 05:45:19'),(925,23,'Pitabeddara','active',1,'2026-09-03 05:45:19'),(926,23,'Kaduruwana','active',1,'2026-09-03 05:45:19'),(927,23,'Dehigaspe','active',1,'2026-09-03 05:45:19'),(928,23,'Kotagala','active',1,'2026-09-03 05:45:19'),(929,23,'Siyambalagoda East','active',1,'2026-09-03 05:45:19'),(930,23,'Wanasinkanda','active',1,'2026-09-03 05:45:19'),(931,23,'Diyadawa','active',1,'2026-09-03 05:45:19'),(932,23,'Ihala Ainagama','active',1,'2026-09-03 05:45:19'),(933,23,'Elamaldeniya','active',1,'2026-09-03 05:45:19'),(934,23,'Wathurakumbura','active',1,'2026-09-03 05:45:19'),(935,23,'Puwakbadovita','active',1,'2026-09-03 05:45:19'),(936,24,'Mederipitiya','active',1,'2026-09-03 05:45:19'),(937,24,'Keeriwalagama','active',1,'2026-09-03 05:45:19'),(938,24,'Kiriweldola','active',1,'2026-09-03 05:45:19'),(939,24,'Kandilpana','active',1,'2026-09-03 05:45:19'),(940,24,'Viharahena','active',1,'2026-09-03 05:45:19'),(941,24,'Adaradeniya','active',1,'2026-09-03 05:45:19'),(942,24,'Ihalagama','active',1,'2026-09-03 05:45:19'),(943,24,'Bateyaya','active',1,'2026-09-03 05:45:19'),(944,24,'Pussawela','active',1,'2026-09-03 05:45:19'),(945,24,'Pallegama North','active',1,'2026-09-03 05:45:19'),(946,24,'Pathawala Nadakanda','active',1,'2026-09-03 05:45:19'),(947,24,'Poddana','active',1,'2026-09-03 05:45:19'),(948,24,'Kolawenigama','active',1,'2026-09-03 05:45:19'),(949,24,'Pallegama South','active',1,'2026-09-03 05:45:19'),(950,24,'Deniyaya West','active',1,'2026-09-03 05:45:19'),(951,24,'Deniyaya','active',1,'2026-09-03 05:45:19'),(952,24,'Kalugalahena','active',1,'2026-09-03 05:45:19'),(953,24,'Thenipita','active',1,'2026-09-03 05:45:19'),(954,24,'Mugunumulla','active',1,'2026-09-03 05:45:19'),(955,24,'Nawalahena','active',1,'2026-09-03 05:45:19'),(956,24,'Kotapola North','active',1,'2026-09-03 05:45:19'),(957,24,'Nishshankapura','active',1,'2026-09-03 05:45:19'),(958,24,'Beliattakumbura','active',1,'2026-09-03 05:45:19'),(959,24,'Morawaka','active',1,'2026-09-03 05:45:19'),(960,24,'Porupitiya','active',1,'2026-09-03 05:45:19'),(961,24,'Waralla','active',1,'2026-09-03 05:45:19'),(962,24,'Koodaludeniya','active',1,'2026-09-03 05:45:19'),(963,24,'Kotapola South','active',1,'2026-09-03 05:45:19'),(964,24,'Usamalagoda','active',1,'2026-09-03 05:45:19'),(965,24,'Lindagawahena','active',1,'2026-09-03 05:45:19'),(966,24,'Kosmodara','active',1,'2026-09-03 05:45:19'),(967,24,'Ilukpitiya','active',1,'2026-09-03 05:45:19'),(968,24,'Horagala East','active',1,'2026-09-03 05:45:19'),(969,24,'Horagala West','active',1,'2026-09-03 05:45:19'),(970,24,'Uvaragala','active',1,'2026-09-03 05:45:19'),(971,24,'Pelawatta','active',1,'2026-09-03 05:45:19'),(972,24,'Paragala','active',1,'2026-09-03 05:45:19'),(973,25,'Batandura South','active',1,'2026-09-03 05:45:19'),(974,25,'Batandura North','active',1,'2026-09-03 05:45:19'),(975,25,'Thalapalakanda','active',1,'2026-09-03 05:45:19'),(976,25,'Kekundeniya','active',1,'2026-09-03 05:45:19'),(977,25,'Ketawala','active',1,'2026-09-03 05:45:19'),(978,25,'Ginnaliya North','active',1,'2026-09-03 05:45:19'),(979,25,'Pattigala','active',1,'2026-09-03 05:45:19'),(980,25,'Beralapanathara South','active',1,'2026-09-03 05:45:19'),(981,25,'Beralapanathara North','active',1,'2026-09-03 05:45:19'),(982,25,'Wijayagama','active',1,'2026-09-03 05:45:19'),(983,25,'Pathavita','active',1,'2026-09-03 05:45:19'),(984,25,'Kirilipana','active',1,'2026-09-03 05:45:19'),(985,25,'Moragala','active',1,'2026-09-03 05:45:19'),(986,25,'Keeripitiya East','active',1,'2026-09-03 05:45:19'),(987,25,'Urubokka','active',1,'2026-09-03 05:45:19'),(988,25,'Ginnaliya West','active',1,'2026-09-03 05:45:19'),(989,25,'Ginnaliya South','active',1,'2026-09-03 05:45:19'),(990,25,'Ginnaliya East','active',1,'2026-09-03 05:45:19'),(991,25,'Mekiliyathenna','active',1,'2026-09-03 05:45:19'),(992,25,'Heegoda','active',1,'2026-09-03 05:45:19'),(993,25,'Keeripitiya West','active',1,'2026-09-03 05:45:19'),(994,25,'Pothdeniya','active',1,'2026-09-03 05:45:19'),(995,25,'Mologgamuwa North','active',1,'2026-09-03 05:45:19'),(996,25,'Mologgamuwa South','active',1,'2026-09-03 05:45:19'),(997,25,'Bengamuwa West','active',1,'2026-09-03 05:45:19'),(998,25,'Bengamuwa East','active',1,'2026-09-03 05:45:19'),(999,25,'Dampahala West','active',1,'2026-09-03 05:45:19'),(1000,25,'Hulankanda','active',1,'2026-09-03 05:45:19'),(1001,25,'Dampahala East','active',1,'2026-09-03 05:45:19'),(1002,25,'Pasgoda','active',1,'2026-09-03 05:45:19'),(1003,25,'Bengamuwa South','active',1,'2026-09-03 05:45:19'),(1004,25,'Denkandaliya','active',1,'2026-09-03 05:45:19'),(1005,25,'Panakaduwa West','active',1,'2026-09-03 05:45:19'),(1006,25,'Rotumba East','active',1,'2026-09-03 05:45:19'),(1007,25,'Napathella','active',1,'2026-09-03 05:45:19'),(1008,25,'Andaluwa','active',1,'2026-09-03 05:45:19'),(1009,25,'Ehelakanda','active',1,'2026-09-03 05:45:19'),(1010,25,'Galketikanda','active',1,'2026-09-03 05:45:19'),(1011,25,'Rotumba West','active',1,'2026-09-03 05:45:19'),(1012,25,'Panakaduwa East','active',1,'2026-09-03 05:45:19'),(1013,25,'Gomila','active',1,'2026-09-03 05:45:19'),(1014,25,'Puwakgahahena','active',1,'2026-09-03 05:45:19'),(1015,25,'Mawarala','active',1,'2026-09-03 05:45:19'),(1016,26,'Gombaddala North','active',1,'2026-09-03 05:45:19'),(1017,26,'Kudapana','active',1,'2026-09-03 05:45:19'),(1018,26,'Ketapalakanda','active',1,'2026-09-03 05:45:19'),(1019,26,'Gammedagama','active',1,'2026-09-03 05:45:19'),(1020,26,'Ketiyape North','active',1,'2026-09-03 05:45:19'),(1021,26,'Athapattukanda','active',1,'2026-09-03 05:45:19'),(1022,26,'Parapamulla East','active',1,'2026-09-03 05:45:19'),(1023,26,'Ketiyape South','active',1,'2026-09-03 05:45:19'),(1024,26,'Galetumba','active',1,'2026-09-03 05:45:19'),(1025,26,'Neralampitiya','active',1,'2026-09-03 05:45:19'),(1026,26,'Mulatiyana','active',1,'2026-09-03 05:45:19'),(1027,26,'Diddenipotha North','active',1,'2026-09-03 05:45:19'),(1028,26,'Beragama North','active',1,'2026-09-03 05:45:19'),(1029,26,'Gombaddala South','active',1,'2026-09-03 05:45:19'),(1030,26,'Beragama West','active',1,'2026-09-03 05:45:19'),(1031,26,'Beragama South','active',1,'2026-09-03 05:45:19'),(1032,26,'Makandura West','active',1,'2026-09-03 05:45:19'),(1033,26,'Beragama East','active',1,'2026-09-03 05:45:19'),(1034,26,'Diddenipotha East','active',1,'2026-09-03 05:45:19'),(1035,26,'Diddenipotha South','active',1,'2026-09-03 05:45:19'),(1036,26,'Seenipella West','active',1,'2026-09-03 05:45:19'),(1037,26,'Maduwala','active',1,'2026-09-03 05:45:19'),(1038,26,'Seenipella East','active',1,'2026-09-03 05:45:19'),(1039,26,'Deiyandara','active',1,'2026-09-03 05:45:19'),(1040,26,'Parapamulla West','active',1,'2026-09-03 05:45:19'),(1041,26,'Parapamulla South','active',1,'2026-09-03 05:45:19'),(1042,26,'Dewalegama West','active',1,'2026-09-03 05:45:19'),(1043,26,'Dewalegama East','active',1,'2026-09-03 05:45:19'),(1044,26,'Batadola','active',1,'2026-09-03 05:45:19'),(1045,26,'Kithsiripura','active',1,'2026-09-03 05:45:19'),(1046,26,'Radawela West','active',1,'2026-09-03 05:45:19'),(1047,26,'Belpamulla','active',1,'2026-09-03 05:45:19'),(1048,26,'Bamunugama West','active',1,'2026-09-03 05:45:19'),(1049,26,'Makandura East','active',1,'2026-09-03 05:45:19'),(1050,26,'Ransegoda East','active',1,'2026-09-03 05:45:19'),(1051,26,'Meepavita','active',1,'2026-09-03 05:45:19'),(1052,26,'Mudaligedara','active',1,'2026-09-03 05:45:19'),(1053,26,'Rathkekulawa','active',1,'2026-09-03 05:45:19'),(1054,26,'Ransegoda North','active',1,'2026-09-03 05:45:19'),(1055,26,'Ransegoda South','active',1,'2026-09-03 05:45:19'),(1056,26,'Ransegoda West','active',1,'2026-09-03 05:45:19'),(1057,26,'Koramburuwana','active',1,'2026-09-03 05:45:19'),(1058,26,'Horapavita South','active',1,'2026-09-03 05:45:19'),(1059,26,'Horapavita North','active',1,'2026-09-03 05:45:19'),(1060,26,'Bamunugama East','active',1,'2026-09-03 05:45:19'),(1061,26,'Radawela East','active',1,'2026-09-03 05:45:19'),(1062,26,'Pallawela','active',1,'2026-09-03 05:45:19'),(1063,26,'Pitawalgamuwa','active',1,'2026-09-03 05:45:19'),(1064,27,'Kehelwala','active',1,'2026-09-03 05:45:19'),(1065,27,'Urumutta','active',1,'2026-09-03 05:45:19'),(1066,27,'Urumutta South','active',1,'2026-09-03 05:45:19'),(1067,27,'Dematapassa','active',1,'2026-09-03 05:45:19'),(1068,27,'Divithura','active',1,'2026-09-03 05:45:19'),(1069,27,'Welihena','active',1,'2026-09-03 05:45:19'),(1070,27,'Hawpe','active',1,'2026-09-03 05:45:19'),(1071,27,'Thalahagama West','active',1,'2026-09-03 05:45:19'),(1072,27,'Wenagama','active',1,'2026-09-03 05:45:19'),(1073,27,'Thalahagama East','active',1,'2026-09-03 05:45:19'),(1074,27,'Uggashena','active',1,'2026-09-03 05:45:19'),(1075,27,'Vilpita West','active',1,'2026-09-03 05:45:19'),(1076,27,'Kanahalagama','active',1,'2026-09-03 05:45:19'),(1077,27,'Godapitiya','active',1,'2026-09-03 05:45:19'),(1078,27,'Maragoda','active',1,'2026-09-03 05:45:19'),(1079,27,'Panadugama','active',1,'2026-09-03 05:45:19'),(1080,27,'Thibbotuwawa North','active',1,'2026-09-03 05:45:19'),(1081,27,'Walagepiyadda','active',1,'2026-09-03 05:45:19'),(1082,27,'Vilpita East 1','active',1,'2026-09-03 05:45:19'),(1083,27,'Vilpita East 11','active',1,'2026-09-03 05:45:19'),(1084,27,'Yahamulla','active',1,'2026-09-03 05:45:19'),(1085,27,'Namburukanda','active',1,'2026-09-03 05:45:19'),(1086,27,'Ihala Athuraliya','active',1,'2026-09-03 05:45:19'),(1087,27,'Balakawala','active',1,'2026-09-03 05:45:19'),(1088,27,'Thibbotuwawa','active',1,'2026-09-03 05:45:19'),(1089,27,'Athuraliya West','active',1,'2026-09-03 05:45:19'),(1090,27,'Athuraliya East','active',1,'2026-09-03 05:45:19'),(1091,27,'Pahala Athuraliya','active',1,'2026-09-03 05:45:19'),(1092,28,'Diganahena','active',1,'2026-09-03 05:45:19'),(1093,28,'Lenama North','active',1,'2026-09-03 05:45:19'),(1094,28,'Hulandawa','active',1,'2026-09-03 05:45:19'),(1095,28,'Maramba North','active',1,'2026-09-03 05:45:19'),(1096,28,'Peddapitiya North','active',1,'2026-09-03 05:45:19'),(1097,28,'Idikatudeniya','active',1,'2026-09-03 05:45:19'),(1098,28,'Lenama South','active',1,'2026-09-03 05:45:19'),(1099,28,'Udupitiya','active',1,'2026-09-03 05:45:19'),(1100,28,'Weliketiya','active',1,'2026-09-03 05:45:19'),(1101,28,'Dediyagala','active',1,'2026-09-03 05:45:19'),(1102,28,'Vilagama','active',1,'2026-09-03 05:45:19'),(1103,28,'Ehelape','active',1,'2026-09-03 05:45:19'),(1104,28,'Dolamawatha','active',1,'2026-09-03 05:45:19'),(1105,28,'Kohugoda','active',1,'2026-09-03 05:45:19'),(1106,28,'Ihala Maliduwa','active',1,'2026-09-03 05:45:19'),(1107,28,'Ketanvila','active',1,'2026-09-03 05:45:19'),(1108,28,'Nawalagoda','active',1,'2026-09-03 05:45:19'),(1109,28,'Ellewela','active',1,'2026-09-03 05:45:19'),(1110,28,'Minipogoda','active',1,'2026-09-03 05:45:19'),(1111,28,'Peddapitiya South','active',1,'2026-09-03 05:45:19'),(1112,28,'Maramba South','active',1,'2026-09-03 05:45:19'),(1113,28,'Ganhela','active',1,'2026-09-03 05:45:19'),(1114,28,'Asmagoda','active',1,'2026-09-03 05:45:19'),(1115,28,'Higgoda','active',1,'2026-09-03 05:45:19'),(1116,28,'Diyalape','active',1,'2026-09-03 05:45:19'),(1117,28,'Eramudugoda','active',1,'2026-09-03 05:45:19'),(1118,28,'Manikgoda','active',1,'2026-09-03 05:45:19'),(1119,28,'Pahala Maliduwa','active',1,'2026-09-03 05:45:19'),(1120,28,'Bopitiya','active',1,'2026-09-03 05:45:19'),(1121,28,'Iluppella','active',1,'2026-09-03 05:45:19'),(1122,28,'Imbulgoda','active',1,'2026-09-03 05:45:19'),(1123,28,'Poramba','active',1,'2026-09-03 05:45:19'),(1124,28,'Akuressa','active',1,'2026-09-03 05:45:19'),(1125,28,'Yakabedda','active',1,'2026-09-03 05:45:19'),(1126,28,'Galabadahena','active',1,'2026-09-03 05:45:19'),(1127,28,'Ihala Kiyanduwa','active',1,'2026-09-03 05:45:19'),(1128,28,'Melewwa','active',1,'2026-09-03 05:45:19'),(1129,28,'Paraduwa North','active',1,'2026-09-03 05:45:19'),(1130,28,'Paraduwa East','active',1,'2026-09-03 05:45:19'),(1131,28,'Paraduwa South','active',1,'2026-09-03 05:45:19'),(1132,28,'Henegama','active',1,'2026-09-03 05:45:19'),(1133,28,'Henegama West','active',1,'2026-09-03 05:45:19'),(1134,28,'Gallala','active',1,'2026-09-03 05:45:19'),(1135,28,'Nimalawa East','active',1,'2026-09-03 05:45:19'),(1136,28,'Nimalawa','active',1,'2026-09-03 05:45:19'),(1137,28,'Paragahawatta','active',1,'2026-09-03 05:45:19'),(1138,29,'Wahala Kananke North','active',1,'2026-09-03 05:45:19'),(1139,29,'Nivithiwelbokka','active',1,'2026-09-03 05:45:19'),(1140,29,'Poramba Kananke North','active',1,'2026-09-03 05:45:19'),(1141,29,'Puhulahena','active',1,'2026-09-03 05:45:19'),(1142,29,'Poramba Kananke South','active',1,'2026-09-03 05:45:19'),(1143,29,'Wahala Kananke South','active',1,'2026-09-03 05:45:19'),(1144,29,'Nalawana','active',1,'2026-09-03 05:45:19'),(1145,29,'Penetiyana West','active',1,'2026-09-03 05:45:19'),(1146,29,'Penetiyana East','active',1,'2026-09-03 05:45:19'),(1147,29,'Wellana','active',1,'2026-09-03 05:45:19'),(1148,29,'Udukawa North','active',1,'2026-09-03 05:45:19'),(1149,29,'Udukawa South','active',1,'2026-09-03 05:45:19'),(1150,29,'Hallala','active',1,'2026-09-03 05:45:19'),(1151,29,'Bathalahena','active',1,'2026-09-03 05:45:19'),(1152,29,'Kokmaduwa North','active',1,'2026-09-03 05:45:19'),(1153,29,'Sahabandu Kokmaduwa','active',1,'2026-09-03 05:45:19'),(1154,29,'Jamburegoda East','active',1,'2026-09-03 05:45:19'),(1155,29,'Padili Kokmaduwa','active',1,'2026-09-03 05:45:19'),(1156,29,'Beraleliya','active',1,'2026-09-03 05:45:19'),(1157,29,'Jayawickramapura','active',1,'2026-09-03 05:45:19'),(1158,29,'Warakapitiya North','active',1,'2026-09-03 05:45:19'),(1159,29,'Warakapitiya East','active',1,'2026-09-03 05:45:19'),(1160,29,'Warakapitiya South','active',1,'2026-09-03 05:45:19'),(1161,29,'Meeruppa','active',1,'2026-09-03 05:45:19'),(1162,29,'Uruvitiya','active',1,'2026-09-03 05:45:19'),(1163,29,'Watagedaramulla','active',1,'2026-09-03 05:45:19'),(1164,29,'Moonamalpe','active',1,'2026-09-03 05:45:19'),(1165,29,'Welipitiya','active',1,'2026-09-03 05:45:19'),(1166,29,'Palalla','active',1,'2026-09-03 05:45:19'),(1167,29,'Jamburegoda West','active',1,'2026-09-03 05:45:19'),(1168,29,'Ibbawala','active',1,'2026-09-03 05:45:19'),(1169,29,'Vilegoda','active',1,'2026-09-03 05:45:19'),(1170,29,'Borala','active',1,'2026-09-03 05:45:19'),(1171,29,'Maduragoda','active',1,'2026-09-03 05:45:19'),(1172,29,'Kapuwatta','active',1,'2026-09-03 05:45:19'),(1173,29,'Denipitiya Central','active',1,'2026-09-03 05:45:19'),(1174,29,'Denipitiya West','active',1,'2026-09-03 05:45:19'),(1175,29,'Denipitiya East','active',1,'2026-09-03 05:45:19'),(1176,30,'Elgiriya','active',1,'2026-09-03 05:45:19'),(1177,30,'Pahala Kiyaduwa','active',1,'2026-09-03 05:45:19'),(1178,30,'Maragoda','active',1,'2026-09-03 05:45:19'),(1179,30,'Kekunawela','active',1,'2026-09-03 05:45:19'),(1180,30,'Kadduwa','active',1,'2026-09-03 05:45:19'),(1181,30,'Dampella','active',1,'2026-09-03 05:45:19'),(1182,30,'Horagoda East','active',1,'2026-09-03 05:45:19'),(1183,30,'Horagoda West','active',1,'2026-09-03 05:45:19'),(1184,30,'Horagoda South','active',1,'2026-09-03 05:45:19'),(1185,30,'Kadukanna','active',1,'2026-09-03 05:45:19'),(1186,30,'Welandagoda','active',1,'2026-09-03 05:45:19'),(1187,30,'Uninduwela','active',1,'2026-09-03 05:45:19'),(1188,30,'Thelijjavila','active',1,'2026-09-03 05:45:19'),(1189,30,'Nape','active',1,'2026-09-03 05:45:19'),(1190,30,'Akurugoda North','active',1,'2026-09-03 05:45:19'),(1191,30,'Akurugoda East','active',1,'2026-09-03 05:45:19'),(1192,30,'Kirimetimulla South','active',1,'2026-09-03 05:45:19'),(1193,30,'Kirimetimulla North','active',1,'2026-09-03 05:45:19'),(1194,30,'Malimbada West','active',1,'2026-09-03 05:45:19'),(1195,30,'Katuwangoda','active',1,'2026-09-03 05:45:19'),(1196,30,'Malimbada East','active',1,'2026-09-03 05:45:19'),(1197,30,'Malimbada North','active',1,'2026-09-03 05:45:19'),(1198,30,'Malimbada South','active',1,'2026-09-03 05:45:19'),(1199,30,'Galpamuna','active',1,'2026-09-03 05:45:19'),(1200,30,'Akurugoda South','active',1,'2026-09-03 05:45:19'),(1201,30,'Akurugoda West','active',1,'2026-09-03 05:45:19'),(1202,30,'Sulthanagoda West','active',1,'2026-09-03 05:45:19'),(1203,30,'Sulthanagoda East','active',1,'2026-09-03 05:45:19'),(1204,30,'Sulthanagoda South','active',1,'2026-09-03 05:45:19'),(1205,31,'Gathara West','active',1,'2026-09-03 05:45:19'),(1206,31,'Gathara North','active',1,'2026-09-03 05:45:19'),(1207,31,'Gathara East','active',1,'2026-09-03 05:45:19'),(1208,31,'Beragammulla','active',1,'2026-09-03 05:45:19'),(1209,31,'Eriyathota','active',1,'2026-09-03 05:45:19'),(1210,31,'Ganegama','active',1,'2026-09-03 05:45:19'),(1211,31,'Narandeniya East','active',1,'2026-09-03 05:45:19'),(1212,31,'Narandeniya West','active',1,'2026-09-03 05:45:19'),(1213,31,'Malana','active',1,'2026-09-03 05:45:19'),(1214,31,'Magamure','active',1,'2026-09-03 05:45:19'),(1215,31,'Sapugoda','active',1,'2026-09-03 05:45:19'),(1216,31,'Lenabatuwa','active',1,'2026-09-03 05:45:19'),(1217,31,'Bibulewela','active',1,'2026-09-03 05:45:19'),(1218,31,'Godawa','active',1,'2026-09-03 05:45:19'),(1219,31,'Pitakatuwana','active',1,'2026-09-03 05:45:19'),(1220,31,'Kamburupitiya','active',1,'2026-09-03 05:45:19'),(1221,31,'Ullala East','active',1,'2026-09-03 05:45:19'),(1222,31,'Thumbe','active',1,'2026-09-03 05:45:19'),(1223,31,'Karaputugala North','active',1,'2026-09-03 05:45:19'),(1224,31,'Karaputugala South','active',1,'2026-09-03 05:45:19'),(1225,31,'Ullala Masmulla','active',1,'2026-09-03 05:45:19'),(1226,31,'Ullala West','active',1,'2026-09-03 05:45:19'),(1227,31,'Mapalana Mangin Ihala','active',1,'2026-09-03 05:45:19'),(1228,31,'Mapalana Mangin Pahala','active',1,'2026-09-03 05:45:19'),(1229,31,'Seewelgama','active',1,'2026-09-03 05:45:19'),(1230,31,'Ihala Vitiyala North','active',1,'2026-09-03 05:45:19'),(1231,31,'Karagoda Uyangoda 1 Atha East','active',1,'2026-09-03 05:45:19'),(1232,31,'Karagoda Uyangoda 1 Atha West','active',1,'2026-09-03 05:45:19'),(1233,31,'Karagoda Uyangoda 2 West','active',1,'2026-09-03 05:45:19'),(1234,31,'Karagoda Uyangoda 2 East','active',1,'2026-09-03 05:45:19'),(1235,31,'Ihala Vitiyala West','active',1,'2026-09-03 05:45:19'),(1236,31,'Ihala Vitiyala South','active',1,'2026-09-03 05:45:19'),(1237,31,'Ihala Vitiyala East','active',1,'2026-09-03 05:45:19'),(1238,31,'Akurugoda','active',1,'2026-09-03 05:45:19'),(1239,31,'Kahagala','active',1,'2026-09-03 05:45:19'),(1240,31,'Kahagala South','active',1,'2026-09-03 05:45:19'),(1241,31,'Urapola East','active',1,'2026-09-03 05:45:19'),(1242,31,'Palolpitiya','active',1,'2026-09-03 05:45:19'),(1243,31,'Urapola West','active',1,'2026-09-03 05:45:19'),(1244,32,'Denagama East','active',1,'2026-09-03 05:45:19'),(1245,32,'Denagama North','active',1,'2026-09-03 05:45:19'),(1246,32,'Denagama West','active',1,'2026-09-03 05:45:19'),(1247,32,'Badabadda','active',1,'2026-09-03 05:45:19'),(1248,32,'Meeella','active',1,'2026-09-03 05:45:19'),(1249,32,'Pananwela East','active',1,'2026-09-03 05:45:19'),(1250,32,'Pananwela West','active',1,'2026-09-03 05:45:19'),(1251,32,'Kohuliyadda','active',1,'2026-09-03 05:45:19'),(1252,32,'Kebiliyapola North','active',1,'2026-09-03 05:45:19'),(1253,32,'Wepathaira North','active',1,'2026-09-03 05:45:19'),(1254,32,'Wepathaira West','active',1,'2026-09-03 05:45:19'),(1255,32,'Kandegoda','active',1,'2026-09-03 05:45:19'),(1256,32,'Narawelpita East','active',1,'2026-09-03 05:45:19'),(1257,32,'Narawelpita North','active',1,'2026-09-03 05:45:19'),(1258,32,'Ellewela East','active',1,'2026-09-03 05:45:19'),(1259,32,'Ellewela West','active',1,'2026-09-03 05:45:19'),(1260,32,'Narawelpita South','active',1,'2026-09-03 05:45:19'),(1261,32,'Beruwela','active',1,'2026-09-03 05:45:19'),(1262,32,'Muruthamuraya','active',1,'2026-09-03 05:45:19'),(1263,32,'Muruthamuraya West','active',1,'2026-09-03 05:45:19'),(1264,32,'Muruthamuraya East','active',1,'2026-09-03 05:45:19'),(1265,32,'Wepathaira South','active',1,'2026-09-03 05:45:19'),(1266,32,'Kebiliyapola South','active',1,'2026-09-03 05:45:19'),(1267,32,'Gangodagama','active',1,'2026-09-03 05:45:19'),(1268,32,'Pottewela','active',1,'2026-09-03 05:45:19'),(1269,32,'Lalpe','active',1,'2026-09-03 05:45:19'),(1270,32,'Gammedapitiya','active',1,'2026-09-03 05:45:19'),(1271,32,'Udupeellegoda East','active',1,'2026-09-03 05:45:19'),(1272,32,'Udupeellegoda West','active',1,'2026-09-03 05:45:19'),(1273,32,'Kongala East','active',1,'2026-09-03 05:45:19'),(1274,32,'Kongala South','active',1,'2026-09-03 05:45:19'),(1275,32,'Kongala Central','active',1,'2026-09-03 05:45:19'),(1276,32,'Kongala West','active',1,'2026-09-03 05:45:19'),(1277,32,'Narawelpita West','active',1,'2026-09-03 05:45:19'),(1278,33,'Ovitigamuwa South','active',1,'2026-09-03 05:45:19'),(1279,33,'Ovitigamuwa North','active',1,'2026-09-03 05:45:19'),(1280,33,'Malwathugoda','active',1,'2026-09-03 05:45:19'),(1281,33,'Galkanda','active',1,'2026-09-03 05:45:19'),(1282,33,'Kirinda Mangin Pahala','active',1,'2026-09-03 05:45:19'),(1283,33,'Kirinda Mangin Ihala North','active',1,'2026-09-03 05:45:19'),(1284,33,'Hettiyawala West','active',1,'2026-09-03 05:45:19'),(1285,33,'Hettiyawala North','active',1,'2026-09-03 05:45:19'),(1286,33,'Boraluketiya','active',1,'2026-09-03 05:45:19'),(1287,33,'Karathota','active',1,'2026-09-03 05:45:19'),(1288,33,'Naradda','active',1,'2026-09-03 05:45:19'),(1289,33,'Kumbalgoda','active',1,'2026-09-03 05:45:19'),(1290,33,'Hettiyawala East','active',1,'2026-09-03 05:45:19'),(1291,33,'Hettiyawala South','active',1,'2026-09-03 05:45:19'),(1292,33,'Wavulanbokka','active',1,'2026-09-03 05:45:19'),(1293,33,'Puhulwella East','active',1,'2026-09-03 05:45:19'),(1294,33,'Puhulwella West','active',1,'2026-09-03 05:45:19'),(1295,33,'Kirinda Mangin Ihala South','active',1,'2026-09-03 05:45:19'),(1296,33,'Kirinda Mangin Ihala Central','active',1,'2026-09-03 05:45:19'),(1297,33,'Kirinda Mangin Ihala East','active',1,'2026-09-03 05:45:19'),(1298,33,'Wathukolakanda East','active',1,'2026-09-03 05:45:19'),(1299,33,'Walakanda South','active',1,'2026-09-03 05:45:19'),(1300,33,'Walakanda West','active',1,'2026-09-03 05:45:19'),(1301,33,'Walakanda East','active',1,'2026-09-03 05:45:19'),(1302,33,'Wathukolakanda North','active',1,'2026-09-03 05:45:19'),(1303,34,'Pahala Vitiyala West','active',1,'2026-09-03 05:45:19'),(1304,34,'Pahala Vitiyala Central','active',1,'2026-09-03 05:45:19'),(1305,34,'Pahala Vitiyala East','active',1,'2026-09-03 05:45:19'),(1306,34,'Polathugoda','active',1,'2026-09-03 05:45:19'),(1307,34,'Batuvita 1','active',1,'2026-09-03 05:45:19'),(1308,34,'Batuvita 2','active',1,'2026-09-03 05:45:19'),(1309,34,'Medauyangoda','active',1,'2026-09-03 05:45:19'),(1310,34,'Akkara Panaha','active',1,'2026-09-03 05:45:19'),(1311,34,'Yatiyana','active',1,'2026-09-03 05:45:19'),(1312,34,'Kottawatta','active',1,'2026-09-03 05:45:19'),(1313,34,'Komangoda 2','active',1,'2026-09-03 05:45:19'),(1314,34,'Komangoda 1','active',1,'2026-09-03 05:45:19'),(1315,34,'Thihagoda East','active',1,'2026-09-03 05:45:19'),(1316,34,'Thihagoda','active',1,'2026-09-03 05:45:19'),(1317,34,'Kithalagama East 2','active',1,'2026-09-03 05:45:19'),(1318,34,'Kithalagama East 3','active',1,'2026-09-03 05:45:19'),(1319,34,'Kithalagama Central','active',1,'2026-09-03 05:45:19'),(1320,34,'Narangala','active',1,'2026-09-03 05:45:19'),(1321,34,'Wellethota','active',1,'2026-09-03 05:45:19'),(1322,34,'Kithalagama West','active',1,'2026-09-03 05:45:19'),(1323,34,'Kithalagama East 1','active',1,'2026-09-03 05:45:19'),(1324,34,'Naimbala 1','active',1,'2026-09-03 05:45:19'),(1325,34,'Kapudoowa','active',1,'2026-09-03 05:45:19'),(1326,34,'Kapudoowa East','active',1,'2026-09-03 05:45:19'),(1327,34,'Uduwa West','active',1,'2026-09-03 05:45:19'),(1328,34,'Uduwa East','active',1,'2026-09-03 05:45:19'),(1329,34,'Galbada','active',1,'2026-09-03 05:45:19'),(1330,34,'Naimbala 2','active',1,'2026-09-03 05:45:19'),(1331,34,'Bandattara 2','active',1,'2026-09-03 05:45:19'),(1332,34,'Watagedara East','active',1,'2026-09-03 05:45:19'),(1333,34,'Attudawa','active',1,'2026-09-03 05:45:19'),(1334,34,'Attudawa West','active',1,'2026-09-03 05:45:19'),(1335,34,'Watagedara','active',1,'2026-09-03 05:45:19'),(1336,34,'Nadugala 2','active',1,'2026-09-03 05:45:19'),(1337,34,'Nadugala 1','active',1,'2026-09-03 05:45:19'),(1338,34,'Bandattara 1','active',1,'2026-09-03 05:45:19'),(1339,34,'Elambathalagoda','active',1,'2026-09-03 05:45:19'),(1340,34,'Palatuwa','active',1,'2026-09-03 05:45:19'),(1341,34,'Unella','active',1,'2026-09-03 05:45:19'),(1342,34,'Dematahettigoda','active',1,'2026-09-03 05:45:19'),(1343,35,'Midigama North','active',1,'2026-09-03 05:45:19'),(1344,35,'Pathegama','active',1,'2026-09-03 05:45:19'),(1345,35,'Moodugamuwa West','active',1,'2026-09-03 05:45:19'),(1346,35,'Moodugamuwa East','active',1,'2026-09-03 05:45:19'),(1347,35,'Kohunugamuwa','active',1,'2026-09-03 05:45:19'),(1348,35,'Walana','active',1,'2026-09-03 05:45:19'),(1349,35,'Wekada','active',1,'2026-09-03 05:45:19'),(1350,35,'Denuwala','active',1,'2026-09-03 05:45:19'),(1351,35,'Midigama West','active',1,'2026-09-03 05:45:19'),(1352,35,'Midigama East','active',1,'2026-09-03 05:45:19'),(1353,35,'Hetti Weediya','active',1,'2026-09-03 05:45:19'),(1354,35,'Aluth Weediya','active',1,'2026-09-03 05:45:19'),(1355,35,'Polwatta','active',1,'2026-09-03 05:45:19'),(1356,35,'Nidangala','active',1,'2026-09-03 05:45:19'),(1357,35,'Kotavila West','active',1,'2026-09-03 05:45:19'),(1358,35,'Kotavila North','active',1,'2026-09-03 05:45:19'),(1359,35,'Kamburugamuwa North','active',1,'2026-09-03 05:45:19'),(1360,35,'Kotavila South','active',1,'2026-09-03 05:45:19'),(1361,35,'Henwala East','active',1,'2026-09-03 05:45:19'),(1362,35,'Polwathumodara','active',1,'2026-09-03 05:45:19'),(1363,35,'Pelena North','active',1,'2026-09-03 05:45:19'),(1364,35,'Pelena West','active',1,'2026-09-03 05:45:19'),(1365,35,'Galbokka East','active',1,'2026-09-03 05:45:19'),(1366,35,'Galbokka West','active',1,'2026-09-03 05:45:19'),(1367,35,'Paranakade','active',1,'2026-09-03 05:45:19'),(1368,35,'Walliwala East','active',1,'2026-09-03 05:45:19'),(1369,35,'Pitidoowa','active',1,'2026-09-03 05:45:19'),(1370,35,'Gurubebila','active',1,'2026-09-03 05:45:19'),(1371,35,'Walliwala West','active',1,'2026-09-03 05:45:19'),(1372,35,'Walliwala South','active',1,'2026-09-03 05:45:19'),(1373,35,'Maha Weediya','active',1,'2026-09-03 05:45:19'),(1374,35,'Pelena South','active',1,'2026-09-03 05:45:19'),(1375,35,'Henwala West','active',1,'2026-09-03 05:45:19'),(1376,35,'Garanduwa','active',1,'2026-09-03 05:45:19'),(1377,35,'Mirissa Udumulla','active',1,'2026-09-03 05:45:19'),(1378,35,'Mirissa Udupila','active',1,'2026-09-03 05:45:19'),(1379,35,'Mirissa North','active',1,'2026-09-03 05:45:19'),(1380,35,'Mirissa South 2','active',1,'2026-09-03 05:45:19'),(1381,35,'Kapparathota South','active',1,'2026-09-03 05:45:19'),(1382,35,'Kapparathota North','active',1,'2026-09-03 05:45:19'),(1383,35,'Mirissa South 1','active',1,'2026-09-03 05:45:19'),(1384,35,'Bandaramulla','active',1,'2026-09-03 05:45:19'),(1385,35,'Thal Aramba North','active',1,'2026-09-03 05:45:19'),(1386,35,'Thudella','active',1,'2026-09-03 05:45:19'),(1387,35,'Kamburugamuwa South','active',1,'2026-09-03 05:45:19'),(1388,35,'Kamburugamuwa West','active',1,'2026-09-03 05:45:19'),(1389,35,'Thal Aramba East','active',1,'2026-09-03 05:45:19'),(1390,35,'Thal Aramba South','active',1,'2026-09-03 05:45:19'),(1391,36,'Deeyagaha East','active',1,'2026-09-03 05:45:19'),(1392,36,'Kekanadura East','active',1,'2026-09-03 05:45:19'),(1393,36,'Kokawala','active',1,'2026-09-03 05:45:19'),(1394,36,'Parawahera East','active',1,'2026-09-03 05:45:19'),(1395,36,'Parawahera North','active',1,'2026-09-03 05:45:19'),(1396,36,'Kekanadura North','active',1,'2026-09-03 05:45:19'),(1397,36,'Kekanadura Central','active',1,'2026-09-03 05:45:19'),(1398,36,'Deeyagaha West','active',1,'2026-09-03 05:45:19'),(1399,36,'Navimana North','active',1,'2026-09-03 05:45:19'),(1400,36,'Pahalagoda','active',1,'2026-09-03 05:45:19'),(1401,36,'Navimana South','active',1,'2026-09-03 05:45:19'),(1402,36,'Thudawa East','active',1,'2026-09-03 05:45:19'),(1403,36,'Thudawa North','active',1,'2026-09-03 05:45:19'),(1404,36,'Thudawa South','active',1,'2026-09-03 05:45:19'),(1405,36,'Sudarshi Place','active',1,'2026-09-03 05:45:19'),(1406,36,'Hittatiya East','active',1,'2026-09-03 05:45:19'),(1407,36,'Hittatiya Meda','active',1,'2026-09-03 05:45:20'),(1408,36,'Godagama','active',1,'2026-09-03 05:45:20'),(1409,36,'Eduwa - Madurudoowa','active',1,'2026-09-03 05:45:20'),(1410,36,'Kanattagoda North','active',1,'2026-09-03 05:45:20'),(1411,36,'Wewahamandoowa','active',1,'2026-09-03 05:45:20'),(1412,36,'Hittatiya West','active',1,'2026-09-03 05:45:20'),(1413,36,'Isadeen Town','active',1,'2026-09-03 05:45:20'),(1414,36,'Weliweriya West','active',1,'2026-09-03 05:45:20'),(1415,36,'Weliweriya East','active',1,'2026-09-03 05:45:20'),(1416,36,'Walpala','active',1,'2026-09-03 05:45:20'),(1417,36,'Weragampita','active',1,'2026-09-03 05:45:20'),(1418,36,'Ruwan Ella','active',1,'2026-09-03 05:45:20'),(1419,36,'Kekanadura West','active',1,'2026-09-03 05:45:20'),(1420,36,'Kekanadura South','active',1,'2026-09-03 05:45:20'),(1421,36,'Parawahera South','active',1,'2026-09-03 05:45:20'),(1422,36,'Thalpavila North','active',1,'2026-09-03 05:45:20'),(1423,36,'Nakuttiya','active',1,'2026-09-03 05:45:20'),(1424,36,'Veherahena','active',1,'2026-09-03 05:45:20'),(1425,36,'Makavita','active',1,'2026-09-03 05:45:20'),(1426,36,'Weradoowa','active',1,'2026-09-03 05:45:20'),(1427,36,'Uyanwatta','active',1,'2026-09-03 05:45:20'),(1428,36,'Uyanwatta North','active',1,'2026-09-03 05:45:20'),(1429,36,'Kadeweediya East','active',1,'2026-09-03 05:45:20'),(1430,36,'Kadeweediya West','active',1,'2026-09-03 05:45:20'),(1431,36,'Welegoda East','active',1,'2026-09-03 05:45:20'),(1432,36,'Welegoda West','active',1,'2026-09-03 05:45:20'),(1433,36,'Mathotagama','active',1,'2026-09-03 05:45:20'),(1434,36,'Walgama North','active',1,'2026-09-03 05:45:20'),(1435,36,'Kanattegoda South','active',1,'2026-09-03 05:45:20'),(1436,36,'Madiha West','active',1,'2026-09-03 05:45:20'),(1437,36,'Walgama','active',1,'2026-09-03 05:45:20'),(1438,36,'Walgama Meda','active',1,'2026-09-03 05:45:20'),(1439,36,'Walgama South','active',1,'2026-09-03 05:45:20'),(1440,36,'Madiha East','active',1,'2026-09-03 05:45:20'),(1441,36,'Polhena','active',1,'2026-09-03 05:45:20'),(1442,36,'Pamburana','active',1,'2026-09-03 05:45:20'),(1443,36,'Noope','active',1,'2026-09-03 05:45:20'),(1444,36,'Kadeweediya South','active',1,'2026-09-03 05:45:20'),(1445,36,'Thotamuna','active',1,'2026-09-03 05:45:20'),(1446,36,'Fort','active',1,'2026-09-03 05:45:20'),(1447,36,'Kotuwegoda North','active',1,'2026-09-03 05:45:20'),(1448,36,'Eliyakanda North','active',1,'2026-09-03 05:45:20'),(1449,36,'Meddawatta','active',1,'2026-09-03 05:45:20'),(1450,36,'Rassandeniya','active',1,'2026-09-03 05:45:20'),(1451,36,'Wewa Ihalagoda','active',1,'2026-09-03 05:45:20'),(1452,36,'Thalpavila South','active',1,'2026-09-03 05:45:20'),(1453,36,'Gandarawatta','active',1,'2026-09-03 05:45:20'),(1454,36,'Meddawatta South','active',1,'2026-09-03 05:45:20'),(1455,36,'Eliyakanda South','active',1,'2026-09-03 05:45:20'),(1456,36,'Kotuwegoda South','active',1,'2026-09-03 05:45:20'),(1457,37,'Kadawedduwa West','active',1,'2026-09-03 05:45:20'),(1458,37,'Kadawedduwa East','active',1,'2026-09-03 05:45:20'),(1459,37,'Walbulugahahena','active',1,'2026-09-03 05:45:20'),(1460,37,'Aparekka North','active',1,'2026-09-03 05:45:20'),(1461,37,'Uda Aparekka East','active',1,'2026-09-03 05:45:20'),(1462,37,'Uda Aparekka','active',1,'2026-09-03 05:45:20'),(1463,37,'Palle Aparekka','active',1,'2026-09-03 05:45:20'),(1464,37,'Agarawala','active',1,'2026-09-03 05:45:20'),(1465,37,'Beddegammedda','active',1,'2026-09-03 05:45:20'),(1466,37,'Pathegama East','active',1,'2026-09-03 05:45:20'),(1467,37,'Pathegama North','active',1,'2026-09-03 05:45:20'),(1468,37,'Naotunna','active',1,'2026-09-03 05:45:20'),(1469,37,'Naotunna North','active',1,'2026-09-03 05:45:20'),(1470,37,'Thalalla North','active',1,'2026-09-03 05:45:20'),(1471,37,'Thalalla East','active',1,'2026-09-03 05:45:20'),(1472,37,'Thalalla South','active',1,'2026-09-03 05:45:20'),(1473,37,'Naotunna Central','active',1,'2026-09-03 05:45:20'),(1474,37,'Naotunna South','active',1,'2026-09-03 05:45:20'),(1475,37,'Thalalla Central','active',1,'2026-09-03 05:45:20'),(1476,37,'Delgalla','active',1,'2026-09-03 05:45:20'),(1477,37,'Kapugama North','active',1,'2026-09-03 05:45:20'),(1478,37,'Kapugama West','active',1,'2026-09-03 05:45:20'),(1479,37,'Gandarawatta South','active',1,'2026-09-03 05:45:20'),(1480,37,'Kapugama Central','active',1,'2026-09-03 05:45:20'),(1481,37,'Kapugama East','active',1,'2026-09-03 05:45:20'),(1482,37,'Gandara East','active',1,'2026-09-03 05:45:20'),(1483,37,'Thalalla','active',1,'2026-09-03 05:45:20'),(1484,37,'Gandara South','active',1,'2026-09-03 05:45:20'),(1485,37,'Gandara Central','active',1,'2026-09-03 05:45:20'),(1486,37,'Gandara West','active',1,'2026-09-03 05:45:20'),(1487,37,'Devinuwara North','active',1,'2026-09-03 05:45:20'),(1488,37,'Devinuwara Central','active',1,'2026-09-03 05:45:20'),(1489,37,'Devinuwara Nugegoda','active',1,'2026-09-03 05:45:20'),(1490,37,'Devinuwara West','active',1,'2026-09-03 05:45:20'),(1491,37,'Devinuwara East','active',1,'2026-09-03 05:45:20'),(1492,37,'Devinuwara Wawwa','active',1,'2026-09-03 05:45:20'),(1493,37,'Devinuwara Pradeepagara Pedesa','active',1,'2026-09-03 05:45:20'),(1494,37,'Devinuwara','active',1,'2026-09-03 05:45:20'),(1495,37,'Devinuwara Welegoda','active',1,'2026-09-03 05:45:20'),(1496,37,'Devinuwara Sinhasana Pedesa','active',1,'2026-09-03 05:45:20'),(1497,37,'Devinuwara South','active',1,'2026-09-03 05:45:20'),(1498,38,'Urugamuwa North','active',1,'2026-09-03 05:45:20'),(1499,38,'Bodarakanda','active',1,'2026-09-03 05:45:20'),(1500,38,'Urugamuwa East','active',1,'2026-09-03 05:45:20'),(1501,38,'Urugamuwa Central','active',1,'2026-09-03 05:45:20'),(1502,38,'Urugamuwa West','active',1,'2026-09-03 05:45:20'),(1503,38,'Dandeniya North','active',1,'2026-09-03 05:45:20'),(1504,38,'Urugamuwa South','active',1,'2026-09-03 05:45:20'),(1505,38,'Rannawala','active',1,'2026-09-03 05:45:20'),(1506,38,'Urugamuwa','active',1,'2026-09-03 05:45:20'),(1507,38,'Wehella','active',1,'2026-09-03 05:45:20'),(1508,38,'Wehella North','active',1,'2026-09-03 05:45:20'),(1509,38,'Dandeniya South','active',1,'2026-09-03 05:45:20'),(1510,38,'Bambarenda North','active',1,'2026-09-03 05:45:20'),(1511,38,'Pohosathugoda','active',1,'2026-09-03 05:45:20'),(1512,38,'Rathmale','active',1,'2026-09-03 05:45:20'),(1513,38,'Bambarenda Central','active',1,'2026-09-03 05:45:20'),(1514,38,'Wehella South','active',1,'2026-09-03 05:45:20'),(1515,38,'Wattegama','active',1,'2026-09-03 05:45:20'),(1516,38,'Wattegama North','active',1,'2026-09-03 05:45:20'),(1517,38,'Walasgala West','active',1,'2026-09-03 05:45:20'),(1518,38,'Walasgala East','active',1,'2026-09-03 05:45:20'),(1519,38,'Wevurukannala','active',1,'2026-09-03 05:45:20'),(1520,38,'Dickwella North','active',1,'2026-09-03 05:45:20'),(1521,38,'Dodampahala North','active',1,'2026-09-03 05:45:20'),(1522,38,'Dodampahala East','active',1,'2026-09-03 05:45:20'),(1523,38,'Dodampahala Central','active',1,'2026-09-03 05:45:20'),(1524,38,'Dodampahala West','active',1,'2026-09-03 05:45:20'),(1525,38,'Dodampahala South','active',1,'2026-09-03 05:45:20'),(1526,38,'Dickwella East','active',1,'2026-09-03 05:45:20'),(1527,38,'Dickwella Central','active',1,'2026-09-03 05:45:20'),(1528,38,'Dickwella Muslim Yonakapura East','active',1,'2026-09-03 05:45:20'),(1529,38,'Dickwella Muslim Yonakapura West','active',1,'2026-09-03 05:45:20'),(1530,38,'Dickwella South','active',1,'2026-09-03 05:45:20'),(1531,38,'Wattegama South','active',1,'2026-09-03 05:45:20'),(1532,38,'Batheegama East','active',1,'2026-09-03 05:45:20'),(1533,38,'Batheegama Central','active',1,'2026-09-03 05:45:20'),(1534,38,'Batheegama West','active',1,'2026-09-03 05:45:20'),(1535,38,'Bambarenda East','active',1,'2026-09-03 05:45:20'),(1536,38,'Bambarenda South','active',1,'2026-09-03 05:45:20'),(1537,38,'Bambarenda West','active',1,'2026-09-03 05:45:20'),(1538,38,'Belideniya','active',1,'2026-09-03 05:45:20'),(1539,38,'Pathegama Central','active',1,'2026-09-03 05:45:20'),(1540,38,'Pathegama South','active',1,'2026-09-03 05:45:20'),(1541,38,'Beliwatta','active',1,'2026-09-03 05:45:20'),(1542,38,'Suduwella','active',1,'2026-09-03 05:45:20'),(1543,38,'Kottagoda','active',1,'2026-09-03 05:45:20'),(1544,38,'Godauda','active',1,'2026-09-03 05:45:20'),(1545,38,'Lunukalapuwa','active',1,'2026-09-03 05:45:20'),(1546,39,'Ihala Kumbukwewa','active',1,'2026-09-03 05:45:20'),(1547,39,'Mahagalwewa','active',1,'2026-09-03 05:45:20'),(1548,39,'Meegaha Jadura','active',1,'2026-09-03 05:45:20'),(1549,39,'Ranmuduwewa','active',1,'2026-09-03 05:45:20'),(1550,39,'Weliwewa','active',1,'2026-09-03 05:45:20'),(1551,39,'Suravirugama','active',1,'2026-09-03 05:45:20'),(1552,39,'Samajasewapura','active',1,'2026-09-03 05:45:20'),(1553,39,'Weeriyagama','active',1,'2026-09-03 05:45:20'),(1554,39,'Hathporuwa','active',1,'2026-09-03 05:45:20'),(1555,39,'Weniwel Ara','active',1,'2026-09-03 05:45:20'),(1556,39,'Aliolu Ara','active',1,'2026-09-03 05:45:20'),(1557,39,'Sooriyawewa Town','active',1,'2026-09-03 05:45:20'),(1558,39,'Viharagala','active',1,'2026-09-03 05:45:20'),(1559,39,'Beddewewa','active',1,'2026-09-03 05:45:20'),(1560,39,'Mahawelikada Ara','active',1,'2026-09-03 05:45:20'),(1561,39,'Andarawewa','active',1,'2026-09-03 05:45:20'),(1562,39,'Namadagaswewa','active',1,'2026-09-03 05:45:20'),(1563,39,'Mahapelessa','active',1,'2026-09-03 05:45:20'),(1564,39,'Bediganthota','active',1,'2026-09-03 05:45:20'),(1565,39,'Habarattawala','active',1,'2026-09-03 05:45:20'),(1566,39,'Wediwewa','active',1,'2026-09-03 05:45:20'),(1567,40,'Maha Aluth Gam Ara','active',1,'2026-09-03 05:45:20'),(1568,40,'Angunakolawewa','active',1,'2026-09-03 05:45:20'),(1569,40,'Veheragala','active',1,'2026-09-03 05:45:20'),(1570,40,'Ranawarnawa','active',1,'2026-09-03 05:45:20'),(1571,40,'Dewramvehera','active',1,'2026-09-03 05:45:20'),(1572,40,'Bogahawewa','active',1,'2026-09-03 05:45:20'),(1573,40,'Padavigama','active',1,'2026-09-03 05:45:20'),(1574,40,'Punchiappujandura','active',1,'2026-09-03 05:45:20'),(1575,40,'Lunugamvehera New Town','active',1,'2026-09-03 05:45:20'),(1576,40,'Seenimunna','active',1,'2026-09-03 05:45:20'),(1577,40,'Kendagasmankada','active',1,'2026-09-03 05:45:20'),(1578,40,'Mahanagapura','active',1,'2026-09-03 05:45:20'),(1579,40,'Muwanwewa','active',1,'2026-09-03 05:45:20'),(1580,40,'Weeravil Ara','active',1,'2026-09-03 05:45:20'),(1581,40,'Ittanwekada','active',1,'2026-09-03 05:45:20'),(1582,40,'Mattala','active',1,'2026-09-03 05:45:20'),(1583,40,'Pahala Mattala','active',1,'2026-09-03 05:45:20'),(1584,40,'Mihindupura','active',1,'2026-09-03 05:45:20'),(1585,40,'Abhayapura','active',1,'2026-09-03 05:45:20'),(1586,40,'Singhapura','active',1,'2026-09-03 05:45:20'),(1587,40,'Senapura','active',1,'2026-09-03 05:45:20'),(1588,40,'Agbopura','active',1,'2026-09-03 05:45:20'),(1589,40,'Karambawewa','active',1,'2026-09-03 05:45:20'),(1590,40,'Rambukwewa','active',1,'2026-09-03 05:45:20'),(1591,40,'Keerthipura','active',1,'2026-09-03 05:45:20'),(1592,40,'Samanpura','active',1,'2026-09-03 05:45:20'),(1593,40,'Ranasiripura','active',1,'2026-09-03 05:45:20'),(1594,40,'Weeravila','active',1,'2026-09-03 05:45:20'),(1595,40,'Weligatta','active',1,'2026-09-03 05:45:20'),(1596,40,'Beralihela','active',1,'2026-09-03 05:45:20'),(1597,40,'Dutugemunupura','active',1,'2026-09-03 05:45:20'),(1598,40,'Jayagama','active',1,'2026-09-03 05:45:20'),(1599,40,'Parakramapura','active',1,'2026-09-03 05:45:20'),(1600,40,'Saddhathissapura','active',1,'2026-09-03 05:45:20'),(1601,40,'Saddhathissapura New Town','active',1,'2026-09-03 05:45:20'),(1602,40,'Saliyapura','active',1,'2026-09-03 05:45:20'),(1603,41,'Kawanthissapura','active',1,'2026-09-03 05:45:20'),(1604,41,'Kirinda','active',1,'2026-09-03 05:45:20'),(1605,41,'Uddhakandara','active',1,'2026-09-03 05:45:20'),(1606,41,'Joolpallama','active',1,'2026-09-03 05:45:20'),(1607,41,'Weerahela','active',1,'2026-09-03 05:45:20'),(1608,41,'Vijithapura','active',1,'2026-09-03 05:45:20'),(1609,41,'Ellagala','active',1,'2026-09-03 05:45:20'),(1610,41,'Anjaligala','active',1,'2026-09-03 05:45:20'),(1611,41,'Pannagamuwa','active',1,'2026-09-03 05:45:20'),(1612,41,'Dambewelena','active',1,'2026-09-03 05:45:20'),(1613,41,'Mahindapura','active',1,'2026-09-03 05:45:20'),(1614,41,'Gemunupura','active',1,'2026-09-03 05:45:20'),(1615,41,'Ekamuthugama','active',1,'2026-09-03 05:45:20'),(1616,41,'Sandungama','active',1,'2026-09-03 05:45:20'),(1617,41,'Debarawewa','active',1,'2026-09-03 05:45:20'),(1618,41,'Kachcheriyagama','active',1,'2026-09-03 05:45:20'),(1619,41,'Sandagiripura','active',1,'2026-09-03 05:45:20'),(1620,41,'Mahasenpura','active',1,'2026-09-03 05:45:20'),(1621,41,'Shuddha Nagaraya','active',1,'2026-09-03 05:45:20'),(1622,41,'Medawelena','active',1,'2026-09-03 05:45:20'),(1623,41,'Rohanapura','active',1,'2026-09-03 05:45:20'),(1624,41,'Senapura','active',1,'2026-09-03 05:45:20'),(1625,41,'Gangasiripura','active',1,'2026-09-03 05:45:20'),(1626,41,'Polgahawelena','active',1,'2026-09-03 05:45:20'),(1627,41,'Randunu Watta','active',1,'2026-09-03 05:45:20'),(1628,41,'Molakepoopathana','active',1,'2026-09-03 05:45:20'),(1629,41,'Thissapura','active',1,'2026-09-03 05:45:20'),(1630,41,'Uduvila','active',1,'2026-09-03 05:45:20'),(1631,41,'Rubberwatta','active',1,'2026-09-03 05:45:20'),(1632,41,'Thissamaharama','active',1,'2026-09-03 05:45:20'),(1633,41,'Gotabhayapura','active',1,'2026-09-03 05:45:20'),(1634,41,'Rana Keliya','active',1,'2026-09-03 05:45:20'),(1635,41,'Viharamahadevipura','active',1,'2026-09-03 05:45:20'),(1636,41,'Yodhakandiya','active',1,'2026-09-03 05:45:20'),(1637,41,'Welipothewala','active',1,'2026-09-03 05:45:20'),(1638,41,'Halmillawa','active',1,'2026-09-03 05:45:20'),(1639,41,'Rathnelumwalayaya','active',1,'2026-09-03 05:45:20'),(1640,41,'Gonagamuwa','active',1,'2026-09-03 05:45:20'),(1641,41,'Saliyapura','active',1,'2026-09-03 05:45:20'),(1642,41,'Nedigamvila','active',1,'2026-09-03 05:45:20'),(1643,41,'Wijayapura','active',1,'2026-09-03 05:45:20'),(1644,41,'Konwelena','active',1,'2026-09-03 05:45:20'),(1645,41,'Magama','active',1,'2026-09-03 05:45:20'),(1646,41,'Andaragasyaya','active',1,'2026-09-03 05:45:20'),(1647,42,'Elalla','active',1,'2026-09-03 05:45:20'),(1648,42,'Ketenwewa','active',1,'2026-09-03 05:45:20'),(1649,42,'Thammennawa','active',1,'2026-09-03 05:45:20'),(1650,42,'Badagiriya','active',1,'2026-09-03 05:45:20'),(1651,42,'Yahangala East','active',1,'2026-09-03 05:45:20'),(1652,42,'Joolgamuwa','active',1,'2026-09-03 05:45:20'),(1653,42,'Yahangala West','active',1,'2026-09-03 05:45:20'),(1654,42,'Keliyapura','active',1,'2026-09-03 05:45:20'),(1655,42,'Gonnoruwa','active',1,'2026-09-03 05:45:20'),(1656,42,'Bellagaswewa','active',1,'2026-09-03 05:45:20'),(1657,42,'Galwewa','active',1,'2026-09-03 05:45:20'),(1658,42,'Siyambalagasvila South','active',1,'2026-09-03 05:45:20'),(1659,42,'Siyambalagasvila North','active',1,'2026-09-03 05:45:20'),(1660,42,'Udaberagama','active',1,'2026-09-03 05:45:20'),(1661,42,'Arawanamulla','active',1,'2026-09-03 05:45:20'),(1662,42,'Pahala Beragama','active',1,'2026-09-03 05:45:20'),(1663,42,'Dehigahalanda','active',1,'2026-09-03 05:45:20'),(1664,42,'Walawa','active',1,'2026-09-03 05:45:20'),(1665,42,'Godawaya','active',1,'2026-09-03 05:45:20'),(1666,42,'Sisilasagama','active',1,'2026-09-03 05:45:20'),(1667,42,'Manajjawa','active',1,'2026-09-03 05:45:20'),(1668,42,'Samodagama','active',1,'2026-09-03 05:45:20'),(1669,42,'Mirijjavila','active',1,'2026-09-03 05:45:20'),(1670,42,'Hambantota West','active',1,'2026-09-03 05:45:20'),(1671,42,'Hambantota East','active',1,'2026-09-03 05:45:20'),(1672,42,'Siribopura','active',1,'2026-09-03 05:45:20'),(1673,42,'Koholankala','active',1,'2026-09-03 05:45:20'),(1674,42,'Pallemalala','active',1,'2026-09-03 05:45:20'),(1675,42,'Bundala','active',1,'2026-09-03 05:45:20'),(1676,42,'Siriyagama','active',1,'2026-09-03 05:45:20'),(1677,43,'Siyambalakote','active',1,'2026-09-03 05:45:20'),(1678,43,'Barawakumbuka','active',1,'2026-09-03 05:45:20'),(1679,43,'Thaligala','active',1,'2026-09-03 05:45:20'),(1680,43,'Liyangasthota','active',1,'2026-09-03 05:45:20'),(1681,43,'Wetiya','active',1,'2026-09-03 05:45:20'),(1682,43,'Murawesihena','active',1,'2026-09-03 05:45:20'),(1683,43,'Mahajandura','active',1,'2026-09-03 05:45:20'),(1684,43,'Handunkatuwa','active',1,'2026-09-03 05:45:20'),(1685,43,'Rote','active',1,'2026-09-03 05:45:20'),(1686,43,'Hedavinna','active',1,'2026-09-03 05:45:20'),(1687,43,'Ridiyagama','active',1,'2026-09-03 05:45:20'),(1688,43,'Punchihenayagama','active',1,'2026-09-03 05:45:20'),(1689,43,'Poliyarwatta','active',1,'2026-09-03 05:45:20'),(1690,43,'Godakoggalla','active',1,'2026-09-03 05:45:20'),(1691,43,'Koggalla','active',1,'2026-09-03 05:45:20'),(1692,43,'Modara Piliwala','active',1,'2026-09-03 05:45:20'),(1693,43,'Bolana North','active',1,'2026-09-03 05:45:20'),(1694,43,'Jansagama','active',1,'2026-09-03 05:45:20'),(1695,43,'Mamadala South','active',1,'2026-09-03 05:45:20'),(1696,43,'Mamadala North','active',1,'2026-09-03 05:45:20'),(1697,43,'Ethbatuwa','active',1,'2026-09-03 05:45:20'),(1698,43,'Mulana','active',1,'2026-09-03 05:45:20'),(1699,43,'Eraminiyaya','active',1,'2026-09-03 05:45:20'),(1700,43,'Ihalagama','active',1,'2026-09-03 05:45:20'),(1701,43,'Deniya','active',1,'2026-09-03 05:45:20'),(1702,43,'Elegoda West','active',1,'2026-09-03 05:45:20'),(1703,43,'Elegoda East','active',1,'2026-09-03 05:45:20'),(1704,43,'Beminiyanvila','active',1,'2026-09-03 05:45:20'),(1705,43,'Walawewatta West','active',1,'2026-09-03 05:45:20'),(1706,43,'Walawewatta East','active',1,'2026-09-03 05:45:20'),(1707,43,'Bolana South','active',1,'2026-09-03 05:45:20'),(1708,43,'Kudabolana','active',1,'2026-09-03 05:45:20'),(1709,43,'Palugaha Godella','active',1,'2026-09-03 05:45:20'),(1710,43,'Rotawala','active',1,'2026-09-03 05:45:20'),(1711,43,'Uhapitagoda','active',1,'2026-09-03 05:45:20'),(1712,43,'Miniethiliya','active',1,'2026-09-03 05:45:20'),(1713,43,'Pingama','active',1,'2026-09-03 05:45:20'),(1714,43,'Pallegama','active',1,'2026-09-03 05:45:20'),(1715,43,'Hungama','active',1,'2026-09-03 05:45:20'),(1716,43,'Bataatha North','active',1,'2026-09-03 05:45:20'),(1717,43,'Bataatha South','active',1,'2026-09-03 05:45:20'),(1718,43,'Hathagala','active',1,'2026-09-03 05:45:20'),(1719,43,'Kivula South','active',1,'2026-09-03 05:45:20'),(1720,43,'Kivula North','active',1,'2026-09-03 05:45:20'),(1721,43,'Lunama North','active',1,'2026-09-03 05:45:20'),(1722,43,'Lunama South','active',1,'2026-09-03 05:45:20'),(1723,43,'Nonagama','active',1,'2026-09-03 05:45:20'),(1724,43,'Ekkassa','active',1,'2026-09-03 05:45:20'),(1725,43,'Welipatanvila','active',1,'2026-09-03 05:45:20'),(1726,43,'Malpettawa','active',1,'2026-09-03 05:45:20'),(1727,43,'Puhulyaya','active',1,'2026-09-03 05:45:20'),(1728,43,'Ambalanthota North','active',1,'2026-09-03 05:45:20'),(1729,43,'Ambalanthota South','active',1,'2026-09-03 05:45:20'),(1730,43,'Wanduruppa','active',1,'2026-09-03 05:45:20'),(1731,43,'Thawaluvila','active',1,'2026-09-03 05:45:20'),(1732,44,'Kariyamaditta','active',1,'2026-09-03 05:45:20'),(1733,44,'Dabarella North','active',1,'2026-09-03 05:45:20'),(1734,44,'Rathmalwala','active',1,'2026-09-03 05:45:20'),(1735,44,'Kailawelpotawa','active',1,'2026-09-03 05:45:20'),(1736,44,'Kendaketiya','active',1,'2026-09-03 05:45:20'),(1737,44,'Dabarella South','active',1,'2026-09-03 05:45:20'),(1738,44,'Thalawa North','active',1,'2026-09-03 05:45:20'),(1739,44,'Thalawa South','active',1,'2026-09-03 05:45:20'),(1740,44,'Debokkawa South','active',1,'2026-09-03 05:45:20'),(1741,44,'Debokkawa North','active',1,'2026-09-03 05:45:20'),(1742,44,'Uswewa','active',1,'2026-09-03 05:45:20'),(1743,44,'Metigathwala','active',1,'2026-09-03 05:45:20'),(1744,44,'Amarathungagama','active',1,'2026-09-03 05:45:20'),(1745,44,'Sooriyapokuna','active',1,'2026-09-03 05:45:20'),(1746,44,'Pahalagama','active',1,'2026-09-03 05:45:20'),(1747,44,'Kohombagaswewa','active',1,'2026-09-03 05:45:20'),(1748,44,'Dikwewa','active',1,'2026-09-03 05:45:20'),(1749,44,'Abesekaragama','active',1,'2026-09-03 05:45:20'),(1750,44,'Kalawelwala','active',1,'2026-09-03 05:45:20'),(1751,44,'Meda Ara','active',1,'2026-09-03 05:45:20'),(1752,44,'Binkama','active',1,'2026-09-03 05:45:20'),(1753,44,'Karagahawala','active',1,'2026-09-03 05:45:20'),(1754,44,'Dandenigama','active',1,'2026-09-03 05:45:20'),(1755,44,'Guruwala','active',1,'2026-09-03 05:45:20'),(1756,44,'Weeragaswewa','active',1,'2026-09-03 05:45:20'),(1757,44,'Gajanayakagama','active',1,'2026-09-03 05:45:20'),(1758,44,'Medagoda','active',1,'2026-09-03 05:45:20'),(1759,44,'Dimbulgoda','active',1,'2026-09-03 05:45:20'),(1760,44,'Attanayala East','active',1,'2026-09-03 05:45:20'),(1761,44,'Attanayala West','active',1,'2026-09-03 05:45:20'),(1762,44,'Wakamulla','active',1,'2026-09-03 05:45:20'),(1763,44,'Heenbunna','active',1,'2026-09-03 05:45:20'),(1764,44,'Makuladeniya','active',1,'2026-09-03 05:45:20'),(1765,44,'Udayala','active',1,'2026-09-03 05:45:20'),(1766,44,'Medayala','active',1,'2026-09-03 05:45:20'),(1767,44,'Bogamuwa','active',1,'2026-09-03 05:45:20'),(1768,44,'Daha Amuna','active',1,'2026-09-03 05:45:20'),(1769,44,'Julamulla','active',1,'2026-09-03 05:45:20'),(1770,44,'Jandura','active',1,'2026-09-03 05:45:20'),(1771,44,'Helekada','active',1,'2026-09-03 05:45:20'),(1772,44,'Aluthwewa','active',1,'2026-09-03 05:45:20'),(1773,44,'Kankanamgama','active',1,'2026-09-03 05:45:20'),(1774,44,'Achariyagama','active',1,'2026-09-03 05:45:20'),(1775,44,'Angunakolapelessa','active',1,'2026-09-03 05:45:20'),(1776,44,'Yakagala','active',1,'2026-09-03 05:45:20'),(1777,44,'Gurunnehege Ara','active',1,'2026-09-03 05:45:20'),(1778,44,'Kotawaya','active',1,'2026-09-03 05:45:20'),(1779,44,'Netalaporuwa','active',1,'2026-09-03 05:45:20'),(1780,44,'Hakuruwela','active',1,'2026-09-03 05:45:20'),(1781,44,'Indigetawela','active',1,'2026-09-03 05:45:20'),(1782,44,'Thalamporuwa','active',1,'2026-09-03 05:45:20'),(1783,45,'Mulanyaya','active',1,'2026-09-03 05:45:20'),(1784,45,'Handapangalageaina','active',1,'2026-09-03 05:45:20'),(1785,45,'Heellage Ayna','active',1,'2026-09-03 05:45:20'),(1786,45,'Kudagal Ara','active',1,'2026-09-03 05:45:20'),(1787,45,'Okandayaya North','active',1,'2026-09-03 05:45:20'),(1788,45,'Okandayaya West','active',1,'2026-09-03 05:45:20'),(1789,45,'Kaluwagahayaya','active',1,'2026-09-03 05:45:20'),(1790,45,'Ihala Gonadeniya','active',1,'2026-09-03 05:45:20'),(1791,45,'Debokkawa West','active',1,'2026-09-03 05:45:20'),(1792,45,'Debokkawa East','active',1,'2026-09-03 05:45:20'),(1793,45,'Wekandawala North','active',1,'2026-09-03 05:45:20'),(1794,45,'Pahala Gonadeniya','active',1,'2026-09-03 05:45:20'),(1795,45,'Kuda Bibula North','active',1,'2026-09-03 05:45:20'),(1796,45,'Galpottayaya North','active',1,'2026-09-03 05:45:20'),(1797,45,'Galpottayaya South','active',1,'2026-09-03 05:45:20'),(1798,45,'Malhewage Ayna','active',1,'2026-09-03 05:45:20'),(1799,45,'Kandamaditta','active',1,'2026-09-03 05:45:20'),(1800,45,'Meegas Ara','active',1,'2026-09-03 05:45:20'),(1801,45,'Kuda Bibula South','active',1,'2026-09-03 05:45:20'),(1802,45,'Abakolawewa South','active',1,'2026-09-03 05:45:20'),(1803,45,'Abakolawewa North','active',1,'2026-09-03 05:45:20'),(1804,45,'Morayaya North','active',1,'2026-09-03 05:45:20'),(1805,45,'Morayaya South','active',1,'2026-09-03 05:45:20'),(1806,45,'Kemegala','active',1,'2026-09-03 05:45:20'),(1807,45,'Wekandawala South','active',1,'2026-09-03 05:45:20'),(1808,45,'Thelambuyaya','active',1,'2026-09-03 05:45:20'),(1809,45,'Siyambalaheddewa','active',1,'2026-09-03 05:45:20'),(1810,45,'Degampotha','active',1,'2026-09-03 05:45:20'),(1811,45,'Kinchigune West','active',1,'2026-09-03 05:45:20'),(1812,45,'Medagama','active',1,'2026-09-03 05:45:20'),(1813,45,'Raluwa','active',1,'2026-09-03 05:45:20'),(1814,45,'Kinchigune South','active',1,'2026-09-03 05:45:20'),(1815,45,'Kinchigune East','active',1,'2026-09-03 05:45:20'),(1816,45,'Medamulana','active',1,'2026-09-03 05:45:20'),(1817,45,'Yakgasmulla','active',1,'2026-09-03 05:45:20'),(1818,45,'Udukirivala','active',1,'2026-09-03 05:45:20'),(1819,45,'Buddhiyagama North','active',1,'2026-09-03 05:45:20'),(1820,45,'Keppitiyawa North','active',1,'2026-09-03 05:45:20'),(1821,45,'Ittademaliya East','active',1,'2026-09-03 05:45:20'),(1822,45,'Ittademaliya West','active',1,'2026-09-03 05:45:20'),(1823,45,'Ittademaliya South','active',1,'2026-09-03 05:45:20'),(1824,45,'Athubode West','active',1,'2026-09-03 05:45:20'),(1825,45,'Athubode East','active',1,'2026-09-03 05:45:20'),(1826,45,'Keppitiyawa South','active',1,'2026-09-03 05:45:20'),(1827,45,'Buddhiyagama West','active',1,'2026-09-03 05:45:20'),(1828,45,'Buddhiyagama East','active',1,'2026-09-03 05:45:20'),(1829,45,'Weeraketiya West','active',1,'2026-09-03 05:45:20'),(1830,45,'Mandaduwa','active',1,'2026-09-03 05:45:20'),(1831,45,'Agrahera','active',1,'2026-09-03 05:45:20'),(1832,45,'Weeraketiya East','active',1,'2026-09-03 05:45:20'),(1833,45,'Mulgirigala East','active',1,'2026-09-03 05:45:20'),(1834,45,'Mulgirigala North','active',1,'2026-09-03 05:45:20'),(1835,45,'Mulgirigala West','active',1,'2026-09-03 05:45:20'),(1836,45,'Mulgirigala South','active',1,'2026-09-03 05:45:20'),(1837,45,'Bedigama West','active',1,'2026-09-03 05:45:20'),(1838,45,'Bedigama North','active',1,'2026-09-03 05:45:20'),(1839,45,'Bedigama East','active',1,'2026-09-03 05:45:20'),(1840,45,'Bedigama South','active',1,'2026-09-03 05:45:20'),(1841,45,'Kuda Bedigama','active',1,'2026-09-03 05:45:20'),(1842,45,'Medagoda','active',1,'2026-09-03 05:45:20'),(1843,46,'Karivilakanda','active',1,'2026-09-03 05:45:20'),(1844,46,'Medakanda','active',1,'2026-09-03 05:45:20'),(1845,46,'Hingurakanda','active',1,'2026-09-03 05:45:20'),(1846,46,'Uda Alupothdeniya','active',1,'2026-09-03 05:45:20'),(1847,46,'Alupothdeniya Pahala','active',1,'2026-09-03 05:45:20'),(1848,46,'Kohomporuwa','active',1,'2026-09-03 05:45:20'),(1849,46,'Gangulandeniya','active',1,'2026-09-03 05:45:20'),(1850,46,'Ambagas Ara','active',1,'2026-09-03 05:45:20'),(1851,46,'Labuhengoda','active',1,'2026-09-03 05:45:20'),(1852,46,'Sapugahayaya','active',1,'2026-09-03 05:45:20'),(1853,46,'Hellala','active',1,'2026-09-03 05:45:20'),(1854,46,'Middeniya North','active',1,'2026-09-03 05:45:20'),(1855,46,'Middeniya East','active',1,'2026-09-03 05:45:20'),(1856,46,'Kudagoda West','active',1,'2026-09-03 05:45:20'),(1857,46,'Kudagoda East','active',1,'2026-09-03 05:45:20'),(1858,46,'Dambethalawa','active',1,'2026-09-03 05:45:20'),(1859,46,'Murungasyaya East','active',1,'2026-09-03 05:45:20'),(1860,46,'Murungasyaya West','active',1,'2026-09-03 05:45:20'),(1861,46,'Middeniya West','active',1,'2026-09-03 05:45:20'),(1862,46,'Welipitiya East','active',1,'2026-09-03 05:45:20'),(1863,46,'Welipitiya','active',1,'2026-09-03 05:45:20'),(1864,46,'Welipitiya West','active',1,'2026-09-03 05:45:20'),(1865,46,'Ritigaha Yaya','active',1,'2026-09-03 05:45:20'),(1866,46,'Thalwatta','active',1,'2026-09-03 05:45:20'),(1867,46,'Meemanakoladeniya','active',1,'2026-09-03 05:45:20'),(1868,46,'Siyambalamuraya','active',1,'2026-09-03 05:45:20'),(1869,46,'Udawelmulla','active',1,'2026-09-03 05:45:20'),(1870,46,'Siyarapitiya','active',1,'2026-09-03 05:45:20'),(1871,46,'Kehelwatta','active',1,'2026-09-03 05:45:20'),(1872,46,'Udagomadiya','active',1,'2026-09-03 05:45:20'),(1873,46,'Bengamukanda','active',1,'2026-09-03 05:45:20'),(1874,46,'Dangalakanda','active',1,'2026-09-03 05:45:20'),(1875,46,'Rukmalpitiya','active',1,'2026-09-03 05:45:20'),(1876,46,'Gallindamulla','active',1,'2026-09-03 05:45:20'),(1877,46,'Katuwana','active',1,'2026-09-03 05:45:20'),(1878,46,'Pangamvilayaya','active',1,'2026-09-03 05:45:20'),(1879,46,'Ulahitiyawa West','active',1,'2026-09-03 05:45:20'),(1880,46,'Ulahitiyawa','active',1,'2026-09-03 05:45:20'),(1881,46,'Ulahitiyawa East','active',1,'2026-09-03 05:45:20'),(1882,46,'Adalugoda','active',1,'2026-09-03 05:45:20'),(1883,46,'Mellaketigoda','active',1,'2026-09-03 05:45:20'),(1884,46,'Araboda','active',1,'2026-09-03 05:45:20'),(1885,46,'Horavinna','active',1,'2026-09-03 05:45:20'),(1886,46,'Hediwatta','active',1,'2026-09-03 05:45:20'),(1887,46,'Weerakkuttigoda','active',1,'2026-09-03 05:45:20'),(1888,46,'Ranasingoda','active',1,'2026-09-03 05:45:20'),(1889,46,'Obadagahadeniya','active',1,'2026-09-03 05:45:20'),(1890,46,'Wathukanda','active',1,'2026-09-03 05:45:20'),(1891,46,'Kongasthenna','active',1,'2026-09-03 05:45:20'),(1892,46,'Galpothukanda','active',1,'2026-09-03 05:45:20'),(1893,46,'Ambagahahena','active',1,'2026-09-03 05:45:20'),(1894,46,'Karametiya','active',1,'2026-09-03 05:45:20'),(1895,46,'Puwakgas Ara','active',1,'2026-09-03 05:45:20'),(1896,46,'Binthenna','active',1,'2026-09-03 05:45:20'),(1897,46,'Bookandayaya','active',1,'2026-09-03 05:45:20'),(1898,46,'Walgammulla','active',1,'2026-09-03 05:45:20'),(1899,47,'Rammala','active',1,'2026-09-03 05:45:20'),(1900,47,'Saputhanthirikanda','active',1,'2026-09-03 05:45:20'),(1901,47,'Warapitiya','active',1,'2026-09-03 05:45:20'),(1902,47,'Keredeniya','active',1,'2026-09-03 05:45:20'),(1903,47,'Haggitha Kanda North','active',1,'2026-09-03 05:45:20'),(1904,47,'Konkarahena','active',1,'2026-09-03 05:45:20'),(1905,47,'Mapitakanda','active',1,'2026-09-03 05:45:20'),(1906,47,'Radani Ara','active',1,'2026-09-03 05:45:20'),(1907,47,'Namaneliya','active',1,'2026-09-03 05:45:20'),(1908,47,'Udahagoda','active',1,'2026-09-03 05:45:20'),(1909,47,'Weedikanda','active',1,'2026-09-03 05:45:20'),(1910,47,'Galwadiya','active',1,'2026-09-03 05:45:20'),(1911,47,'Uda Julampitiya','active',1,'2026-09-03 05:45:20'),(1912,47,'Julampitiya','active',1,'2026-09-03 05:45:20'),(1913,47,'Palle Julampitiya','active',1,'2026-09-03 05:45:20'),(1914,47,'Pahala Obada','active',1,'2026-09-03 05:45:20'),(1915,47,'Bowala North','active',1,'2026-09-03 05:45:20'),(1916,47,'Bowala West','active',1,'2026-09-03 05:45:20'),(1917,47,'Welandagoda','active',1,'2026-09-03 05:45:20'),(1918,47,'Batagassa','active',1,'2026-09-03 05:45:20'),(1919,47,'Pahalawatta','active',1,'2026-09-03 05:45:20'),(1920,47,'Pathegama','active',1,'2026-09-03 05:45:20'),(1921,47,'Thalapathkanda','active',1,'2026-09-03 05:45:20'),(1922,47,'Dehigahahena','active',1,'2026-09-03 05:45:20'),(1923,47,'Veedipola','active',1,'2026-09-03 05:45:20'),(1924,47,'Mathuwakanda','active',1,'2026-09-03 05:45:20'),(1925,47,'Handugala','active',1,'2026-09-03 05:45:20'),(1926,47,'Kebellaketiya','active',1,'2026-09-03 05:45:20'),(1927,47,'Egodabedda','active',1,'2026-09-03 05:45:20'),(1928,47,'Kekiriobada','active',1,'2026-09-03 05:45:20'),(1929,47,'Agalabada','active',1,'2026-09-03 05:45:20'),(1930,47,'Bowala South','active',1,'2026-09-03 05:45:20'),(1931,47,'Muruthawela Ihala','active',1,'2026-09-03 05:45:20'),(1932,47,'Muruthawela Pahala','active',1,'2026-09-03 05:45:20'),(1933,47,'Galahitiya North','active',1,'2026-09-03 05:45:20'),(1934,47,'Omara East','active',1,'2026-09-03 05:45:20'),(1935,47,'Omara West','active',1,'2026-09-03 05:45:20'),(1936,47,'Ethpitiya','active',1,'2026-09-03 05:45:20'),(1937,47,'Wattehengoda','active',1,'2026-09-03 05:45:20'),(1938,47,'Horewela','active',1,'2026-09-03 05:45:20'),(1939,47,'Pissubedda','active',1,'2026-09-03 05:45:20'),(1940,47,'Medagamgoda','active',1,'2026-09-03 05:45:20'),(1941,47,'Yahalmulla','active',1,'2026-09-03 05:45:20'),(1942,47,'Walasmulla Ihala','active',1,'2026-09-03 05:45:20'),(1943,47,'Walasmulla Pahala','active',1,'2026-09-03 05:45:20'),(1944,47,'Galahitiya South','active',1,'2026-09-03 05:45:20'),(1945,47,'Galahitiya East','active',1,'2026-09-03 05:45:20'),(1946,47,'Koholana','active',1,'2026-09-03 05:45:20'),(1947,47,'Walasmulla East','active',1,'2026-09-03 05:45:20'),(1948,47,'Walasmulla North','active',1,'2026-09-03 05:45:20'),(1949,47,'Walasmulla West','active',1,'2026-09-03 05:45:20'),(1950,47,'Walasmulla South','active',1,'2026-09-03 05:45:20'),(1951,47,'Daluwakgoda','active',1,'2026-09-03 05:45:20'),(1952,48,'Kanumuldeniya North','active',1,'2026-09-03 05:45:20'),(1953,48,'Olu Ara','active',1,'2026-09-03 05:45:20'),(1954,48,'Kanumuldeniya West','active',1,'2026-09-03 05:45:20'),(1955,48,'Kanumuldeniya East','active',1,'2026-09-03 05:45:20'),(1956,48,'Udadeniya','active',1,'2026-09-03 05:45:20'),(1957,48,'Kurunduwatta','active',1,'2026-09-03 05:45:20'),(1958,48,'Sumihirigama','active',1,'2026-09-03 05:45:20'),(1959,48,'Rajapuragoda','active',1,'2026-09-03 05:45:20'),(1960,48,'Wijayasiripura','active',1,'2026-09-03 05:45:20'),(1961,48,'Morakandegoda','active',1,'2026-09-03 05:45:20'),(1962,48,'Kadigamuwa East','active',1,'2026-09-03 05:45:20'),(1963,48,'Thalahagamwaduwa Pahala','active',1,'2026-09-03 05:45:20'),(1964,48,'Thalahagamwaduwa Ihala','active',1,'2026-09-03 05:45:20'),(1965,48,'Nathuwala','active',1,'2026-09-03 05:45:20'),(1966,48,'Kandebedda','active',1,'2026-09-03 05:45:20'),(1967,48,'Godawenna','active',1,'2026-09-03 05:45:20'),(1968,48,'Kahatellagoda','active',1,'2026-09-03 05:45:20'),(1969,48,'Kanumuldeniya South','active',1,'2026-09-03 05:45:20'),(1970,48,'Kadigamuwa West','active',1,'2026-09-03 05:45:20'),(1971,48,'Wawwa','active',1,'2026-09-03 05:45:20'),(1972,48,'Modarawana North','active',1,'2026-09-03 05:45:20'),(1973,48,'Modarawana South','active',1,'2026-09-03 05:45:20'),(1974,48,'Okewela','active',1,'2026-09-03 05:45:20'),(1975,48,'Heenatihathamuna','active',1,'2026-09-03 05:45:20'),(1976,48,'Pallewawwa','active',1,'2026-09-03 05:45:20'),(1977,48,'Yatigala Pahala','active',1,'2026-09-03 05:45:20'),(1978,48,'Yatigala Ihala','active',1,'2026-09-03 05:45:20'),(1979,49,'Maligathenna','active',1,'2026-09-03 05:45:20'),(1980,49,'Udugalmotegama','active',1,'2026-09-03 05:45:20'),(1981,49,'Pallattara West','active',1,'2026-09-03 05:45:20'),(1982,49,'Pallattara South','active',1,'2026-09-03 05:45:20'),(1983,49,'Pallattara East','active',1,'2026-09-03 05:45:20'),(1984,49,'Nugewela','active',1,'2026-09-03 05:45:20'),(1985,49,'Ihala Beligalla East','active',1,'2026-09-03 05:45:20'),(1986,49,'Wadiya','active',1,'2026-09-03 05:45:20'),(1987,49,'Ihala Beligalla West','active',1,'2026-09-03 05:45:20'),(1988,49,'Beligalla North','active',1,'2026-09-03 05:45:20'),(1989,49,'Beligalla South','active',1,'2026-09-03 05:45:20'),(1990,49,'Dammulla East','active',1,'2026-09-03 05:45:20'),(1991,49,'Dammulla West','active',1,'2026-09-03 05:45:20'),(1992,49,'Pattiyawela','active',1,'2026-09-03 05:45:20'),(1993,49,'Tharaperiya','active',1,'2026-09-03 05:45:20'),(1994,49,'Nihiluwa West','active',1,'2026-09-03 05:45:20'),(1995,49,'Indiketiyagoda','active',1,'2026-09-03 05:45:20'),(1996,49,'Nihiluwa East','active',1,'2026-09-03 05:45:20'),(1997,49,'Waharakgoda North','active',1,'2026-09-03 05:45:20'),(1998,49,'Waharakgoda South','active',1,'2026-09-03 05:45:20'),(1999,49,'Kahawatta','active',1,'2026-09-03 05:45:20'),(2000,49,'Kosgahagoda','active',1,'2026-09-03 05:45:20'),(2001,49,'Agulmaduwa','active',1,'2026-09-03 05:45:20'),(2002,49,'Aranwela North','active',1,'2026-09-03 05:45:20'),(2003,49,'Karambaketiya','active',1,'2026-09-03 05:45:20'),(2004,49,'Galwewa','active',1,'2026-09-03 05:45:20'),(2005,49,'Godawela','active',1,'2026-09-03 05:45:20'),(2006,49,'Panamulla','active',1,'2026-09-03 05:45:20'),(2007,49,'Ambagasdeniya','active',1,'2026-09-03 05:45:20'),(2008,49,'Getamanna North','active',1,'2026-09-03 05:45:20'),(2009,49,'Eldeniya','active',1,'2026-09-03 05:45:20'),(2010,49,'Getamanna West','active',1,'2026-09-03 05:45:20'),(2011,49,'Getamanna East','active',1,'2026-09-03 05:45:20'),(2012,49,'Mahaheella East','active',1,'2026-09-03 05:45:20'),(2013,49,'Kambussawala West','active',1,'2026-09-03 05:45:20'),(2014,49,'Kambussawala East','active',1,'2026-09-03 05:45:20'),(2015,49,'Beliatta West','active',1,'2026-09-03 05:45:20'),(2016,49,'Beliatta Town','active',1,'2026-09-03 05:45:20'),(2017,49,'Puwakdandawa North','active',1,'2026-09-03 05:45:20'),(2018,49,'Puwakdandawa East','active',1,'2026-09-03 05:45:20'),(2019,49,'Aranwela West','active',1,'2026-09-03 05:45:20'),(2020,49,'Sitinamaluwa West','active',1,'2026-09-03 05:45:20'),(2021,49,'Sitinamaluwa North','active',1,'2026-09-03 05:45:20'),(2022,49,'Sitinamaluwa East','active',1,'2026-09-03 05:45:20'),(2023,49,'Sitinamaluwa South','active',1,'2026-09-03 05:45:20'),(2024,49,'Pahalagoda','active',1,'2026-09-03 05:45:20'),(2025,49,'Medagoda','active',1,'2026-09-03 05:45:20'),(2026,49,'Beliatta South','active',1,'2026-09-03 05:45:20'),(2027,49,'Kudaheella East','active',1,'2026-09-03 05:45:20'),(2028,49,'Kudaheella North','active',1,'2026-09-03 05:45:20'),(2029,49,'Mahaheella West','active',1,'2026-09-03 05:45:20'),(2030,49,'Mahaheella North','active',1,'2026-09-03 05:45:20'),(2031,49,'Getamanna South','active',1,'2026-09-03 05:45:20'),(2032,49,'Nayakawatta','active',1,'2026-09-03 05:45:20'),(2033,49,'Ambala North','active',1,'2026-09-03 05:45:20'),(2034,49,'Ambala West','active',1,'2026-09-03 05:45:20'),(2035,49,'Miriswatta','active',1,'2026-09-03 05:45:20'),(2036,49,'Kudaheella South','active',1,'2026-09-03 05:45:20'),(2037,49,'Ovilana','active',1,'2026-09-03 05:45:20'),(2038,49,'Mihindupura','active',1,'2026-09-03 05:45:20'),(2039,49,'Palapotha East','active',1,'2026-09-03 05:45:20'),(2040,49,'Palapotha West','active',1,'2026-09-03 05:45:20'),(2041,49,'Dedduwawala East','active',1,'2026-09-03 05:45:20'),(2042,49,'Dedduwawala','active',1,'2026-09-03 05:45:20'),(2043,49,'Galagama North','active',1,'2026-09-03 05:45:20'),(2044,49,'Galagama West','active',1,'2026-09-03 05:45:20'),(2045,49,'Galagama South','active',1,'2026-09-03 05:45:20'),(2046,49,'Galagama East','active',1,'2026-09-03 05:45:20'),(2047,49,'Nakulugamuwa West','active',1,'2026-09-03 05:45:20'),(2048,49,'Nakulugamuwa North','active',1,'2026-09-03 05:45:20'),(2049,49,'Wevudatta','active',1,'2026-09-03 05:45:20'),(2050,50,'Sudarshanagama','active',1,'2026-09-03 05:45:20'),(2051,50,'Pattiyapola West','active',1,'2026-09-03 05:45:20'),(2052,50,'Thalunna','active',1,'2026-09-03 05:45:20'),(2053,50,'Andupelena','active',1,'2026-09-03 05:45:20'),(2054,50,'Kadiragoda','active',1,'2026-09-03 05:45:20'),(2055,50,'Gotaimbaragama','active',1,'2026-09-03 05:45:20'),(2056,50,'Kattakaduwa North','active',1,'2026-09-03 05:45:20'),(2057,50,'Kattakaduwa South','active',1,'2026-09-03 05:45:20'),(2058,50,'Ranna East','active',1,'2026-09-03 05:45:20'),(2059,50,'Ranna West','active',1,'2026-09-03 05:45:20'),(2060,50,'Vigamuwa','active',1,'2026-09-03 05:45:20'),(2061,50,'Pattiyapola East','active',1,'2026-09-03 05:45:20'),(2062,50,'Pattiyapola South','active',1,'2026-09-03 05:45:20'),(2063,50,'Vitharandeniya North','active',1,'2026-09-03 05:45:20'),(2064,50,'Thenagama South','active',1,'2026-09-03 05:45:20'),(2065,50,'Thenagama North','active',1,'2026-09-03 05:45:20'),(2066,50,'Thalapitiyagama','active',1,'2026-09-03 05:45:20'),(2067,50,'Ethgalamulla','active',1,'2026-09-03 05:45:20'),(2068,50,'Uduvilagoda','active',1,'2026-09-03 05:45:20'),(2069,50,'Vitharandeniya South','active',1,'2026-09-03 05:45:20'),(2070,50,'Aluthgoda','active',1,'2026-09-03 05:45:20'),(2071,50,'Palathuduwa','active',1,'2026-09-03 05:45:20'),(2072,50,'Marakolliya','active',1,'2026-09-03 05:45:20'),(2073,50,'Medagama','active',1,'2026-09-03 05:45:20'),(2074,50,'Netolpitiya North','active',1,'2026-09-03 05:45:20'),(2075,50,'Netolpitiya South','active',1,'2026-09-03 05:45:20'),(2076,50,'Wadigala','active',1,'2026-09-03 05:45:20'),(2077,50,'Kahandawa','active',1,'2026-09-03 05:45:20'),(2078,50,'Nidahasgama West','active',1,'2026-09-03 05:45:20'),(2079,50,'Nidahasgama East','active',1,'2026-09-03 05:45:20'),(2080,50,'Kahandamodara','active',1,'2026-09-03 05:45:20'),(2081,50,'Gurupokuna','active',1,'2026-09-03 05:45:20'),(2082,50,'Wella Odaya','active',1,'2026-09-03 05:45:20'),(2083,50,'Rekawa East','active',1,'2026-09-03 05:45:20'),(2084,50,'Rekawa West','active',1,'2026-09-03 05:45:20'),(2085,50,'Medilla','active',1,'2026-09-03 05:45:20'),(2086,50,'Walgameliya','active',1,'2026-09-03 05:45:20'),(2087,50,'Godawanagoda','active',1,'2026-09-03 05:45:20'),(2088,50,'Wagegoda','active',1,'2026-09-03 05:45:20'),(2089,50,'Nalagama East','active',1,'2026-09-03 05:45:20'),(2090,50,'Siyambalagoda','active',1,'2026-09-03 05:45:20'),(2091,50,'Nalagama West','active',1,'2026-09-03 05:45:20'),(2092,50,'Polommaruwa North','active',1,'2026-09-03 05:45:20'),(2093,50,'Danketiya','active',1,'2026-09-03 05:45:20'),(2094,50,'Medaketiya','active',1,'2026-09-03 05:45:20'),(2095,50,'Kotuwegoda','active',1,'2026-09-03 05:45:20'),(2096,50,'Indipokunagoda North','active',1,'2026-09-03 05:45:20'),(2097,50,'Polommaruwa South','active',1,'2026-09-03 05:45:20'),(2098,50,'Kadurupokuna East','active',1,'2026-09-03 05:45:20'),(2099,50,'Indipokunagoda South','active',1,'2026-09-03 05:45:20'),(2100,50,'Pallikkudawa Urban','active',1,'2026-09-03 05:45:20'),(2101,50,'Pallikkudawa Rural','active',1,'2026-09-03 05:45:20'),(2102,50,'Kadurupokuna South','active',1,'2026-09-03 05:45:20'),(2103,50,'Kadurupokuna North','active',1,'2026-09-03 05:45:20'),(2104,50,'Kadurupokuna West','active',1,'2026-09-03 05:45:20'),(2105,50,'Seenimodara West','active',1,'2026-09-03 05:45:20'),(2106,50,'Seenimodara East','active',1,'2026-09-03 05:45:20'),(2107,50,'Unakooruwa West','active',1,'2026-09-03 05:45:20'),(2108,50,'Unakooruwa East','active',1,'2026-09-03 05:45:20'),(2109,50,'Moraketi Ara East','active',1,'2026-09-03 05:45:20'),(2110,50,'Moraketi Ara West','active',1,'2026-09-03 05:45:20'),(2111,50,'Pahajjawa','active',1,'2026-09-03 05:45:20'),(2112,50,'Mahawela','active',1,'2026-09-03 05:45:20'),(2113,50,'Ihalagoda','active',1,'2026-09-03 05:45:20'),(2114,50,'Nakulugamuwa South','active',1,'2026-09-03 05:45:20'),(2115,50,'Kudawella North','active',1,'2026-09-03 05:45:20'),(2116,50,'Kudawella Central','active',1,'2026-09-03 05:45:20'),(2117,50,'Kudawella East','active',1,'2026-09-03 05:45:20'),(2118,50,'Mawella South','active',1,'2026-09-03 05:45:20'),(2119,50,'Mawella North','active',1,'2026-09-03 05:45:20'),(2120,50,'Kudawella South','active',1,'2026-09-03 05:45:20'),(2121,50,'Kudawella West','active',1,'2026-09-03 05:45:20');
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
  KEY `idx_goods_request_aid` (`aid_request_id`),
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goods_requests`
--

LOCK TABLES `goods_requests` WRITE;
/*!40000 ALTER TABLE `goods_requests` DISABLE KEYS */;
INSERT INTO `goods_requests` VALUES (2,NULL,'GRB-20260912-1AA73DFEA0D3D98D',93,5,43,13,'I need this','rejected','stock is over',2,1,'2026-09-15 20:13:36',NULL,NULL,NULL,NULL,NULL,'2026-09-12 15:05:47','2026-09-15 14:43:36'),(3,NULL,'GRB-20260915-E85F1A3B0F6D6DBE',93,5,43,13,'beacuse he need it','dispatched',NULL,2,1,'2026-09-15 20:13:13',3,2,'2026-09-15 20:15:22','2026-09-15 20:15:22','2026-09-15 20:26:02','2026-09-15 13:59:35','2026-09-15 14:56:02'),(4,NULL,'GRB-20260915-E85F1A3B0F6D6DBE',92,100,43,13,'beacuse he need it','dispatched',NULL,2,1,'2026-09-15 20:13:13',3,2,'2026-09-15 20:15:35','2026-09-15 20:15:35','2026-09-15 20:25:56','2026-09-15 13:59:35','2026-09-15 14:55:56');
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
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
INSERT INTO `inventory_items` VALUES (70,'Contact Lens','Configured Disability Aid',21,'',27,'2026-09-07 10:18:31'),(91,'Spectacles','Configured Disability Aid',21,'',61,'2026-09-16 06:08:15'),(92,'wheelchair','Configured Disability Aid',21,'',477,'2026-09-15 14:45:35'),(93,'hearing aid','Configured Disability Aid',21,'',0,'2026-09-15 14:45:22');
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
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_categories`
--

LOCK TABLES `item_categories` WRITE;
/*!40000 ALTER TABLE `item_categories` DISABLE KEYS */;
INSERT INTO `item_categories` VALUES (21,'Configured Disability Aid','request-based',0,'active',2,'2026-09-05 08:52:32','2026-09-05 08:52:32');
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
INSERT INTO `notification_reads` VALUES (3,'dispatch-4','2026-09-15 14:45:18'),(3,'payment-10','2026-09-11 17:44:19'),(3,'payment-11','2026-09-11 17:44:10'),(3,'payment-2','2026-09-11 17:44:27'),(3,'payment-4','2026-09-11 17:44:32'),(3,'payment-7','2026-09-11 17:44:34'),(3,'payment-8','2026-09-11 17:44:14');
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
INSERT INTO `officer_pools` VALUES (13,43,92,100,1,0,0,'2026-09-15 18:42:40'),(13,43,93,5,0,0,0,'2026-09-15 14:56:02');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pool_allocations`
--

LOCK TABLES `pool_allocations` WRITE;
/*!40000 ALTER TABLE `pool_allocations` DISABLE KEYS */;
INSERT INTO `pool_allocations` VALUES (1,13,92,100,2,'2026-09-15 14:55:56'),(2,13,93,5,2,'2026-09-15 14:56:02');
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registration_requests`
--

LOCK TABLES `registration_requests` WRITE;
/*!40000 ALTER TABLE `registration_requests` DISABLE KEYS */;
INSERT INTO `registration_requests` VALUES (1,'Sunitha Sivakumaran','sunithasivakumaran1@gmail.com','0763121801','$2y$10$1CRWhaj28MudZz6BGA1XDOtKlAN84yLsF8tPMx.C4PvRhAx6F89tq','social-service-officer','Ambalantota',3,43,NULL,'approved',1,'2026-09-03 13:19:52','failed','2026-09-03 07:37:58','2026-09-03 07:49:52'),(2,'Sunitha','sunithasivakumaran8@gmail.com','0763121806','$2y$10$CCgaCVrqL68DNC/1WEKfiuxGx.dvuJzeHGAaqiXLTDScsC3UQRn2i','social-service-officer','Akmeemana',1,16,NULL,'approved',1,'2026-09-03 13:23:47','failed','2026-09-03 07:53:29','2026-09-03 07:53:47'),(3,'suki','sunithasivakumaran901@gmail.com','0763121890','$2y$10$6KQb3eI/gqfWIJ.YwwX61uIrGo8KbDntCwnzROvB1xVk6PvXWgn/u','social-service-officer','Akmeemana',1,16,'already exist','rejected',1,'2026-09-05 13:16:40','failed','2026-09-03 08:03:14','2026-09-05 07:46:41'),(4,'Sunitha Sivakumaran','sunithasivakumaran67@gmail.com','0763121801','$2y$10$.vS/XzxCLYUM7PLFEey32epRHg4ph/3bsQsRs8tCBVoqAj73ZZQhm','store-keeper',NULL,NULL,NULL,NULL,'approved',1,'2026-09-11 23:25:43','failed','2026-09-11 04:12:54','2026-09-11 17:55:43');
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
  UNIQUE KEY `uq_stock_receipt_check_number` (`check_number`),
  KEY `fk_receipt_supplier` (`supplier_id`),
  KEY `fk_receipt_item` (`item_id`),
  KEY `fk_receipt_user` (`received_by`),
  KEY `idx_receipt_date` (`received_date`),
  KEY `idx_receipt_payment` (`payment_status`),
  CONSTRAINT `fk_receipt_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `fk_receipt_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_receipt_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_receipts`
--

LOCK TABLES `stock_receipts` WRITE;
/*!40000 ALTER TABLE `stock_receipts` DISABLE KEYS */;
INSERT INTO `stock_receipts` VALUES (1,52,70,10,3.00,'[{\"sign\":\"+\",\"power\":3,\"count\":4},{\"sign\":\"+\",\"power\":4,\"count\":6}]',44.68,446.80,'fyudfyifg','2026-09-07','fully-paid',NULL,446.80,0.00,3,'2026-09-07 10:06:01'),(2,52,70,1,5.00,'[{\"sign\":\"+\",\"power\":5,\"count\":1}]',47.00,47.00,'sdgwe','2026-09-07','partially-paid',NULL,45.00,2.00,3,'2026-09-07 10:15:31'),(3,52,70,16,5.00,'[{\"sign\":\"+\",\"power\":5,\"count\":16}]',56.00,896.00,'dfgdshbs','2026-09-07','fully-paid',NULL,896.00,0.00,3,'2026-09-07 10:18:31'),(4,53,92,445,NULL,NULL,45.00,20025.00,'fdhdfn','2026-09-07','partially-paid',NULL,13109.00,6916.00,3,'2026-09-07 10:20:58'),(5,53,92,56,NULL,NULL,566.73,31736.88,'bvcgcuc u','2026-09-07','fully-paid','ckc',31736.88,0.00,3,'2026-09-07 14:10:45'),(6,53,92,76,NULL,NULL,66.81,5077.56,'gihgiy','2026-09-07','fully-paid','ugfiuf',5077.56,0.00,3,'2026-09-07 14:11:34'),(7,53,91,20,4.00,'[{\"sign\":\"+\",\"power\":4,\"count\":10},{\"sign\":\"-\",\"power\":2,\"count\":10}]',500.00,10000.00,'kvkichj','2026-09-07','partially-paid',NULL,685.00,9315.00,3,'2026-09-07 16:30:43'),(8,53,91,10,6.00,'[{\"sign\":\"+\",\"power\":6,\"count\":10}]',66.00,660.00,'lkbhogv','2026-09-07','unpaid',NULL,0.00,660.00,3,'2026-09-07 16:32:23'),(9,53,91,20,3.00,'[{\"sign\":\"+\",\"power\":3,\"count\":10},{\"sign\":\"-\",\"power\":1,\"count\":10}]',78.00,1560.00,'bjb','2026-09-07','fully-paid','m,bkjgk',1560.00,0.00,3,'2026-09-07 16:33:35'),(10,52,93,5,NULL,NULL,5.82,29.10,'jhvfyfyu','2026-09-07','unpaid',NULL,0.00,29.10,3,'2026-09-07 17:09:27'),(11,53,91,10,2.00,'[{\"sign\":\"+\",\"power\":2,\"count\":4},{\"sign\":\"+\",\"power\":5,\"count\":4},{\"sign\":\"+\",\"power\":4,\"count\":2}]',567.00,5670.00,'jhjlgo','2026-09-08','unpaid',NULL,0.00,5670.00,3,'2026-09-08 06:37:53'),(12,53,91,1,5.00,'[{\"sign\":\"+\",\"power\":5,\"count\":1}]',4.00,4.00,',bkjg','2026-09-16','fully-paid','cvukf,',4.00,0.00,3,'2026-09-16 06:08:15');
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
  KEY `fk_supplier_item_item` (`item_id`),
  KEY `fk_supplier_item_user` (`authorized_by`),
  KEY `idx_supplier_authorized_supplier_item` (`supplier_id`,`item_id`),
  CONSTRAINT `fk_supplier_item_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_item_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_item_user` FOREIGN KEY (`authorized_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_authorized_items`
--

LOCK TABLES `supplier_authorized_items` WRITE;
/*!40000 ALTER TABLE `supplier_authorized_items` DISABLE KEYS */;
INSERT INTO `supplier_authorized_items` VALUES (1,52,70,'2026-09-06',12,'months','inactive','fun','2026-09-06 21:24:37',2,'2026-09-06 14:41:24'),(2,52,91,'2026-09-06',10,'months','inactive','for bfun','2026-09-06 21:22:07',2,'2026-09-06 15:03:18'),(3,52,70,'2026-09-07',12,'months','active',NULL,NULL,2,'2026-09-07 07:14:28'),(4,53,92,'2026-09-07',12,'months','active',NULL,NULL,2,'2026-09-07 08:48:53'),(5,53,91,'2026-09-07',12,'months','active',NULL,NULL,2,'2026-09-07 16:29:29'),(6,52,93,'2026-09-07',12,'months','active',NULL,NULL,2,'2026-09-07 16:55:40');
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
  UNIQUE KEY `uq_supplier_payment_check_number` (`check_number`),
  KEY `fk_payment_user` (`recorded_by`),
  KEY `idx_supplier_payment_supplier` (`supplier_id`),
  KEY `idx_supplier_payment_receipt` (`receipt_id`),
  KEY `idx_supplier_payment_date` (`payment_date`),
  CONSTRAINT `fk_payment_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `stock_receipts` (`id`),
  CONSTRAINT `fk_payment_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_payments`
--

LOCK TABLES `supplier_payments` WRITE;
/*!40000 ALTER TABLE `supplier_payments` DISABLE KEYS */;
INSERT INTO `supplier_payments` VALUES (1,53,6,4510.56,'digid;LMD',NULL,'2026-09-07',NULL,3,'2026-09-07 14:42:36'),(2,53,4,675.00,'JBKOB',NULL,'2026-09-07',NULL,3,'2026-09-07 14:42:50'),(3,53,4,6787.00,'vyufyuc',NULL,'2026-09-07',NULL,3,'2026-09-07 14:56:37'),(4,53,4,5647.00,'dsgrsh',NULL,'2026-09-07',NULL,3,'2026-09-07 15:42:15'),(5,52,3,567.00,'nohuiog',NULL,'2026-09-07',NULL,3,'2026-09-07 15:49:55'),(6,52,3,329.00,'kbuyuy',NULL,'2026-09-07',NULL,3,'2026-09-07 15:50:07'),(7,52,2,45.00,'hgiufv',NULL,'2026-09-07',NULL,3,'2026-09-07 15:54:35'),(8,52,1,56.00,'sdfhdr',NULL,'2026-09-07',NULL,3,'2026-09-07 15:57:38'),(9,52,1,56.00,'hjfyi',NULL,'2026-09-07',NULL,3,'2026-09-07 15:59:26'),(10,52,1,67.00,'dsg',NULL,'2026-09-07',NULL,3,'2026-09-07 16:17:07'),(11,53,7,685.00,'bjkvv',NULL,'2026-09-07',NULL,3,'2026-09-07 16:31:24'),(12,52,1,267.80,'kjgfui',NULL,'2026-09-08',NULL,3,'2026-09-08 03:18:43');
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
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (52,'suki co','Sunitha Sivakumaran','sunithasivakumaran1@gmail.com','0763121801','no 116\r\nMusic college road,','2026-09-06',12,'months','active',NULL,NULL,2,'2026-09-06 14:41:23','2026-09-06 15:51:20'),(53,'suki and j','Sunitha Sivakumaran','sunithasivakumaran1@gmail.com','0763121801','no 116\r\nMusic college road,','2026-09-07',12,'months','active',NULL,NULL,2,'2026-09-07 08:48:53','2026-09-07 08:48:53');
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'low_stock_threshold','10','integer','general','Minimum stock quantity before a low-stock alert is triggered',NULL,'2026-09-03 05:41:05','2026-09-03 05:41:05'),(2,'session_timeout_minutes','30','integer','general','Minutes of inactivity before automatic logout',NULL,'2026-09-03 05:41:05','2026-09-03 05:41:05'),(3,'max_failed_logins','5','integer','general','Maximum failed login attempts before an account is locked',NULL,'2026-09-03 05:41:05','2026-09-03 05:41:05'),(4,'audit_retention_years','5','integer','general','Minimum years audit logs are retained',NULL,'2026-09-03 05:41:05','2026-09-03 05:41:05'),(5,'notify_low_stock','true','boolean','notification','Send alert when item stock drops below threshold',NULL,'2026-09-03 05:41:05','2026-09-03 05:41:05'),(6,'notify_pending_approval','true','boolean','notification','Notify Admin of pending registration or request',NULL,'2026-09-03 05:41:05','2026-09-03 05:41:05'),(7,'notify_payment_due','true','boolean','notification','Alert Store Keeper of outstanding supplier payments',NULL,'2026-09-03 05:41:05','2026-09-03 05:41:05'),(8,'email_notifications','true','boolean','notification','Send email notifications in addition to in-system alerts',NULL,'2026-09-03 05:41:05','2026-09-03 05:41:05');
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_notifications`
--

LOCK TABLES `user_notifications` WRITE;
/*!40000 ALTER TABLE `user_notifications` DISABLE KEYS */;
INSERT INTO `user_notifications` VALUES (1,2,'aid-request-decision-15','Aid request approved','AR-0015','Your aid request has been approved.','dashboard.php?page=my-aid-requests#aid-request-15','2026-09-11 23:04:34','2026-09-11 17:34:15'),(3,4,'aid-request-decision-12','Aid request approved','AR-0012','Your aid request has been approved.','dashboard.php?page=aid-requests#aid-request-12',NULL,'2026-09-11 17:51:31'),(4,13,'stock-quota-approved-GRB-20260915-E85F1A3B0F6D6DBE-13','Stock quota approved','GRB-20260915-E85F1A3B0F6D6DBE allocated to your division','2 allocations - 105 total units; awaiting Store Keeper release.','dashboard.php?page=pool-quota#goods-request-3','2026-09-15 20:14:29','2026-09-15 14:43:13'),(5,2,'stock-quota-decision-GRB-20260915-E85F1A3B0F6D6DBE','Stock quota request update','GRB-20260915-E85F1A3B0F6D6DBE approved','2 allocations - 105 total units','dashboard.php?page=my-goods-requests#goods-request-3','2026-09-15 20:25:39','2026-09-15 14:43:13'),(6,2,'stock-quota-decision-GRB-20260912-1AA73DFEA0D3D98D','Stock quota request update','GRB-20260912-1AA73DFEA0D3D98D rejected','1 allocations - 5 total units','dashboard.php?page=my-goods-requests#goods-request-2','2026-09-15 20:25:38','2026-09-15 14:43:36'),(7,2,'goods-dispatched-3','Stock quota request update','GR-0003 released to you','The Store Keeper released the approved stock quota to you.','dashboard.php?page=my-goods-requests#goods-request-3','2026-09-15 20:25:36','2026-09-15 14:45:22'),(8,2,'goods-dispatched-4','Stock quota request update','GR-0004 released to you','The Store Keeper released the approved stock quota to you.','dashboard.php?page=my-goods-requests#goods-request-4','2026-09-15 20:25:30','2026-09-15 14:45:35'),(9,13,'goods-pool-allocation-4','Stock allocated','New stock quota added to your pool','wheelchair × 100 was assigned by the Subject Officer.','dashboard.php?page=pool-quota#goods-request-4','2026-09-15 20:26:38','2026-09-15 14:55:56'),(10,13,'goods-pool-allocation-3','Stock allocated','New stock quota added to your pool','hearing aid × 5 was assigned by the Subject Officer.','dashboard.php?page=pool-quota#goods-request-3','2026-09-15 20:26:14','2026-09-15 14:56:02');
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System Administrator','admin@widms.gov',NULL,NULL,NULL,NULL,NULL,'$2y$10$X0FwRht7Q0C.JqXyIflzpeh4Y3ELemZy701MZT6m9blGcTsOioY2i','admin','active',NULL,NULL,NULL,'2026-09-16 13:27:59','2026-09-03 05:41:05','2026-09-16 07:57:59'),(2,'Subject Officer','subject@widms.gov',NULL,NULL,NULL,NULL,NULL,'$2y$10$Eb3LEL4FIqtTe1KGFcXsCOttfqZYJ5kln1nOxHGArw34pdVqFUi/m','subject-officer','active',NULL,NULL,NULL,'2026-09-16 13:28:21','2026-09-03 05:41:05','2026-09-16 07:58:21'),(3,'Store Keeper','store@widms.gov',NULL,NULL,NULL,NULL,NULL,'$2y$10$ATASnZ59iJlNsRazDaC.aulIweRZb1vMzzcSqGCaAC7JyGDxdOhFi','store-keeper','active',NULL,NULL,NULL,'2026-09-16 12:16:55','2026-09-03 05:41:05','2026-09-16 06:46:55'),(4,'Social Service Officer','social@widms.gov',NULL,NULL,NULL,NULL,NULL,'$2y$10$aDf7T0XuX15L.ErDBSBe2OO68H/o0af8wOsmbcMfpPYEzNmky4Btq','social-service-officer','inactive','he doesnt has ds division',1,'2026-09-11 14:41:10','2026-09-11 10:02:32','2026-09-03 05:41:05','2026-09-11 09:11:10'),(13,'Sunitha Sivakumaran','sunithasivakumaran1@gmail.com','0763121801','Hikkaduwa',1,13,NULL,'$2y$10$1CRWhaj28MudZz6BGA1XDOtKlAN84yLsF8tPMx.C4PvRhAx6F89tq','social-service-officer','active',NULL,NULL,NULL,'2026-09-16 13:27:20','2026-09-03 07:49:52','2026-09-16 07:57:20'),(14,'Sunitha','sunithasivakumaran8@gmail.com','0763121806','Akmeemana',1,16,NULL,'$2y$10$CCgaCVrqL68DNC/1WEKfiuxGx.dvuJzeHGAaqiXLTDScsC3UQRn2i','social-service-officer','active',NULL,NULL,NULL,NULL,'2026-09-03 07:53:47','2026-09-03 07:53:47'),(15,'Sunitha Sivakumaran','sunithasivakumaran98@gmail.com','0763121801','Lunugamwehera',3,40,NULL,'$2y$10$2ToieavtdYkZkp4K3c4bje398rnye6o5itTlc0BTLgvL8DKBfWciC','social-service-officer','active',NULL,NULL,NULL,'2026-09-15 22:51:10','2026-09-11 06:06:02','2026-09-15 17:21:10'),(16,'Sunitha Sivakumaran','sunithasivakumaran67@gmail.com','0763121801',NULL,NULL,NULL,NULL,'$2y$10$.vS/XzxCLYUM7PLFEey32epRHg4ph/3bsQsRs8tCBVoqAj73ZZQhm','store-keeper','active',NULL,NULL,NULL,NULL,'2026-09-11 17:55:43','2026-09-11 17:55:43');
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
INSERT INTO `widms_data_migrations` VALUES ('2026-09-clear-default-disabilities','2026-09-05 08:49:52'),('2026-09-clear-remaining-inventory','2026-09-05 08:52:05'),('2026-09-item-eligibility-redesign','2026-09-05 08:32:29');
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

-- Dump completed on 2026-09-16 15:04:14
