CREATE TABLE pages
(
	tx_ximatypo3contentplanner_status   int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_assignee int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_comments int(11) unsigned default '0' not null,
);

CREATE TABLE be_users
(
	tx_ximatypo3contentplanner_hide tinyint(4) unsigned DEFAULT 0 NOT NULL,
);

CREATE TABLE tx_ximatypo3contentplanner_comment
(
	uid           int(11) NOT NULL auto_increment,
	pid           int(11) DEFAULT '0' NOT NULL,

	foreign_uid   int(11) default '0' not null,
	foreign_table varchar(255) default '' not null,

	content       text,
	author        int(11) DEFAULT NULL,
	resolved      varchar(255) default '' not null,
	todo_resolved int(11) unsigned NOT NULL DEFAULT 0,
	todo_total    int(11) unsigned NOT NULL DEFAULT 0,
	PRIMARY KEY (uid)
);

CREATE TABLE tx_ximatypo3contentplanner_domain_model_status
(
	uid       int(11) NOT NULL auto_increment,
	pid       int(11) DEFAULT '0' NOT NULL,

	cruser_id int(11) DEFAULT '0' NOT NULL,
	sorting   int(11) unsigned default '0' not null,

	title     varchar(255) DEFAULT '' NOT NULL,
	icon      varchar(255) DEFAULT '' NOT NULL,
	color     varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (uid)
);
