SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `email` (
  `id` int(11) NOT NULL,
  `status` enum('waiting','sent','error') COLLATE utf8_polish_ci DEFAULT 'waiting',
  `prioity` tinyint(4) DEFAULT '10',
  `datetime_save` int(11) DEFAULT NULL,
  `datetime_sent` int(11) DEFAULT NULL,
  `authors` text COLLATE utf8_polish_ci,
  `recipients` text COLLATE utf8_polish_ci,
  `reply_to` text COLLATE utf8_polish_ci,
  `subject` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL,
  `encoding` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL,
  `context` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL,
  `context_identifier` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL,
  `retry_count` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;

CREATE TABLE `email_header` (
  `id` int(11) NOT NULL,
  `email_id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL,
  `value` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;

CREATE TABLE `email_part` (
  `id` int(11) NOT NULL,
  `email_id` int(11) NOT NULL,
  `mime_type` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL,
  `encoding` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL,
  `charset` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL,
  `disposition` enum('attachment','inline') COLLATE utf8_polish_ci DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL,
  `content_id` varchar(255) COLLATE utf8_polish_ci DEFAULT NULL,
  `contents` longtext COLLATE utf8_polish_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


ALTER TABLE `email`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `priority` (`prioity`);

ALTER TABLE `email_header`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `value` (`value`),
  ADD KEY `fk_email_header_email1_idx` (`email_id`);

ALTER TABLE `email_part`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_email_part_email1_idx` (`email_id`);


ALTER TABLE `email`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `email_header`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `email_part`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `email_header`
  ADD CONSTRAINT `fk_email_header_email1` FOREIGN KEY (`email_id`) REFERENCES `email` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `email_part`
  ADD CONSTRAINT `fk_email_part_email1` FOREIGN KEY (`email_id`) REFERENCES `email` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
