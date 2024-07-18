CREATE TABLE pages
(
	tx_ximatypo3contentplanner_status   int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_assignee int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_comments int(11) unsigned default '0' not null,
);

CREATE TABLE be_users
(
	tx_ximatypo3contentplanner_hide      tinyint(4) unsigned DEFAULT 0 NOT NULL,
	tx_ximatypo3contentplanner_subscribe varchar(255) default '' not null,
	tx_ximatypo3contentplanner_last_mail int(11) DEFAULT '0' NOT NULL,
);

CREATE TABLE tx_ximatypo3contentplanner_comment
(
	uid           int(11) NOT NULL auto_increment,
	pid           int(11) DEFAULT '0' NOT NULL,
	tstamp        int(11) DEFAULT '0' NOT NULL,
	crdate        int(11) DEFAULT '0' NOT NULL,
	cruser_id     int(11) DEFAULT '0' NOT NULL,
	deleted       tinyint(4) unsigned DEFAULT '0' NOT NULL,
	hidden        tinyint(4) unsigned DEFAULT '0' NOT NULL,

	foreign_uid   int(11) default '0' not null,
	foreign_table varchar(255) default '' not null,
	record_type   varchar(255) default '' not null,
	sorting       int(11) unsigned default '0' not null,

	content       text,
	author        int(11) DEFAULT NULL,
	PRIMARY KEY (uid)
);

CREATE TABLE tx_ximatypo3contentplanner_note
(
	uid       int(11) NOT NULL auto_increment,
	pid       int(11) DEFAULT '0' NOT NULL,
	tstamp    int(11) DEFAULT '0' NOT NULL,
	crdate    int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted   tinyint(4) unsigned DEFAULT '0' NOT NULL,
	hidden    tinyint(4) unsigned DEFAULT '0' NOT NULL,

	title     varchar(255) DEFAULT '' NOT NULL,
	content   mediumtext,
	PRIMARY KEY (uid)
);

CREATE TABLE tx_ximatypo3contentplanner_domain_model_status
(
	uid       int(11) NOT NULL auto_increment,
	pid       int(11) DEFAULT '0' NOT NULL,
	tstamp    int(11) DEFAULT '0' NOT NULL,
	crdate    int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted   tinyint(4) unsigned DEFAULT '0' NOT NULL,
	hidden    tinyint(4) unsigned DEFAULT '0' NOT NULL,
	sorting   int(11) unsigned default '0' not null,

	title     varchar(255) DEFAULT '' NOT NULL,
	icon      varchar(255) DEFAULT '' NOT NULL,
	color     varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (uid)
);
