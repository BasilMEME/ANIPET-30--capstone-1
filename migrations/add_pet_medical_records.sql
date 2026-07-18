-- Migration: Add species, vaccination_records, medical_records columns to pets table
-- Run this once in phpMyAdmin or MySQL CLI before using the new admin dashboard features

ALTER TABLE `pets`
    ADD COLUMN IF NOT EXISTS `species` VARCHAR(50) DEFAULT NULL AFTER `name`,
    ADD COLUMN IF NOT EXISTS `vaccination_records` TEXT DEFAULT NULL AFTER `health_status`,
    ADD COLUMN IF NOT EXISTS `medical_records` TEXT DEFAULT NULL AFTER `vaccination_records`;
