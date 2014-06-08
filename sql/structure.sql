-- phpMyAdmin SQL Dump
-- version 3.3.7deb7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Erstellungszeit: 28. August 2013 um 07:30
-- Server Version: 5.1.66
-- PHP-Version: 5.3.3-7+squeeze17

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Datenbank: `huhu`
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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=6 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `chats_messages`
--

CREATE TABLE IF NOT EXISTS `chats_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fk_chatID` int(11) unsigned NOT NULL,
  `fk_userID` int(11) unsigned NOT NULL,
  `fk_recipientUserID` int(11) unsigned NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_chatID` (`fk_chatID`,`fk_userID`),
  KEY `fk_userID` (`fk_userID`),
  KEY `fk_recipientUserID` (`fk_recipientUserID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=133 ;

ALTER TABLE  `chats_messages` ADD  `message_id` VARCHAR( 24 ) NOT NULL AFTER  `id` ,
ADD UNIQUE (
  `message_id`
);

ALTER TABLE  `huhu`.`chats_messages` DROP INDEX  `message_id` ,
ADD UNIQUE  `message_id` (  `message_id` ,  `fk_recipientUserID` );

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `chats_messages`
--
ALTER TABLE `chats_messages`
  ADD CONSTRAINT `chats_messages_ibfk_1` FOREIGN KEY (`fk_chatID`) REFERENCES `chats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chats_messages_ibfk_2` FOREIGN KEY (`fk_userID`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chats_messages_ibfk_3` FOREIGN KEY (`fk_recipientUserID`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `chats_user`
--

CREATE TABLE IF NOT EXISTS `chats_user` (
  `fk_chatID` int(11) unsigned NOT NULL,
  `fk_userID` int(11) unsigned NOT NULL,
  `last_read_message_id` VARCHAR( 24 ),
  UNIQUE KEY `fk_chatID` (`fk_chatID`,`fk_userID`),
  KEY `fk_userID` (`fk_userID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
ALTER TABLE  `chats_user` CHANGE  `last_read_message_id`  `last_read_message_id` VARCHAR( 24 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL;


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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=63 ;

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
  `key` binary(24) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user` (`user`),
  KEY `lastLoginTimestamp` (`lastLoginTimestamp`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=79 ;

ALTER TABLE  `users` ADD  `public_key` BLOB NULL;
ALTER TABLE `users` DROP `key`;

--
-- Tabellenstruktur für Tabelle `user_invisible`
--
CREATE TABLE IF NOT EXISTS `user_invisible` (
  `fk_userId` int(11) unsigned NOT NULL,
  `fk_userInvisibleForId` int(11) unsigned NOT NULL,
  UNIQUE KEY `fk_userId` (`fk_userId`,`fk_userInvisibleForId`),
  KEY `fk_userInvisibleForId` (`fk_userInvisibleForId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `user_invisible`
--
ALTER TABLE `user_invisible`
  ADD CONSTRAINT `user_invisible_ibfk_2` FOREIGN KEY (`fk_userInvisibleForId`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_invisible_ibfk_1` FOREIGN KEY (`fk_userId`) REFERENCES `users` (`id`) ON DELETE CASCADE;


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
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;

--
-- Constraints der exportierten Tabellen
--

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
  ADD CONSTRAINT `chats_messages_ibfk_2` FOREIGN KEY (`fk_userID`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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

  
  CREATE TABLE IF NOT EXISTS `log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `priority` varchar(10) NOT NULL,
  `message` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4380 ;

ALTER TABLE  `log` ADD  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE  `users` ADD  `email` VARCHAR( 255 ) NOT NULL;


--
-- Tabellenstruktur für Tabelle `user_push_auth`
--

CREATE TABLE IF NOT EXISTS `user_push_auth` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fk_userID` int(11) unsigned NOT NULL,
  `method` enum('gcm','websocket') NOT NULL,
  `token` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_userID` (`fk_userID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `user_push_auth`
--
ALTER TABLE `user_push_auth`
  ADD CONSTRAINT `user_push_auth_ibfk_1` FOREIGN KEY (`fk_userID`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE  `user_push_auth` ADD  `valid_until` TIMESTAMP NOT NULL;
ALTER TABLE  `user_push_auth` CHANGE  `valid_until`  `valid_until` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE  `huhu`.`user_push_auth` ADD UNIQUE (
  `fk_userID` ,
  `method`
);
ALTER TABLE  `huhu`.`user_push_auth` ADD UNIQUE (
  `token`
);