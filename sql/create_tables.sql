CREATE TABLE IF NOT EXISTS `prefix_xcollections_entity` (
  `guid` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `description` text NOT NULL,
  `key_id` int(11) NOT NULL,
  `items_type` varchar(50) NOT NULL,
  PRIMARY KEY (`guid`),
  KEY `key_id` (`key_id`),
  FULLTEXT KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `prefix_xcollection_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `guid` bigint(20) unsigned NOT NULL,
  `item` bigint(20) unsigned NOT NULL,
  `priority` int(11) unsigned,
  PRIMARY KEY (`id`),
  KEY `guid` (`guid`),
  KEY `priority` (`priority`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
