-- MySQL dump 10.13  Distrib 5.7.42, for Linux (x86_64)
--
-- Host: localhost    Database: bill_of_lading
-- ------------------------------------------------------
-- Server version	5.7.42

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bills_of_lading`
--

DROP TABLE IF EXISTS `bills_of_lading`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills_of_lading` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bl_number` varchar(50) NOT NULL,
  `bl_type` enum('TBL','Non-TBL') NOT NULL DEFAULT 'TBL',
  `last_return_date` date NOT NULL,
  `item_description` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('draft','active','completed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bl_number` (`bl_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `bills_of_lading_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bills_of_lading`
--

LOCK TABLES `bills_of_lading` WRITE;
/*!40000 ALTER TABLE `bills_of_lading` DISABLE KEYS */;
INSERT INTO `bills_of_lading` VALUES (1,'AMC2459233B','TBL','2026-06-05','INDIAN ORIGIN SOYABEAN MEAL NON- GMO\r\nFOR ANIMAL FEED CONSUMPTION\r\nORIGIN: INDIA',1,'active','2026-05-22 07:59:00','2026-05-22 07:59:00');
/*!40000 ALTER TABLE `bills_of_lading` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bl_items`
--

DROP TABLE IF EXISTS `bl_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bl_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bl_id` int(11) NOT NULL,
  `container_number` varchar(100) NOT NULL,
  `agent_seal_number` varchar(100) DEFAULT NULL,
  `sgs_seal_number` varchar(100) DEFAULT NULL,
  `number_of_bags` int(11) NOT NULL,
  `gross_weight` decimal(10,3) NOT NULL,
  `net_weight` decimal(10,3) NOT NULL,
  `dispatch_status` enum('pending','transit','received','rejected') DEFAULT 'pending',
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `return_status` enum('not_applicable','pending_return','in_transit_return','returned') DEFAULT 'not_applicable',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bl_id` (`bl_id`),
  CONSTRAINT `bl_items_ibfk_1` FOREIGN KEY (`bl_id`) REFERENCES `bills_of_lading` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bl_items`
--

LOCK TABLES `bl_items` WRITE;
/*!40000 ALTER TABLE `bl_items` DISABLE KEYS */;
INSERT INTO `bl_items` VALUES (1,1,'SEKU5498944','M0731662','',550,28.360,25.360,'received','2026-05-22 08:14:24','returned','2026-05-22 07:59:00'),(2,1,'CMAU8856894','M0745210','',550,28.300,25.300,'pending',NULL,'not_applicable','2026-05-22 07:59:00'),(3,1,'RFCU5055974','M0731700','',550,27.930,24.930,'pending',NULL,'not_applicable','2026-05-22 07:59:00');
/*!40000 ALTER TABLE `bl_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dispatches`
--

DROP TABLE IF EXISTS `dispatches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dispatches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bl_id` int(11) NOT NULL,
  `bl_item_id` int(11) NOT NULL,
  `transporter_name` varchar(150) NOT NULL,
  `truck_number` varchar(50) NOT NULL,
  `destination` varchar(200) NOT NULL,
  `clearing_agent_dnote` varchar(100) DEFAULT NULL,
  `transporter_dnote` varchar(100) DEFAULT NULL,
  `status` enum('transit','received','rejected') DEFAULT 'transit',
  `dispatched_by` int(11) DEFAULT NULL,
  `dispatched_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `received_at` timestamp NULL DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `rejection_reason` text,
  `notes` text,
  `redispatch_count` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `bl_id` (`bl_id`),
  KEY `bl_item_id` (`bl_item_id`),
  KEY `dispatched_by` (`dispatched_by`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `dispatches_ibfk_1` FOREIGN KEY (`bl_id`) REFERENCES `bills_of_lading` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dispatches_ibfk_2` FOREIGN KEY (`bl_item_id`) REFERENCES `bl_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dispatches_ibfk_3` FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dispatches_ibfk_4` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dispatches`
--

LOCK TABLES `dispatches` WRITE;
/*!40000 ALTER TABLE `dispatches` DISABLE KEYS */;
INSERT INTO `dispatches` VALUES (1,1,1,'Mahindra','KDV 896R - ZE399F','UNGAR DAKAR',NULL,NULL,'received',1,'2026-05-22 08:14:24','2026-05-22 08:14:52',1,NULL,NULL,0);
/*!40000 ALTER TABLE `dispatches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `empty_container_returns`
--

DROP TABLE IF EXISTS `empty_container_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empty_container_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bl_id` int(11) NOT NULL,
  `bl_item_id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `bl_type` enum('TBL','Non-TBL') NOT NULL,
  `tbl_depot` varchar(200) DEFAULT NULL,
  `tbl_date_in` date DEFAULT NULL,
  `nontbl_transporter` varchar(150) DEFAULT NULL,
  `nontbl_truck_number` varchar(50) DEFAULT NULL,
  `nontbl_final_depot` varchar(200) DEFAULT NULL,
  `nontbl_date_in` date DEFAULT NULL,
  `return_status` enum('pending_return','in_transit_return','returned') DEFAULT 'pending_return',
  `created_by` int(11) DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bl_id` (`bl_id`),
  KEY `bl_item_id` (`bl_item_id`),
  KEY `dispatch_id` (`dispatch_id`),
  KEY `created_by` (`created_by`),
  KEY `completed_by` (`completed_by`),
  CONSTRAINT `empty_container_returns_ibfk_1` FOREIGN KEY (`bl_id`) REFERENCES `bills_of_lading` (`id`) ON DELETE CASCADE,
  CONSTRAINT `empty_container_returns_ibfk_2` FOREIGN KEY (`bl_item_id`) REFERENCES `bl_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `empty_container_returns_ibfk_3` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `empty_container_returns_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `empty_container_returns_ibfk_5` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empty_container_returns`
--

LOCK TABLES `empty_container_returns` WRITE;
/*!40000 ALTER TABLE `empty_container_returns` DISABLE KEYS */;
INSERT INTO `empty_container_returns` VALUES (1,1,1,1,'TBL','NAIROBI EPZ','2026-05-31',NULL,NULL,NULL,NULL,'returned',1,1,'2026-05-22 08:15:03','2026-05-22 08:15:20');
/*!40000 ALTER TABLE `empty_container_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `type` enum('destination','depot','mixed') DEFAULT 'mixed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `locations`
--

LOCK TABLES `locations` WRITE;
/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transporters`
--

DROP TABLE IF EXISTS `transporters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transporters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transporters`
--

LOCK TABLES `transporters` WRITE;
/*!40000 ALTER TABLE `transporters` DISABLE KEYS */;
/*!40000 ALTER TABLE `transporters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','ict@rvp.co.ke','$2y$10$AvZHZfOiBPrw8IA27giN0.5R2BfDs6Tcrb2VZCSwr/xQ0OUyk.m3K','System Administrator','admin','2026-05-19 09:54:56');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-25  9:25:41
