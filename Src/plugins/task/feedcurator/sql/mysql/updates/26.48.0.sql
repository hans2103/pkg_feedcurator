--
-- Koppeling tussen feed-items en Joomla-artikelen. Een item dat hier staat, wordt niet opnieuw
-- geimporteerd, ook niet als de redactie het artikel verwijdert of weggooit.
--
-- item_key    sha256 van de genormaliseerde link van het item (of van de guid als er geen link is)
-- source      URL van de feed waaruit het item kwam
-- article_id  Joomla-artikel; NULL is gereserveerd en wordt nu niet gebruikt (een weggegooid artikel houdt zijn id)
-- imported    moment van importeren (UTC)
--
CREATE TABLE IF NOT EXISTS `#__feedcurator_items` (
  `item_key` char(64) NOT NULL,
  `source` varchar(255) NOT NULL DEFAULT '',
  `article_id` int unsigned DEFAULT NULL,
  `imported` datetime NOT NULL,
  PRIMARY KEY (`item_key`),
  UNIQUE KEY `idx_article` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

--
-- Mislukte runs op rij per geplande taak, zodat een korte storing bij een feed geen foutmail geeft.
--
-- task_id   id van de geplande taak (#__scheduler_tasks)
-- failures  aantal runs op rij waarin een feed niet bereikbaar was
-- since     eerste mislukte run van deze reeks (UTC)
--
CREATE TABLE IF NOT EXISTS `#__feedcurator_failures` (
  `task_id` int unsigned NOT NULL,
  `failures` smallint unsigned NOT NULL DEFAULT 0,
  `since` datetime NOT NULL,
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

--
-- Bronnen waarvan de eenmalige terugblik (first_run_weeks) al gedaan is, per taak.
--
-- task_id    id van de geplande taak (#__scheduler_tasks)
-- url_hash   sha256 van de URL van de bron
-- first_run  moment van de terugblik (UTC)
--
CREATE TABLE IF NOT EXISTS `#__feedcurator_sources` (
  `task_id` int unsigned NOT NULL,
  `url_hash` char(64) NOT NULL,
  `first_run` datetime NOT NULL,
  PRIMARY KEY (`task_id`, `url_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
