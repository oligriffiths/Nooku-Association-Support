
CREATE TABLE `jos_test_groups` (
  `group_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `jos_test_groups` WRITE;
/*!40000 ALTER TABLE `jos_test_groups` DISABLE KEYS */;

INSERT INTO `jos_test_groups` (`group_id`, `name`)
VALUES
	(1,'Group 1'),
	(2,'Group 2');

/*!40000 ALTER TABLE `jos_test_groups` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table jos_test_posts
# ------------------------------------------------------------

CREATE TABLE `jos_test_posts` (
  `post_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`post_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `jos_test_posts` WRITE;
/*!40000 ALTER TABLE `jos_test_posts` DISABLE KEYS */;

INSERT INTO `jos_test_posts` (`post_id`, `user_id`, `title`, `description`)
VALUES
	(1,1,'First post','This is my first post'),
	(2,1,'Second post','This is my second post'),
	(3,2,'First post','This is my first post');

/*!40000 ALTER TABLE `jos_test_posts` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table jos_test_profiles
# ------------------------------------------------------------

CREATE TABLE `jos_test_profiles` (
  `profile_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `jos_test_profiles` WRITE;
/*!40000 ALTER TABLE `jos_test_profiles` DISABLE KEYS */;

INSERT INTO `jos_test_profiles` (`profile_id`, `type`)
VALUES
	(1,'user'),
	(2,'company');

/*!40000 ALTER TABLE `jos_test_profiles` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table jos_test_user_documents
# ------------------------------------------------------------

CREATE TABLE `jos_test_user_documents` (
  `user_document_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`user_document_id`),
  KEY `user_id` (`user_id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

LOCK TABLES `jos_test_user_documents` WRITE;
/*!40000 ALTER TABLE `jos_test_user_documents` DISABLE KEYS */;

INSERT INTO `jos_test_user_documents` (`user_document_id`, `user_id`, `name`)
VALUES
	(1,1,'Test document 1'),
	(2,1,'Test document 2'),
	(3,2,'Test document 3');

/*!40000 ALTER TABLE `jos_test_user_documents` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table jos_test_users
# ------------------------------------------------------------

CREATE TABLE `jos_test_users` (
  `user_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `profile_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

LOCK TABLES `jos_test_users` WRITE;
/*!40000 ALTER TABLE `jos_test_users` DISABLE KEYS */;

INSERT INTO `jos_test_users` (`user_id`, `name`, `profile_id`)
VALUES
	(1,'Oli',1),
	(2,'Kyle',2);

/*!40000 ALTER TABLE `jos_test_users` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table jos_test_users_groups
# ------------------------------------------------------------

CREATE TABLE `jos_test_users_groups` (
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

LOCK TABLES `jos_test_users_groups` WRITE;
/*!40000 ALTER TABLE `jos_test_users_groups` DISABLE KEYS */;

INSERT INTO `jos_test_users_groups` (`user_id`, `group_id`)
VALUES
	(1,1),
	(1,2);