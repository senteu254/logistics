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
  `item_description` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('draft','active','completed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bl_number` (`bl_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `bills_of_lading_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bills_of_lading`
--

LOCK TABLES `bills_of_lading` WRITE;
/*!40000 ALTER TABLE `bills_of_lading` DISABLE KEYS */;
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
  `number_of_bags` int(11) NOT NULL,
  `sgs_seal_number` varchar(100) DEFAULT NULL,
  `gross_weight` decimal(10,2) NOT NULL,
  `net_weight` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `empty_return_status` enum('pending','in_transit','completed') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `bl_id` (`bl_id`),
  CONSTRAINT `bl_items_ibfk_1` FOREIGN KEY (`bl_id`) REFERENCES `bills_of_lading` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bl_items`
--

LOCK TABLES `bl_items` WRITE;
/*!40000 ALTER TABLE `bl_items` DISABLE KEYS */;
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
  `clearing_agent_dnote` varchar(100) DEFAULT NULL,
  `transporter_dnote` varchar(100) DEFAULT NULL,
  `destination` varchar(200) NOT NULL,
  `status` enum('transit','received','rejected') DEFAULT 'transit',
  `dispatched_by` int(11) DEFAULT NULL,
  `dispatched_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `received_at` timestamp NULL DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `rejection_reason` text,
  `notes` text,
  `redirected_destination` varchar(200) DEFAULT NULL,
  `redirected_at` timestamp NULL DEFAULT NULL,
  `redirect_count` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `bl_id` (`bl_id`),
  KEY `bl_item_id` (`bl_item_id`),
  KEY `dispatched_by` (`dispatched_by`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `dispatches_ibfk_1` FOREIGN KEY (`bl_id`) REFERENCES `bills_of_lading` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dispatches_ibfk_2` FOREIGN KEY (`bl_item_id`) REFERENCES `bl_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dispatches_ibfk_3` FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dispatches_ibfk_4` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dispatches`
--

LOCK TABLES `dispatches` WRITE;
/*!40000 ALTER TABLE `dispatches` DISABLE KEYS */;
INSERT INTO `dispatches` VALUES (1,3,6,'Toprise','KDG 123A',NULL,NULL,'Unga','received',1,'2026-05-13 11:41:35','2026-05-13 12:06:18',1,NULL,NULL,NULL,NULL,0),(2,4,12,'TOPRISE','KBS860W/ZA8418','124123','3777','UNGA FARM CARE','received',1,'2026-05-13 12:17:59','2026-05-13 12:19:09',1,NULL,NULL,NULL,NULL,0);
/*!40000 ALTER TABLE `dispatches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `empty_returns`
--

DROP TABLE IF EXISTS `empty_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empty_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dispatch_id` int(11) NOT NULL,
  `bl_id` int(11) NOT NULL,
  `bl_item_id` int(11) NOT NULL,
  `bl_type` enum('TBL','Non-TBL') NOT NULL,
  `date_in` date DEFAULT NULL,
  `depot` varchar(200) DEFAULT NULL,
  `transporter` varchar(150) DEFAULT NULL,
  `local_depot` varchar(200) DEFAULT NULL,
  `return_status` enum('in_transit','completed') DEFAULT 'in_transit',
  `returned_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `dispatch_id` (`dispatch_id`),
  KEY `bl_id` (`bl_id`),
  KEY `bl_item_id` (`bl_item_id`),
  KEY `created_by` (`created_by`),
  KEY `completed_by` (`completed_by`),
  CONSTRAINT `empty_returns_ibfk_1` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `empty_returns_ibfk_2` FOREIGN KEY (`bl_id`) REFERENCES `bills_of_lading` (`id`) ON DELETE CASCADE,
  CONSTRAINT `empty_returns_ibfk_3` FOREIGN KEY (`bl_item_id`) REFERENCES `bl_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `empty_returns_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `empty_returns_ibfk_5` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empty_returns`
--

LOCK TABLES `empty_returns` WRITE;
/*!40000 ALTER TABLE `empty_returns` DISABLE KEYS */;
/*!40000 ALTER TABLE `empty_returns` ENABLE KEYS */;
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@example.com','$2y$10$4ptki7G6tTrWxzBBSxRT9uxjjzhwTGStKXhEfHmxLSpuJmgepDoaC','System Administrator','2026-05-14 07:28:37');
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

-- Dump completed on 2026-05-14 14:28:48
