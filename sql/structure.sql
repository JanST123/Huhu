-- phpMyAdmin SQL Dump
-- version 3.4.11.1deb2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Erstellungszeit: 09. Jul 2014 um 14:13
-- Server Version: 5.5.37
-- PHP-Version: 5.4.4-14+deb7u10

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Datenbank: `deutschtalk`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `chats`
--

CREATE TABLE IF NOT EXISTS `chats` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `fk_ownerUserID` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ownerUserID` (`fk_ownerUserID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `chats_messages`
--

CREATE TABLE IF NOT EXISTS `chats_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` varchar(24) NOT NULL,
  `fk_chatID` int(11) unsigned NOT NULL,
  `fk_userID` int(11) unsigned NOT NULL,
  `fk_recipientUserID` int(11) unsigned NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_id` (`message_id`,`fk_recipientUserID`),
  KEY `fk_chatID` (`fk_chatID`,`fk_userID`),
  KEY `fk_userID` (`fk_userID`),
  KEY `fk_recipientUserID` (`fk_recipientUserID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `chats_user`
--

CREATE TABLE IF NOT EXISTS `chats_user` (
  `fk_chatID` int(11) unsigned NOT NULL,
  `fk_userID` int(11) unsigned NOT NULL,
  `last_read_message_id` varchar(24) DEFAULT NULL,
  UNIQUE KEY `fk_chatID` (`fk_chatID`,`fk_userID`),
  KEY `fk_userID` (`fk_userID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `contactlist`
--

CREATE TABLE IF NOT EXISTS `contactlist` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fk_ownerUserID` int(11) unsigned NOT NULL,
  `fk_contactUserID` int(11) unsigned NOT NULL,
  `accepted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fk_ownerUserID_2` (`fk_ownerUserID`,`fk_contactUserID`),
  KEY `fk_ownerUserID` (`fk_ownerUserID`,`fk_contactUserID`,`accepted`),
  KEY `fk_contactUserID` (`fk_contactUserID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `log`
--

CREATE TABLE IF NOT EXISTS `log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `priority` varchar(10) NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `lastLoginTimestamp` int(11) DEFAULT NULL,
  `lastLogoutTimestamp` int(11) DEFAULT NULL,
  `app_in_background` tinyint(1) NOT NULL DEFAULT '0',
  `photo` blob,
  `photo_width` int(5) DEFAULT NULL,
  `photo_big` blob,
  `photo_height` int(5) DEFAULT NULL,
  `photo_big_width` int(5) DEFAULT NULL,
  `photo_big_height` int(5) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `invisible` tinyint(1) DEFAULT '0',
  `public_key` blob,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user` (`user`),
  KEY `lastLoginTimestamp` (`lastLoginTimestamp`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_additional`
--

CREATE TABLE IF NOT EXISTS `user_additional` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fk_userID` int(11) unsigned NOT NULL,
  `field` enum('email','city','zip','age','birthday','firstname','lastname','lastschool','company','phone','mobile','url','girlsname') NOT NULL,
  `value` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_userID` (`fk_userID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_invisible`
--

CREATE TABLE IF NOT EXISTS `user_invisible` (
  `fk_userId` int(11) unsigned NOT NULL,
  `fk_userInvisibleForId` int(11) unsigned NOT NULL,
  UNIQUE KEY `fk_userId` (`fk_userId`,`fk_userInvisibleForId`),
  KEY `fk_userInvisibleForId` (`fk_userInvisibleForId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_push_auth`
--

CREATE TABLE IF NOT EXISTS `user_push_auth` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fk_userID` int(11) unsigned NOT NULL,
  `method` enum('gcm','websocket','apn') NOT NULL,
  `token` varchar(255) NOT NULL,
  `valid_until` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fk_userID_2` (`fk_userID`,`method`),
  UNIQUE KEY `token` (`token`),
  KEY `fk_userID` (`fk_userID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Constraints der exportierten Tabellen

--
-- Constraints der Tabelle `chats`
--
ALTER TABLE `chats`
  ADD CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`fk_ownerUserID`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `chats_messages`
--
ALTER TABLE `chats_messages`
  ADD CONSTRAINT `chats_messages_ibfk_1` FOREIGN KEY (`fk_chatID`) REFERENCES `chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chats_messages_ibfk_2` FOREIGN KEY (`fk_userID`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chats_messages_ibfk_3` FOREIGN KEY (`fk_recipientUserID`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `chats_user`
--
ALTER TABLE `chats_user`
  ADD CONSTRAINT `chats_user_ibfk_1` FOREIGN KEY (`fk_chatID`) REFERENCES `chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chats_user_ibfk_2` FOREIGN KEY (`fk_userID`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `contactlist`
--
ALTER TABLE `contactlist`
  ADD CONSTRAINT `contactlist_ibfk_1` FOREIGN KEY (`fk_ownerUserID`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contactlist_ibfk_2` FOREIGN KEY (`fk_contactUserID`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `user_additional`
--
ALTER TABLE `user_additional`
  ADD CONSTRAINT `user_additional_ibfk_1` FOREIGN KEY (`fk_userID`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `user_invisible`
--
ALTER TABLE `user_invisible`
  ADD CONSTRAINT `user_invisible_ibfk_1` FOREIGN KEY (`fk_userId`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_invisible_ibfk_2` FOREIGN KEY (`fk_userInvisibleForId`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `user_push_auth`
--
ALTER TABLE `user_push_auth`
  ADD CONSTRAINT `user_push_auth_ibfk_1` FOREIGN KEY (`fk_userID`) REFERENCES `users` (`id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
