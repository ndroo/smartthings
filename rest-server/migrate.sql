drop database if exists smartthings;
create database smartthings;
use smartthings;
CREATE TABLE `sensors` (
  `id` varchar(255) DEFAULT NULL,
  `type` varchar(30) DEFAULT NULL,
  `val` varchar(100) DEFAULT NULL,
  `val_type` varchar(50) DEFAULT NULL,
  `timestamp` int(10) unsigned DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  KEY `timestamp` (`timestamp`,`type`,`id`),
  KEY `id` (`id`,`timestamp`,`type`)
) ENGINE=InnoDB;
CREATE TABLE `battery_sensors` (
  `id` varchar(255) DEFAULT NULL ) ENGINE=InnoDB;
