-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: tokaido.proxy.rlwy.net    Database: anipet_db
-- ------------------------------------------------------
-- Server version	9.4.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `adoption_applications`
--

DROP TABLE IF EXISTS `adoption_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adoption_applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pet_id` int NOT NULL,
  `user_id` int NOT NULL,
  `applicant_name` varchar(150) NOT NULL,
  `message` text,
  `qr_code` varchar(255) DEFAULT NULL,
  `qr_data` varchar(255) DEFAULT NULL,
  `completed_photo` varchar(255) DEFAULT NULL,
  `id_documents` text,
  `house_photos` text,
  `form_data` text,
  `terms_accepted` tinyint(1) NOT NULL DEFAULT '0',
  `privacy_consent` text,
  `interview_datetime` datetime DEFAULT NULL,
  `ready_pickup_at` datetime DEFAULT NULL,
  `pickup_deadline` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `admin_notes` text,
  `screened_by` int DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pet_id` (`pet_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `adoption_applications_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adoption_applications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `adoption_applications`
--

LOCK TABLES `adoption_applications` WRITE;
/*!40000 ALTER TABLE `adoption_applications` DISABLE KEYS */;
INSERT INTO `adoption_applications` VALUES (1,6,3,'Janessa Pilapil','I want to adopt this pet',NULL,NULL,NULL,'[\"uploads\\/id_6a5eaa965ee438.23767905_id_document.jpg\"]','[\"uploads\\/house_6a5eaa965ef6c5.96647284_house_photo_1.jpg\"]','{\"address\":\"Novaliches Quezon city\",\"phone\":\"0992 894 6136\",\"email\":\"maepilapil522@gmail.com\",\"birth_date\":\"2004-09-05\",\"occupation\":\"BPO\",\"company\":\"IntouchCX\",\"social_profile\":\"Janessa\",\"status\":\"Single\",\"pronouns\":\"She\\/her\",\"prompted_by\":\"Website\",\"adopted_before\":\"Yes\",\"interaction_method\":\"Email\",\"contact_preference\":\"Email\",\"zoom_details\":\"\",\"alt_name\":\"Basil Defeo\",\"alt_relation\":\"Husband\",\"alt_phone\":\"09542904524\",\"alt_email\":\"basildef@gmail.com\",\"looking_for\":\"Cat\",\"specific_animal\":\"NO\",\"ideal_pet\":\"I want something different \",\"building_type\":\"House\",\"do_rent\":\"Yes\",\"move_plan\":\"Bring the Cat\",\"household\":\"Spouse\",\"allergic\":\"No\",\"daily_caregiver\":\"Me\",\"financial_responsible\":\"Both of us\",\"pet_sitter\":\"Me\",\"hours_left\":\"5hours\",\"family_support\":\"Yes\",\"family_explain\":\"they want it too\",\"other_pets\":\"Yes\",\"past_pets\":\"Yes\",\"preferred_date\":\"\",\"preferred_time\":\"\",\"will_visit\":\"Yes\"}',1,'I agree to the terms and conditions and consent to the use of my private information solely for the adoption application process. I understand that if I return the adopted pet, a return penalty of ₱1000.00 applies, per shelter policy.','2026-07-21 08:07:00',NULL,NULL,NULL,NULL,'',1,'rejected','2026-07-20 23:09:10'),(2,6,3,'Janessa Pilapil','','qrcodes/qr_app_2_1784595495.png','ANIPET|APP:2|PET:6|DATE:2026-07-21',NULL,'[\"uploads\\/id_6a5ec357300118.60834666_id_document.jpg\"]','[\"uploads\\/house_6a5ec357306634.12951351_house_photo_1.jpg\",\"uploads\\/house_6a5ec357306c29.20128998_house_photo_2.jpg\"]','{\"address\":\"Novaliches Quezon city\",\"phone\":\"0992 894 6136\",\"email\":\"maepilapil522@gmail.com\",\"birth_date\":\"2000-07-12\",\"occupation\":\"1\",\"company\":\"1\",\"social_profile\":\"1\",\"status\":\"1\",\"pronouns\":\"1\",\"prompted_by\":\"1\",\"adopted_before\":\"1\",\"interaction_method\":\"Email\",\"contact_preference\":\"Email\",\"zoom_details\":\"\",\"alt_name\":\"\",\"alt_relation\":\"\",\"alt_phone\":\"\",\"alt_email\":\"\",\"looking_for\":\"1\",\"specific_animal\":\"1\",\"ideal_pet\":\"1\",\"building_type\":\"1\",\"do_rent\":\"1\",\"move_plan\":\"1\",\"household\":\"1\",\"allergic\":\"1\",\"daily_caregiver\":\"1\",\"financial_responsible\":\"1\",\"pet_sitter\":\"1\",\"hours_left\":\"1\",\"family_support\":\"1\",\"family_explain\":\"1\",\"other_pets\":\"1\",\"past_pets\":\"1\",\"preferred_date\":\"\",\"preferred_time\":\"\",\"will_visit\":\"1\"}',1,'I agree to the terms and conditions and consent to the use of my private information solely for the adoption application process. I understand that if I return the adopted pet, a return penalty of ₱1000.00 applies, per shelter policy.',NULL,NULL,NULL,NULL,NULL,'Interview requested by applicant',NULL,'screening','2026-07-21 00:54:47'),(3,6,3,'Janessa Pilapil','',NULL,NULL,NULL,'[\"uploads\\/id_6a5ec72de1eaa6.29819093_id_document.jpg\"]','[\"uploads\\/house_6a5ec72de1f842.87503052_house_photo_2.jpg\",\"uploads\\/house_6a5ec72de21571.56625061_house_photo_3.jpg\"]','{\"address\":\"Novaliches Quezon city\",\"phone\":\"0992 894 6136\",\"email\":\"maepilapil522@gmail.com\",\"birth_date\":\"2003-07-09\",\"occupation\":\"\",\"company\":\"1\",\"social_profile\":\"1\",\"status\":\"1\",\"pronouns\":\"1\",\"prompted_by\":\"11\",\"adopted_before\":\"1\",\"interaction_method\":\"Email\",\"contact_preference\":\"Email\",\"zoom_details\":\"\",\"alt_name\":\"1\",\"alt_relation\":\"1\",\"alt_phone\":\"\",\"alt_email\":\"\",\"looking_for\":\"1\",\"specific_animal\":\"1\",\"ideal_pet\":\"1\",\"building_type\":\"1\",\"do_rent\":\"1\",\"move_plan\":\"1\",\"household\":\"1\",\"allergic\":\"1\",\"daily_caregiver\":\"1\",\"financial_responsible\":\"1\",\"pet_sitter\":\"1\",\"hours_left\":\"1\",\"family_support\":\"1\",\"family_explain\":\"1\",\"other_pets\":\"11\",\"past_pets\":\"1\",\"preferred_date\":\"\",\"preferred_time\":\"\",\"will_visit\":\"1\"}',1,'I agree to the terms and conditions and consent to the use of my private information solely for the adoption application process. I understand that if I return the adopted pet, a return penalty of ₱1000.00 applies, per shelter policy.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending','2026-07-21 01:11:09'),(4,6,3,'Janessa Pilapil','Liie this pet','qrcodes/qr_app_4_1785193482.png','ANIPET|APP:4|PET:6|DATE:2026-07-27',NULL,'[\"uploads\\/id_6a5ec8cc971da8.78310136_id_document.jpg\"]','[\"uploads\\/house_6a5ec8cc973989.32713673_house_photo_1.jpg\",\"uploads\\/house_6a5ec8cc974303.05606203_house_photo_2.jpg\",\"uploads\\/house_6a5ec8cc975685.55561526_house_photo_3.jpg\"]','{\"address\":\"Novaliches Quezon city\",\"phone\":\"0992 894 6136\",\"email\":\"maepilapil522@gmail.com\",\"birth_date\":\"2004-05-09\",\"occupation\":\"BPO\",\"company\":\"IntouchCX\",\"social_profile\":\"Janessa\",\"status\":\"Married \",\"pronouns\":\"She\\/her\",\"prompted_by\":\"website\",\"adopted_before\":\"Yes\",\"interaction_method\":\"Email\",\"contact_preference\":\"Email\",\"zoom_details\":\"\",\"alt_name\":\"Basil\",\"alt_relation\":\"Husband\",\"alt_phone\":\"09229808613\",\"alt_email\":\"basildef@gmail.com\",\"looking_for\":\"Cat\",\"specific_animal\":\"Yes\",\"ideal_pet\":\"Small\",\"building_type\":\"House\",\"do_rent\":\"Yes\",\"move_plan\":\"Bring the pet\",\"household\":\"Spouse\",\"allergic\":\"Yes\",\"daily_caregiver\":\"Me\",\"financial_responsible\":\"Me\",\"pet_sitter\":\"Me\",\"hours_left\":\"5hours\",\"family_support\":\"Yes\",\"family_explain\":\"Good pets are better\",\"other_pets\":\"Yes\",\"past_pets\":\"Yes\",\"preferred_date\":\"\",\"preferred_time\":\"\",\"will_visit\":\"Yes\"}',1,'I agree to the terms and conditions and consent to the use of my private information solely for the adoption application process. I understand that if I return the adopted pet, a return penalty of ₱1000.00 applies, per shelter policy.',NULL,NULL,NULL,NULL,NULL,'',1,'approved','2026-07-21 01:18:04'),(5,1,6,'Basil Defeo','Will adopt the pet',NULL,NULL,'uploads/completed_photos/completed_5_1785469999.jpg','[\"uploads\\/id_6a5ecabe282915.52023751_id_document.jpg\"]','[\"uploads\\/house_6a5ecabe283439.31118226_house_photo_1.jpg\"]','{\"address\":\"Bagumbong Caloocan\",\"phone\":\"09219809613\",\"email\":\"basildef@gmail.com\",\"birth_date\":\"2004-09-05\",\"occupation\":\"BPO\",\"company\":\"IntouchCX \",\"social_profile\":\"Basil\",\"status\":\"Married\",\"pronouns\":\"She\\/her\",\"prompted_by\":\"Friends\",\"adopted_before\":\"Yes\",\"interaction_method\":\"Email\",\"contact_preference\":\"Email\",\"zoom_details\":\"\",\"alt_name\":\"janessa\",\"alt_relation\":\"Spouse\",\"alt_phone\":\"0992 894 6136 \",\"alt_email\":\"maepilapil522@gmail.com\",\"looking_for\":\"Cat\",\"specific_animal\":\"No\",\"ideal_pet\":\"Small\",\"building_type\":\"House\",\"do_rent\":\"Yes\",\"move_plan\":\"BRING my pet\",\"household\":\"Spouse\",\"allergic\":\"No\",\"daily_caregiver\":\"Me\",\"financial_responsible\":\"Me\",\"pet_sitter\":\"Me\",\"hours_left\":\"5hours\",\"family_support\":\"Yes\",\"family_explain\":\"Wife likes it too\",\"other_pets\":\"Yes\",\"past_pets\":\"Yes\",\"preferred_date\":\"\",\"preferred_time\":\"\",\"will_visit\":\"Yes\"}',1,'I agree to the terms and conditions and consent to the use of my private information solely for the adoption application process. I understand that if I return the adopted pet, a return penalty of ₱1000.00 applies, per shelter policy.','2026-07-28 23:35:00','2026-07-31 05:05:53','2026-08-05 23:59:59',NULL,NULL,'',1,'ready_pickup','2026-07-21 01:26:22'),(6,7,3,'Janessa Pilapil','test ko to',NULL,NULL,NULL,'[\"uploads\\/id_6a619eef2f4688.75762151_id_document.jpg\"]','[\"uploads\\/house_6a619eef2f82a0.95608400_house_photo_2.jpg\",\"uploads\\/house_6a619eef2f8ba3.85184441_house_photo_3.jpg\"]','{\"address\":\"Novaliches Quezon city\",\"phone\":\"0992 894 6136\",\"email\":\"maepilapil522@gmail.com\",\"birth_date\":\"2004-07-09\",\"occupation\":\"test\",\"company\":\"test\",\"social_profile\":\"test\",\"status\":\"tets\",\"pronouns\":\"test\",\"prompted_by\":\"test\",\"adopted_before\":\"test\",\"interaction_method\":\"Email\",\"contact_preference\":\"Email\",\"zoom_details\":\"\",\"alt_name\":\"tets\",\"alt_relation\":\"test\",\"alt_phone\":\"tets\",\"alt_email\":\"test\",\"looking_for\":\"test\",\"specific_animal\":\"test\",\"ideal_pet\":\"test\",\"building_type\":\"tets\",\"do_rent\":\"test\",\"move_plan\":\"test\",\"household\":\"test\",\"allergic\":\"test\",\"daily_caregiver\":\"test\",\"financial_responsible\":\"test\",\"pet_sitter\":\"test\",\"hours_left\":\"test\",\"family_support\":\"test\",\"family_explain\":\"test\",\"other_pets\":\"test\",\"past_pets\":\"test\",\"preferred_date\":\"\",\"preferred_time\":\"\",\"will_visit\":\"yes\"}',1,'I agree to the terms and conditions and consent to the use of my private information solely for the adoption application process. I understand that if I return the adopted pet, a return penalty of ₱1000.00 applies, per shelter policy.','2026-08-03 01:24:00',NULL,NULL,NULL,NULL,'',5,'screening','2026-07-23 04:56:15'),(7,5,10,'Boni Parts','Let me adopt this pet','qrcodes/qr_app_7_1785210662.png','ANIPET|APP:7|PET:5|DATE:2026-07-28','uploads/completed_photos/completed_7_1785203276.jpg','[\"uploads\\/id_6a67ee584ed668.29454758_id_document.jpg\"]','[\"uploads\\/house_6a67ee584f1262.34274288_house_photo_1.jpg\",\"uploads\\/house_6a67ee584f1be5.62261340_house_photo_2.jpg\",\"uploads\\/house_6a67ee584f28d0.61175502_house_photo_3.jpg\"]','{\"address\":\"bagumbong \",\"phone\":\"+639917337008\",\"email\":\"boniparts036@gmail.com\",\"birth_date\":\"2004-09-05\",\"occupation\":\"BPO\",\"company\":\"IntouchCx \",\"social_profile\":\"Basil\",\"status\":\"single\",\"pronouns\":\"He\\/him\",\"prompted_by\":\"Friends\",\"adopted_before\":\"Yes\",\"interaction_method\":\"Email\",\"contact_preference\":\"Email\",\"zoom_details\":\"\",\"alt_name\":\"Basil\",\"alt_relation\":\"Friend\",\"alt_phone\":\"+639219809613\",\"alt_email\":\"basildef@gmail.com\",\"looking_for\":\"Bird\",\"specific_animal\":\"No\",\"ideal_pet\":\"Small, With wings\",\"building_type\":\"House\",\"do_rent\":\"Yes\",\"move_plan\":\"Move with the pet\",\"household\":\"Living Alone\",\"allergic\":\"No\",\"daily_caregiver\":\"Me\",\"financial_responsible\":\"Me\",\"pet_sitter\":\"Me\",\"hours_left\":\"10mins\",\"family_support\":\"Yes\",\"family_explain\":\"I like it\",\"other_pets\":\"No\",\"past_pets\":\"Yes\",\"preferred_date\":\"\",\"preferred_time\":\"\",\"will_visit\":\"Yes\"}',1,'I agree to the terms and conditions and consent to the use of my private information solely for the adoption application process. I understand that if I return the adopted pet, a return penalty of ₱1000.00 applies, per shelter policy.','2026-07-28 09:00:00',NULL,NULL,NULL,NULL,'',1,'approved','2026-07-27 23:48:40'),(8,7,11,'Adam Medina','',NULL,NULL,NULL,'[\"uploads\\/id_6a7003a9339474.68147251_id_document.jpg\"]','[\"uploads\\/house_6a7003a9365388.42559128_house_photo_1.jpg\",\"uploads\\/house_6a7003a9369ee9.31610315_house_photo_2.jpg\",\"uploads\\/house_6a7003a936e746.13046604_house_photo_3.jpg\"]','{\"address\":\"Caloocan City\",\"phone\":\"+639928946136\",\"email\":\"adam.medina1005@gmail.com\",\"birth_date\":\"2005-08-10\",\"occupation\":\"1\",\"company\":\"1\",\"social_profile\":\"1\",\"status\":\"1\",\"pronouns\":\"1\",\"prompted_by\":\"1\",\"adopted_before\":\"1\",\"interaction_method\":\"Email\",\"contact_preference\":\"Email\",\"zoom_details\":\"\",\"alt_name\":\"\",\"alt_relation\":\"\",\"alt_phone\":\"\",\"alt_email\":\"\",\"looking_for\":\"1\",\"specific_animal\":\"1\",\"ideal_pet\":\"1\",\"building_type\":\"1\",\"do_rent\":\"1\",\"move_plan\":\"1\",\"household\":\"1\",\"allergic\":\"1\",\"daily_caregiver\":\"1\",\"financial_responsible\":\"1\",\"pet_sitter\":\"1\",\"hours_left\":\"1\",\"family_support\":\"1\",\"family_explain\":\"\",\"other_pets\":\"1\",\"past_pets\":\"1\",\"preferred_date\":\"\",\"preferred_time\":\"\",\"will_visit\":\"1\"}',1,'I agree to the terms and conditions and consent to the use of my private information solely for the adoption application process. I understand that once the adoption is completed and the pet is released to me, responsibility and ownership of the pet transfer to me. AniPet and the pet pound are no longer liable for the pet\'s care, condition, or any events that occur after the completed adoption.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pending','2026-08-03 02:57:45'),(9,7,11,'Adam Medina','','qrcodes/qr_app_9_1785725958.png','ANIPET|APP:9|PET:7|DATE:2026-08-03',NULL,'[\"uploads\\/id_6a7003ac1b7b90.60069083_id_document.jpg\"]','[\"uploads\\/house_6a7003ac1d0b84.40416009_house_photo_1.jpg\",\"uploads\\/house_6a7003ac1d8f04.19251811_house_photo_2.jpg\",\"uploads\\/house_6a7003ac1e1b69.17916256_house_photo_3.jpg\"]','{\"address\":\"Caloocan City\",\"phone\":\"+639928946136\",\"email\":\"adam.medina1005@gmail.com\",\"birth_date\":\"2005-08-10\",\"occupation\":\"1\",\"company\":\"1\",\"social_profile\":\"1\",\"status\":\"1\",\"pronouns\":\"1\",\"prompted_by\":\"1\",\"adopted_before\":\"1\",\"interaction_method\":\"Email\",\"contact_preference\":\"Email\",\"zoom_details\":\"\",\"alt_name\":\"\",\"alt_relation\":\"\",\"alt_phone\":\"\",\"alt_email\":\"\",\"looking_for\":\"1\",\"specific_animal\":\"1\",\"ideal_pet\":\"1\",\"building_type\":\"1\",\"do_rent\":\"1\",\"move_plan\":\"1\",\"household\":\"1\",\"allergic\":\"1\",\"daily_caregiver\":\"1\",\"financial_responsible\":\"1\",\"pet_sitter\":\"1\",\"hours_left\":\"1\",\"family_support\":\"1\",\"family_explain\":\"\",\"other_pets\":\"1\",\"past_pets\":\"1\",\"preferred_date\":\"\",\"preferred_time\":\"\",\"will_visit\":\"1\"}',1,'I agree to the terms and conditions and consent to the use of my private information solely for the adoption application process. I understand that once the adoption is completed and the pet is released to me, responsibility and ownership of the pet transfer to me. AniPet and the pet pound are no longer liable for the pet\'s care, condition, or any events that occur after the completed adoption.','2026-08-04 10:00:00',NULL,NULL,NULL,NULL,'',5,'approved','2026-08-03 02:57:48');
/*!40000 ALTER TABLE `adoption_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `adoption_records`
--

DROP TABLE IF EXISTS `adoption_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adoption_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pet_id` int NOT NULL,
  `user_id` int NOT NULL,
  `adoption_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `qr_code_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pet_id` (`pet_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `adoption_records_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adoption_records_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `adoption_records`
--

LOCK TABLES `adoption_records` WRITE;
/*!40000 ALTER TABLE `adoption_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `adoption_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `pet_id` int DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `details` text,
  `scheduled_at` datetime DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `application_id` int DEFAULT NULL,
  `appointment_type` varchar(20) NOT NULL DEFAULT 'general',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `pet_id` (`pet_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (1,3,6,'Adoption Interview – Piercie','Adoption interview for Piercie.','2026-07-21 08:07:00','pending',1,'interview','2026-07-20 23:50:11'),(2,3,6,'Adoption Interview – Piercie','Adoption interview for Piercie.',NULL,'pending',2,'interview','2026-07-21 00:54:54'),(3,6,1,'Adoption Interview – Kuro','Adoption interview for Kuro.','2026-07-28 23:35:00','pending',5,'interview','2026-07-21 01:26:52'),(4,6,NULL,'Looking to adopt','I want to book an appointment to book a date and time to choose a pet from your shelter since there is not pet available in the app that I want','2026-07-25 15:00:00','pending',NULL,'general','2026-07-21 01:31:24'),(5,3,7,'Adoption Interview – Miming','Adoption interview for Miming.','2026-08-03 01:24:00','approved',6,'interview','2026-07-23 05:25:29'),(6,10,5,'Adoption Interview – lili, george, cici','Adoption interview for lili, george, cici.','2026-07-28 09:00:00','approved',7,'interview','2026-07-27 23:49:48'),(7,11,7,'Adoption Interview – Miming','Adoption interview for Miming.','2026-08-04 10:00:00','pending',9,'interview','2026-08-03 02:58:46');
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(120) NOT NULL,
  `target_type` varchar(120) DEFAULT NULL,
  `target_id` varchar(80) DEFAULT NULL,
  `details` text,
  `before_data` json DEFAULT NULL,
  `after_data` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'create_admin','user','1','Created admin anipet.adoption@gmail.com',NULL,NULL,'100.64.0.7','2026-07-20 22:55:01'),(2,1,'create_admin','user','2','Created admin demo@local.com',NULL,NULL,'100.64.0.22','2026-07-20 22:55:47'),(3,1,'update_admin','user','2','Updated admin demo@local.com',NULL,NULL,'100.64.0.23','2026-07-20 22:56:05'),(4,1,'update_admin','user','1','Updated admin anipet.adoption@gmail.com',NULL,NULL,'100.64.0.19','2026-07-20 22:56:17'),(5,1,'update_admin','user','2','Updated admin demo@local.com',NULL,NULL,'100.64.0.14','2026-07-20 22:57:15'),(6,1,'update_admin','user','2','Updated admin demo@local.com',NULL,NULL,'100.64.0.7','2026-07-20 22:58:10'),(7,1,'create_admin','user','5','Created admin anipet.adoption2026@gmail.com',NULL,NULL,'100.64.0.6','2026-07-20 23:00:26'),(8,1,'delete_admin','user','2','Deleted admin account',NULL,NULL,'100.64.0.8','2026-07-20 23:00:35'),(9,5,'create_pet','pet','7','Created pet Miming',NULL,NULL,'100.64.0.19','2026-07-23 03:42:47'),(10,5,'update_pet','pet','5','Updated pet lili, george, cici',NULL,NULL,'100.64.0.10','2026-07-28 00:09:01'),(11,5,'reopen_application','adoption_application','7','Reopened rejected/completed application',NULL,NULL,'100.64.0.13','2026-07-28 01:47:04'),(12,5,'backup_database',NULL,NULL,'Database backup created: anipet_backup_20260729_101749.sql',NULL,NULL,'100.64.0.13','2026-07-29 10:17:49'),(13,5,'export_database',NULL,NULL,'Database exported: anipet_export_20260729_101801.sql',NULL,NULL,'100.64.0.14','2026-07-29 10:18:01'),(14,5,'run_due_reports',NULL,NULL,'Executed due report schedules: 0',NULL,NULL,'100.64.0.22','2026-08-03 02:21:43');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donations`
--

DROP TABLE IF EXISTS `donations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `donations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `donor_name` varchar(150) NOT NULL,
  `pet_name` varchar(150) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_number` varchar(100) NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'GCash',
  `receipt_image` varchar(255) DEFAULT NULL,
  `donation_date` datetime NOT NULL,
  `payment_status` varchar(20) NOT NULL DEFAULT 'Successful',
  PRIMARY KEY (`id`),
  KEY `idx_donation_date` (`donation_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donations`
--

LOCK TABLES `donations` WRITE;
/*!40000 ALTER TABLE `donations` DISABLE KEYS */;
INSERT INTO `donations` VALUES (1,3,'Basil','',1.00,'pi_gg3nQAzaNbBBJLDJd9FsxQac','PayMongo QR Ph',NULL,'2026-07-27 21:27:09','Successful'),(2,3,'Basil','',1.00,'pi_Q9fg1efUfouqXvzPFmwjUBiF','PayMongo QR Ph',NULL,'2026-07-27 21:55:41','Successful'),(3,10,'Basil','Kuro',1.00,'pi_g4WcAmNEYiGHqHELwfh4hhjs','PayMongo QR Ph',NULL,'2026-07-27 23:42:31','Successful');
/*!40000 ALTER TABLE `donations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recipient_group` varchar(50) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_recipient_group` (`recipient_group`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,'applicant','announcement','New Pet Available for Adoption!','Hello,\n\nWe have exciting news — a new pet is now available for adoption!\n\nVisit our app or website to view the pet and submit your application.\n\nBest regards,\nAniPet Team','2026-07-20 11:23:29'),(2,'applicant','status_update','Adoption Application Update','Dear [Name],\n\nThank you for your interest in adopting from AniPet.\n\nAfter careful consideration, we are unable to approve your application at this time. You are welcome to apply for a different pet in the future.\n\nBest regards,\nAniPet Team','2026-07-20 11:44:36');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otps`
--

DROP TABLE IF EXISTS `otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `otps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `otp` varchar(32) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otps`
--

LOCK TABLES `otps` WRITE;
/*!40000 ALTER TABLE `otps` DISABLE KEYS */;
INSERT INTO `otps` VALUES (4,'Adam','172269','2026-07-20 03:29:52');
/*!40000 ALTER TABLE `otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pet_penalty_payments`
--

DROP TABLE IF EXISTS `pet_penalty_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pet_penalty_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pet_pound_id` int NOT NULL,
  `owner_id` int DEFAULT NULL,
  `payer_name` varchar(150) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_intent_id` varchar(150) DEFAULT NULL,
  `payment_method_id` varchar(150) DEFAULT NULL,
  `qr_image_url` text,
  `payment_method` varchar(50) NOT NULL DEFAULT 'QR Ph',
  `payment_status` varchar(30) NOT NULL DEFAULT 'pending',
  `receipt_photo` varchar(255) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pet_pound_id` (`pet_pound_id`),
  CONSTRAINT `pet_penalty_payments_ibfk_1` FOREIGN KEY (`pet_pound_id`) REFERENCES `pet_pound` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pet_penalty_payments`
--

LOCK TABLES `pet_penalty_payments` WRITE;
/*!40000 ALTER TABLE `pet_penalty_payments` DISABLE KEYS */;
INSERT INTO `pet_penalty_payments` VALUES (3,2,NULL,'Unkown',1000.00,NULL,'pi_NpsbPT3qbKNLDaLwzVnZVBWh','pm_nkVn73FxQXYDu6rBrsUyRNPG','data:image/png;base64,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','QR Ph','awaiting_next_action',NULL,NULL,'2026-08-03 05:14:53');
/*!40000 ALTER TABLE `pet_penalty_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pet_pound`
--

DROP TABLE IF EXISTS `pet_pound`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pet_pound` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pet_name` varchar(150) NOT NULL,
  `pet_photo` varchar(255) DEFAULT NULL,
  `owner_name` varchar(150) NOT NULL,
  `owner_id` int DEFAULT NULL,
  `reason` text NOT NULL,
  `penalty_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `impound_date` datetime NOT NULL,
  `claim_deadline` datetime NOT NULL,
  `species` varchar(50) NOT NULL DEFAULT 'Unknown',
  `breed` varchar(100) NOT NULL DEFAULT 'Unknown',
  `age` varchar(30) NOT NULL DEFAULT 'Unknown',
  `gender` varchar(20) NOT NULL DEFAULT 'Unknown',
  `health_status` varchar(100) NOT NULL DEFAULT 'Unknown',
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `posted_for_adoption` tinyint(1) NOT NULL DEFAULT '0',
  `adoption_pet_id` int DEFAULT NULL,
  `payment_status` varchar(20) NOT NULL DEFAULT 'Unpaid',
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `cause_of_death` varchar(50) DEFAULT NULL,
  `death_remarks` text,
  `death_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_claim_deadline` (`claim_deadline`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pet_pound`
--

LOCK TABLES `pet_pound` WRITE;
/*!40000 ALTER TABLE `pet_pound` DISABLE KEYS */;
INSERT INTO `pet_pound` VALUES (1,'Kuro','pet_6a5da1255e1d95.05395464.jpg','Basil Defeo',NULL,'Makulet',100000.00,'2026-07-20 04:16:37','2026-08-03 04:16:37','Cat','Persian','2 months','Male','Young and Healthy','Deceased',0,NULL,'Unpaid',NULL,NULL,'Illness','','2026-07-20 16:57:32','2026-07-20 04:16:37'),(2,'Miming','pet_6a61a740a0d5a6.26585252.jpg','Unkown',NULL,'outside',1000.00,'2026-07-23 05:31:44','2026-08-06 05:31:44','cat','orange','unkown','Male','Young and Healthy','Pending',0,NULL,'Unpaid',NULL,NULL,NULL,NULL,NULL,'2026-07-23 05:31:44');
/*!40000 ALTER TABLE `pet_pound` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pets`
--

DROP TABLE IF EXISTS `pets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `species` varchar(50) DEFAULT NULL,
  `breed` varchar(120) DEFAULT NULL,
  `age` varchar(50) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `description` text,
  `health_status` varchar(120) DEFAULT NULL,
  `vaccination_records` text,
  `medical_records` text,
  `image` varchar(255) DEFAULT NULL,
  `shelter_id` int DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(50) DEFAULT 'available',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pets`
--

LOCK TABLES `pets` WRITE;
/*!40000 ALTER TABLE `pets` DISABLE KEYS */;
INSERT INTO `pets` VALUES (1,'Kuro','','Ragdoll','2 months','Male','Clingy','Healthy','','','pet_6a68ddd545b6f_0.jpg',0,0,'in_adoption','2026-07-20 05:45:07'),(2,'Scooby','','Dooberman','2','Male','Playful and Lazy','Young and Healthy','','','pet_6a68ddeacb80c_0.jpg',0,0,'in_adoption','2026-07-20 07:13:56'),(3,'Sara','Dog','golden retriver','2 months','Female','','Healthy, Vaccinated','','','pet_6a68ddf93d853_0.jpg',NULL,0,'available','2026-07-20 18:03:02'),(4,'Elies','Rabbit','rabbit','5 months','Female','','Young and Healthy','','','pet_6a68de0b8a0ce_0.jpg',NULL,0,'available','2026-07-20 18:04:17'),(5,'lili, george, cici','Bird','parrot','10 months','Male','\'the triplets\' requires to be in the same owner','Young and Healthy','','','pet_6a68de199040a_0.jpeg',0,0,'in_adoption','2026-07-20 18:07:41'),(6,'Piercie','Cat','Sphynx','5 months','Male','bald type of cat but he is friendly and clingy to their owner.','Healthy, Vaccinated','','','pet_6a68de338627d_0.jpg',NULL,0,'in_adoption','2026-07-20 18:13:52'),(7,'Miming','','Orange Cat','Unkown','Male','Cute, Lazy, Food lover','Healthy','','','pet_6a68de46da2e5_0.jpg',1,0,'in_adoption','2026-07-23 03:42:47');
/*!40000 ALTER TABLE `pets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report_schedules`
--

DROP TABLE IF EXISTS `report_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `frequency` varchar(20) NOT NULL DEFAULT 'daily',
  `schedule_hour` tinyint NOT NULL DEFAULT '8',
  `recipient_email` varchar(150) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `last_run_at` datetime DEFAULT NULL,
  `next_run_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report_schedules`
--

LOCK TABLES `report_schedules` WRITE;
/*!40000 ALTER TABLE `report_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `report_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_requests`
--

DROP TABLE IF EXISTS `return_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `user_id` int NOT NULL,
  `pet_id` int NOT NULL,
  `reason` text NOT NULL,
  `penalty_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `penalty_paid` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `admin_notes` text,
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  KEY `user_id` (`user_id`),
  KEY `pet_id` (`pet_id`),
  CONSTRAINT `return_requests_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `adoption_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_ibfk_3` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_requests`
--

LOCK TABLES `return_requests` WRITE;
/*!40000 ALTER TABLE `return_requests` DISABLE KEYS */;
INSERT INTO `return_requests` VALUES (2,1,3,6,'I dont like it verry Pasaway',1000.00,0,'pending','2026-07-20 23:58:18','2026-07-20 23:58:18',NULL);
/*!40000 ALTER TABLE `return_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role` varchar(20) NOT NULL,
  `permission_key` varchar(120) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_perm_unique` (`role`,`permission_key`)
) ENGINE=InnoDB AUTO_INCREMENT=35670 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,'super_admin','manage_admins',1),(2,'super_admin','view_audit_logs',1),(3,'super_admin','manage_users',1),(4,'super_admin','manage_pets',1),(5,'super_admin','manage_applications',1),(6,'super_admin','configure_system',1),(7,'super_admin','backup_restore_database',1),(8,'super_admin','terminate_sessions',1),(9,'super_admin','update_security_policy',1),(10,'super_admin','manage_notifications',1),(11,'super_admin','generate_reports',1),(12,'super_admin','manage_appointments',1),(13,'super_admin','manage_returns',1),(14,'super_admin','manage_settings',1),(15,'admin','manage_admins',0),(16,'admin','view_audit_logs',1),(17,'admin','manage_users',1),(18,'admin','manage_pets',1),(19,'admin','manage_applications',1),(20,'admin','configure_system',0),(21,'admin','backup_restore_database',0),(22,'admin','terminate_sessions',1),(23,'admin','update_security_policy',0),(24,'admin','manage_notifications',1),(25,'admin','generate_reports',1),(26,'admin','manage_appointments',1),(27,'admin','manage_returns',1),(28,'admin','manage_settings',1),(29,'user','view_audit_logs',0),(30,'user','manage_users',0),(31,'user','manage_pets',0),(32,'user','manage_applications',0),(33,'user','configure_system',0),(34,'user','backup_restore_database',0),(35,'user','terminate_sessions',0),(36,'user','update_security_policy',0),(37,'user','manage_notifications',0),(38,'user','generate_reports',0),(39,'user','manage_appointments',0),(40,'user','manage_returns',0),(41,'user','manage_settings',0),(28410,'super_admin','manage_pet_pound',1),(28411,'admin','manage_pet_pound',1),(28412,'user','manage_pet_pound',0);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shelters`
--

DROP TABLE IF EXISTS `shelters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shelters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(80) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shelters`
--

LOCK TABLES `shelters` WRITE;
/*!40000 ALTER TABLE `shelters` DISABLE KEYS */;
INSERT INTO `shelters` VALUES (1,'AniPet Main Shelter','123 Pet Care Blvd, Manila','+63 912 345 6789','shelter@anipet.com','active','2026-07-19 22:35:16');
/*!40000 ALTER TABLE `shelters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sso_tokens`
--

DROP TABLE IF EXISTS `sso_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sso_tokens` (
  `id` varchar(64) NOT NULL,
  `user_id` int NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sso_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sso_tokens`
--

LOCK TABLES `sso_tokens` WRITE;
/*!40000 ALTER TABLE `sso_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `sso_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(120) NOT NULL,
  `setting_value` text,
  `description` text,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'site_title','AniPet Adoption System','Primary system title','2026-07-19 22:35:17'),(2,'contact_email','anipet.adoption@gmail.com','Pet pound contact email','2026-07-29 10:47:06'),(3,'notification_enabled','1','Master notification switch','2026-07-19 22:35:17'),(4,'smtp_host','smtp.gmail.com','','2026-07-20 13:57:47'),(5,'smtp_port','587','','2026-07-20 13:57:48'),(6,'alert_recipient_emails','basildef@gmail.com,adammedina1005@gmail.com,barroa.michelle0@gmail.com','','2026-07-20 13:57:48'),(7,'smtp_pass','','','2026-07-20 13:57:48'),(8,'use_smtp','1','','2026-07-20 13:57:48'),(9,'smtp_from_email','','','2026-07-20 13:57:48'),(10,'alert_pending_applications','10','','2026-07-20 13:57:48'),(11,'alert_stalled_applications','5','','2026-07-20 13:57:48'),(12,'alert_aborted_connects','10','','2026-07-20 13:57:48'),(13,'alert_unassigned_applications','5','','2026-07-20 13:57:48'),(14,'smtp_user','','','2026-07-20 13:57:49'),(16,'smtp_from_name','AniPet','','2026-07-20 13:57:49'),(17,'alert_threads_running','25','','2026-07-20 13:57:49'),(18,'notification_channel','email','','2026-07-20 13:57:49'),(49,'pound_name','AniPet Pet Adoption Center','Public name of the pet pound','2026-07-29 10:47:06'),(50,'pound_address','','Address of the pet pound','2026-07-29 10:47:06'),(51,'support_phone','','Pet pound contact number','2026-07-29 10:47:06'),(52,'hours_weekdays','8:00 AM - 5:00 PM','Monday to Friday opening hours','2026-07-29 10:47:06'),(53,'hours_saturday','8:00 AM - 12:00 PM','Saturday opening hours','2026-07-29 10:47:06'),(54,'hours_sunday','Closed','Sunday opening hours','2026-07-29 10:47:06'),(55,'email_notifications_enabled','1','Enable email notifications','2026-07-29 10:47:06'),(56,'fcm_notifications_enabled','1','Enable Firebase push notifications','2026-07-29 10:47:06'),(57,'notify_new_application','1','Notify when a new adoption application is submitted','2026-07-29 10:47:06'),(58,'notify_status_update','1','Notify when an application status changes','2026-07-29 10:47:06'),(59,'notify_donation_received','1','Notify when a donation is received','2026-07-29 10:47:06'),(60,'notify_pickup_reminder','1','Enable ready-for-pickup reminders','2026-07-29 10:47:06');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_notifications`
--

DROP TABLE IF EXISTS `user_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `application_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `idx_application_id` (`application_id`),
  CONSTRAINT `fk_user_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_notifications`
--

LOCK TABLES `user_notifications` WRITE;
/*!40000 ALTER TABLE `user_notifications` DISABLE KEYS */;
INSERT INTO `user_notifications` VALUES (1,7,NULL,'Interview Schedule Confirmed','Good day Adam Medina,\n\nYour adoption interview for  has been confirmed.\n\nInterview Schedule:\nJuly 20, 2026 at 08:00 PM\n\nPlease arrive at least 15 minutes before your scheduled interview.\n\nThank you,\nAniPet Adoption Team','appointment',0,'2026-07-20 11:49:44'),(2,7,NULL,'Interview Schedule Confirmed','Good day Adam Medina,\n\nYour adoption interview for Scooby has been confirmed.\n\nInterview Schedule:\nJanuary 01, 1970 at 12:00 AM\n\nPlease arrive at least 15 minutes before your scheduled interview.\n\nThank you,\nAniPet Adoption Team','appointment',0,'2026-07-20 12:14:32'),(3,12,NULL,'Interview Schedule Confirmed','Good day Janessa Pilapil,\n\nYour adoption interview for Scooby has been confirmed.\n\nInterview Schedule:\nJanuary 01, 1970 at 12:00 AM\n\nPlease arrive at least 15 minutes before your scheduled interview.\n\nThank you,\nAniPet Adoption Team','appointment',0,'2026-07-20 13:20:43'),(4,12,NULL,'Interview Schedule Confirmed','Good day Janessa Pilapil,\n\nYour adoption interview for  has been confirmed.\n\nInterview Schedule:\nJuly 23, 2026 at 10:00 AM\n\nPlease arrive at least 15 minutes before your scheduled interview.\n\nThank you,\nAniPet Adoption Team','appointment',0,'2026-07-20 17:03:30'),(5,3,NULL,'Interview Schedule Confirmed','Good day Janessa Pilapil,\n\nYour adoption interview for Miming has been confirmed.\n\nInterview Schedule:\nJuly 23, 2026 at 04:00 PM\n\nPlease arrive at least 15 minutes before your scheduled interview.\n\nThank you,\nAniPet Adoption Team','appointment',1,'2026-07-23 05:27:03'),(6,10,NULL,'Interview Schedule Confirmed','Good day Boni Parts,\n\nYour adoption interview for lili, george, cici has been confirmed.\n\nInterview Schedule:\nJuly 29, 2026 at 08:00 AM\n\nPlease arrive at least 15 minutes before your scheduled interview.\n\nThank you,\nAniPet Adoption Team','appointment',1,'2026-07-27 23:54:33'),(7,10,NULL,'Interview Schedule Confirmed','Good day Boni Parts,\n\nYour adoption interview for lili, george, cici has been confirmed.\n\nInterview Schedule:\nJuly 29, 2026 at 12:00 PM\n\nPlease arrive at least 15 minutes before your scheduled interview.\n\nThank you,\nAniPet Adoption Team','appointment',1,'2026-07-28 00:14:14'),(8,6,5,'Application Approved','Congratulations! Your application for Kuro has been approved.','application',1,'2026-07-28 03:33:27'),(9,10,7,'Application Approved','Congratulations! Your application for lili, george, cici has been approved.','application',1,'2026-07-28 03:51:03'),(10,6,5,'For Releasing','Your adopted pet Kuro is now being prepared for release.','application',1,'2026-07-28 15:06:13'),(11,6,5,'Application Approved','Congratulations! Your application for Kuro has been approved.','application',1,'2026-07-28 15:06:40'),(12,6,5,'For Releasing','Your adopted pet Kuro is now being prepared for release.','application',1,'2026-07-28 15:06:52'),(13,6,5,'Application Under Screening','Your application for Kuro is now under screening.','application',1,'2026-07-28 15:29:41'),(14,6,5,'Application Approved','Congratulations! Your application for Kuro has been approved.','application',1,'2026-07-28 15:30:12'),(15,6,5,'Application Reopened','Your application for Kuro has been moved back to Pending.','application',1,'2026-07-28 15:33:11'),(16,6,5,'Application Approved','Congratulations! Your application for Kuro has been approved.','application',1,'2026-07-28 15:33:27'),(17,6,5,'For Releasing','Your adopted pet Kuro is now being prepared for release.','application',1,'2026-07-28 16:32:23'),(18,6,5,'Application Approved','Congratulations! Your application for Kuro has been approved.','application',1,'2026-07-28 16:32:38'),(19,6,5,'For Releasing','Your adopted pet Kuro is now being prepared for release.','application',1,'2026-07-28 16:32:53'),(20,6,5,'Ready for Pickup','Your adopted pet Kuro is ready for pickup.','application',1,'2026-07-28 16:38:53'),(21,6,5,'Application Approved','Congratulations! Your application for Kuro has been approved.','application',1,'2026-07-28 16:39:23'),(22,6,5,'For Releasing','Your adopted pet Kuro is now being prepared for release.','application',1,'2026-07-28 16:39:34'),(23,6,5,'Application Approved','Congratulations! Your application for Kuro has been approved.','application',1,'2026-07-28 16:54:23'),(24,6,5,'For Releasing','Your adopted pet Kuro is now being prepared for release.','application',1,'2026-07-28 17:13:11'),(25,6,5,'Adoption Completed','Congratulations! Your adoption of Kuro has been completed.','application',1,'2026-07-31 03:53:20'),(26,6,5,'Application Reopened','Your application for Kuro has been moved back to Pending.','application',1,'2026-07-31 04:19:03'),(27,6,5,'Application Approved','Congratulations! Your application for Kuro has been approved.','application',1,'2026-07-31 04:19:11'),(28,6,5,'Ready for Pickup','Your adopted pet Kuro is ready for pickup.','application',1,'2026-07-31 04:20:17'),(29,6,5,'Application Reopened','Your application for Kuro has been moved back to Pending.','application',1,'2026-07-31 05:05:44'),(30,6,5,'Ready for Pickup','Your adopted pet Kuro is ready for pickup.','application',1,'2026-07-31 05:05:53'),(31,3,6,'Application Reopened','Your application for Miming has been moved back to Pending.','application',0,'2026-08-03 02:24:05'),(32,3,6,'Application Under Screening','Your application for Miming is now under screening.','application',0,'2026-08-03 02:24:57'),(33,11,9,'Application Under Screening','Your application for Miming is now under screening.','application',1,'2026-08-03 02:58:46'),(34,11,9,'Application Approved','Congratulations! Your application for Miming has been approved.','application',1,'2026-08-03 02:59:19');
/*!40000 ALTER TABLE `user_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_active_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES (1,1,'317fff1ade3eb324d5786b98660605ac','100.64.0.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 03:56:19','2026-07-21 01:53:02',1),(10,10,'9d68f9c348916bcc2cbe8e5ce3d81c62','100.64.0.13','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-20 05:43:37','2026-07-20 22:45:44',1),(16,10,'d8943477a08bfa23b03d178338a557c0','100.64.0.18','okhttp/4.12.0','2026-07-20 07:01:21','2026-07-20 07:01:21',1),(17,10,'7955497a9fd1ba6b8ffe87bd38807816','100.64.0.7','okhttp/4.12.0','2026-07-20 07:06:45','2026-07-20 07:06:45',1),(18,8,'86ba14ce28e7086a51f6495a2414d694','100.64.0.4','okhttp/4.12.0','2026-07-20 08:15:39','2026-07-20 08:15:39',1),(21,7,'f1715e152f44f2644859a1f60e91ac67','100.64.0.4','okhttp/4.12.0','2026-07-20 10:01:41','2026-07-20 10:01:41',1),(26,7,'340390ba058e986287f6ec5e35104ded','100.64.0.10','okhttp/4.12.0','2026-07-20 12:26:40','2026-07-20 12:26:40',1),(28,12,'dda1d00977975ef541ab736897f6cb1a','100.64.0.8','okhttp/4.12.0','2026-07-20 13:13:53','2026-07-20 13:13:53',1),(29,13,'30a6d2345a3840e6742879b3f6a4817d','100.64.0.12','okhttp/4.12.0','2026-07-20 13:17:00','2026-07-20 13:17:00',1),(40,3,'2823ab13bae4e4e754ab02438d7fc984','100.64.0.11','okhttp/4.12.0','2026-07-20 23:00:01','2026-07-20 23:00:01',1),(43,3,'7c422acb48cdb92094d00dbeb8251f60','100.64.0.19','okhttp/4.12.0','2026-07-20 23:02:51','2026-07-20 23:02:51',1),(44,3,'5d9a78e89283db8e867330c9bf2d3d36','100.64.0.6','okhttp/4.12.0','2026-07-20 23:03:23','2026-07-20 23:03:23',1),(49,6,'f308c3ae8d2ae27c9119ca5985ffe17d','100.64.0.6','okhttp/4.12.0','2026-07-21 01:23:23','2026-07-21 01:23:23',1),(51,1,'2b1ae6322543cabe1e4e03d8bc1a4299','100.64.0.15','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-21 02:05:29','2026-07-21 02:06:24',1),(53,5,'59853314d0582f2ee75b809ecad24cc0','100.64.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-21 08:24:28','2026-07-21 08:24:28',1),(54,1,'8edc51cb094e72c4e49b72dc0185370e','100.64.0.17','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-21 08:27:44','2026-07-21 08:27:44',1),(55,7,'7bd4fae2b0786fc2454dc31729d3e909','100.64.0.21','okhttp/4.12.0','2026-07-21 08:32:18','2026-07-21 08:32:18',1),(56,6,'6b3873100bee6d17ff9ddbeb089c4c98','100.64.0.3','okhttp/4.12.0','2026-07-21 11:15:53','2026-07-21 11:15:53',1),(57,6,'9edab0c417a8f3541ae0b7bccf4734fe','100.64.0.11','okhttp/4.12.0','2026-07-21 12:12:09','2026-07-21 12:12:09',1),(58,1,'324feb4d9334136870b763621504d608','100.64.0.21','Mozilla/5.0 (Linux; Android 16; SM-A566B Build/BP4A.251205.006; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.124 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/570.0.0.34.87;]','2026-07-22 11:11:56','2026-07-23 16:54:33',1),(60,1,'4590d132c9e4e2b9ae6226b6dd154fe1','100.64.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 03:29:56','2026-07-23 03:29:56',1),(61,5,'940507ed953c9b101de959948e1d7fb6','100.64.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 03:30:10','2026-07-23 03:30:10',1),(62,8,'8e965d78e26270749b036cf3f5b8b9eb','100.64.0.23','okhttp/4.12.0','2026-07-23 03:33:42','2026-07-23 03:33:42',1),(63,8,'fd1ff5c8510e6090738a8706055c1cc5','100.64.0.6','okhttp/4.12.0','2026-07-23 03:36:56','2026-07-23 03:36:56',1),(64,3,'e4a401d0e53daceb11f9d8a6ff48d847','100.64.0.4','okhttp/4.12.0','2026-07-23 04:54:37','2026-07-23 04:54:37',1),(65,6,'6a63e2088ba36fa3fa1ffab5573e8f5d','100.64.0.13','okhttp/4.12.0','2026-07-23 09:29:24','2026-07-23 09:29:24',1),(66,1,'43e2e5370a87cc4230f3cc411b20d0d6','100.64.0.5','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-23 11:50:19','2026-07-23 11:50:19',1),(69,5,'d31964b1383636ac6c06690a6b86b8a6','100.64.0.3','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Safari/537.36','2026-07-23 16:55:08','2026-07-23 16:55:08',1),(70,3,'ef169326efb222782c2b875130d20b3f','100.64.0.2','okhttp/4.12.0','2026-07-27 16:00:52','2026-07-27 16:00:52',1),(71,3,'0da859b8b773d78d71b812b79025947b','100.64.0.5','okhttp/4.12.0','2026-07-27 17:53:02','2026-07-27 17:53:02',1),(72,3,'29d9e62e910ed8f74d2b50902ed3ae2b','100.64.0.10','okhttp/4.12.0','2026-07-27 18:08:08','2026-07-27 18:08:08',1),(73,3,'33ef604f3823fddaa4b3d63f6411a819','100.64.0.18','okhttp/4.12.0','2026-07-27 18:41:10','2026-07-27 18:41:10',1),(74,5,'f25589fe8d99f96fe40d0333db37e295','100.64.0.14','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 19:41:49','2026-07-28 01:45:50',1),(78,3,'b3aae1e9aff4165d3c9422b6707afdb7','100.64.0.12','okhttp/4.12.0','2026-07-27 21:20:35','2026-07-27 21:20:35',1),(79,3,'8c746736ec192bb13d50d7c082e488cd','100.64.0.2','okhttp/4.12.0','2026-07-27 21:49:21','2026-07-27 21:49:21',1),(80,3,'23667a191f1644e53cc2333447665ec5','100.64.0.10','okhttp/4.12.0','2026-07-27 21:53:43','2026-07-27 21:53:43',1),(82,1,'a102dc60e4f5f4501528d807acbe2eaa','100.64.0.18','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-27 22:04:46','2026-07-28 01:44:23',1),(83,3,'35efbe5ce49a2472e954ba84b31182e9','100.64.0.11','okhttp/4.12.0','2026-07-27 22:08:14','2026-07-27 22:08:14',1),(85,10,'2c4aba4f69aa8779be215f9bcec016f8','100.64.0.11','okhttp/4.12.0','2026-07-27 23:40:51','2026-07-27 23:40:51',1),(88,10,'70a4313b8b3b53c286eb816e1cb6e478','100.64.0.4','okhttp/4.12.0','2026-07-28 00:09:37','2026-07-28 00:09:37',1),(96,1,'2539c3661073cb4497d480cfaf34294f','100.64.0.7','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 02:45:22','2026-07-28 03:33:14',1),(97,6,'ba66089d970debe5a5c6b7ea0a79529a','100.64.0.16','okhttp/4.12.0','2026-07-28 02:54:23','2026-07-28 02:54:23',1),(99,10,'44fee2c1d86c0f6946a97ff5d55fc9ec','100.64.0.14','okhttp/4.12.0','2026-07-28 03:42:50','2026-07-28 03:42:50',1),(100,10,'54acaea33974a9ec8ac22702cabc8071','100.64.0.11','okhttp/4.12.0','2026-07-28 03:50:08','2026-07-28 03:50:08',1),(101,6,'ab02325ebb34baa577d8d8eb56b4a0d3','100.64.0.3','okhttp/4.12.0','2026-07-28 05:01:25','2026-07-28 05:01:25',1),(102,1,'52045e82c08cdc5ea05265dd35123cab','100.64.0.11','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-28 15:01:34','2026-07-29 05:11:44',1),(107,1,'94f50225d6bda937ca0bf2aa6ee42767','100.64.0.18','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 01:22:31','2026-07-29 01:22:31',1),(112,5,'c7065a082be0b77dc3f53952dc21ce23','100.64.0.10','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36','2026-07-29 04:27:31','2026-07-29 04:27:31',1),(115,5,'359b4b409f694e5beb2c33e979767592','100.64.0.11','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-29 10:16:50','2026-07-31 06:37:57',1),(123,11,'32045037e6dc23bbff764b05ab7b3d00','100.64.0.11','okhttp/4.12.0','2026-08-02 05:15:09','2026-08-02 05:15:09',1),(124,3,'5b47aa4e7b29d81d2a282b80de3fc90a','100.64.0.8','okhttp/4.12.0','2026-08-03 02:16:11','2026-08-03 02:16:11',1),(125,5,'f65576d44a7562a17f4ada5f931c9dc0','100.64.0.6','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 02:20:39','2026-08-03 05:14:37',1),(126,3,'0a3d5399a4928675cb8065f4a77397ba','100.64.0.17','okhttp/4.12.0','2026-08-03 02:22:13','2026-08-03 02:22:13',1);
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(80) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `first_name` varchar(60) NOT NULL,
  `middle_name` varchar(60) DEFAULT NULL,
  `last_name` varchar(60) NOT NULL,
  `suffix` varchar(60) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `contact_preference` varchar(20) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `is_suspended` tinyint(1) NOT NULL DEFAULT '0',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `profile_picture` varchar(255) DEFAULT NULL,
  `fcm_token` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','Admin','Admin',NULL,'Admin',NULL,'anipet.adoption@gmail.com','$2y$12$rmeHWAKghz/l8ZiflGY8EOwH5i74sA8dgg79X7NtnArCyL7YJXeFO',NULL,NULL,NULL,'admin',0,0,1,'2026-07-20 22:55:01',NULL,NULL),(2,'super_admin','Super_anipet','super_admin',NULL,'super_admin',NULL,'demo@local.com','$2y$12$NPe25sLL6quztNWyfHKL/ugL3dnpxXgcZ4CAzTm2azZ7EZj07AHBC',NULL,NULL,NULL,'super_admin',0,1,1,'2026-07-20 22:55:47',NULL,NULL),(3,'Janessa','Janessa Pilapil','Janessa','','Pilapil','','maepilapil522@gmail.com','$2y$12$P6dtT3MggISiJ3vEkLJgPOzx6qZQvrPwX3eJIH7WHjs34YOM4NVEa','Novaliches Quezon city','0992 894 6136','Email','user',0,0,1,'2026-07-20 22:59:23','uploads/profile_pictures/user_3_4acd3467b616ab74.jpg','chUE4Ph9R_umHtei4Ktk41:APA91bEg1FXWzil61t_bMXt-ugm86qcwoVS6YnA1pmQde0nTRJiQukANYzxUYO9dtdL7Krqz6ITmmNNX1Ifg2LsECEzdR3U8Y61mvKTsUDbF4jsOZL1cMFQ'),(5,'super_anipet','Super_anipet','Super_anipet',NULL,'Super_anipet',NULL,'anipet.adoption2026@gmail.com','$2y$12$GaFpd/HBLXNbukXR/GEiBeIPzWmUojEiy9hf2ge4Pr7TLXhGM.phu',NULL,NULL,NULL,'super_admin',0,0,1,'2026-07-20 23:00:26',NULL,NULL),(6,'Basil','Wilfred Anthony Adam C. Medina','Basil','','Defeo','','basildef@gmail.com','$2y$12$PRu6itO6.kEpDIznO9WHXOA3EtTjkWjIRrQcKs1sXlKkszix7qG3K','Bagumbong Caloocan','09219809613','Email','user',0,0,1,'2026-07-21 01:21:02','uploads/profile_pictures/user_6_2e83eb05e25384f8.jpg','crQ3ZXQYSJSVxHO-pzVuX2:APA91bEp1O-AZxf2O4XblrA8X1_gyUlRHKE2CoE8-leKaKf7D4ljDDoolOhAQ4RMKbVDrm3txPW6jrWy4tKVdK30EVr_ukZ3U9ltuxmyNaczc4uYAvCvtg8'),(7,'andrewg','Chris Copada Guarino','Chris','Copada','Guarino','','andrewguarino8@gmail.com','$2y$12$cFb2w6RWhfo4tYkNp3CGJ.qH47ng9wmB7CbYFhuUZT8LOjHhFmbHC','','','Email','user',0,0,1,'2026-07-21 08:31:21','uploads/profile_pictures/user_7_8fb88f3373e9a58f.jpg',NULL),(8,'haigarcia08','Haidie Turaya Turaya','haidie turaya','','turaya','','haidiegarcia.24@gmail.com','$2y$12$PHypaU0RVKP8yDmZ7gf4t.17y4VGQOTKC5G84S/BgDrrSg0sUGA1S','','09166383202','email','user',0,0,1,'2026-07-23 03:29:31',NULL,NULL),(10,'Boniparts','Boni Parts','Boni','','Parts','','boniparts036@gmail.com','$2y$12$vMzRTf2gLb0jVL.WxkjCRuGsEXSrAtfZ3CZGgSKpWQjy4Kjgay.ga','','','email','user',0,0,1,'2026-07-27 23:40:22',NULL,NULL),(11,'Adam','Adam Medina','Adam','','Medina','','adam.medina1005@gmail.com','$2y$12$ik9emdCrWSsqKnUhJD.LMeMb5l1OrUgP0THT0FyhavtqKAeZLA8Ki','Caloocan City','09928946136','Email','user',0,0,1,'2026-08-02 05:14:44','uploads/profile_pictures/user_11_ff083ce37f09be2e.jpg','crQ3ZXQYSJSVxHO-pzVuX2:APA91bEp1O-AZxf2O4XblrA8X1_gyUlRHKE2CoE8-leKaKf7D4ljDDoolOhAQ4RMKbVDrm3txPW6jrWy4tKVdK30EVr_ukZ3U9ltuxmyNaczc4uYAvCvtg8');
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

-- Dump completed on 2026-08-03 13:38:43
