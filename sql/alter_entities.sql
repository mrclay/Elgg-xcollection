ALTER TABLE `prefix_entities`
CHANGE `type` `type` ENUM('object', 'user', 'group', 'site', 'xcollection') NOT NULL;