SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `hooks` (
  `id` int(10) UNSIGNED NOT NULL,
  `hostname_id` int(10) UNSIGNED NOT NULL,
  `hook` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `hostnames` (
  `id` int(10) UNSIGNED NOT NULL,
  `hostname` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `domain` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `permissions` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `hostname_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` char(60) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


ALTER TABLE `hooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hook_hostname` (`hostname_id`);

ALTER TABLE `hostnames`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hostname` (`hostname`);

ALTER TABLE `permissions`
  ADD PRIMARY KEY (`user_id`,`hostname_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `permission_hostname` (`hostname_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);


ALTER TABLE `hooks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `hostnames`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;


ALTER TABLE `hooks`
  ADD CONSTRAINT `hook_hostname` FOREIGN KEY (`hostname_id`) REFERENCES `hostnames` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `permissions`
  ADD CONSTRAINT `permission_hostname` FOREIGN KEY (`hostname_id`) REFERENCES `hostnames` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `permission_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;
