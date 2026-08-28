CREATE TABLE pages
(
	tx_ximatypo3contentplanner_status   int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_assignee int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_comments int(11) unsigned default '0' not null,
	KEY contentplanner_status (tx_ximatypo3contentplanner_status),
	KEY contentplanner_assignee (tx_ximatypo3contentplanner_assignee)
);

CREATE TABLE be_users
(
	tx_ximatypo3contentplanner_hide tinyint(4) unsigned DEFAULT 0 NOT NULL,
);

CREATE TABLE be_groups
(
	tx_ximatypo3contentplanner_allowed_statuses text,
	tx_ximatypo3contentplanner_allowed_tables text,
);

CREATE TABLE tx_ximatypo3contentplanner_comment
(
	uid           int(11) NOT NULL auto_increment,
	pid           int(11) DEFAULT '0' NOT NULL,

	foreign_uid   int(11) default '0' not null,
	foreign_table varchar(255) default '' not null,

	content       text,
	author        int(11) DEFAULT NULL,
	edited        tinyint(4) unsigned DEFAULT 0 NOT NULL,
	resolved_user int(11) DEFAULT '0' NOT NULL,
	resolved_date int(11) DEFAULT '0' NOT NULL,
	todo_resolved int(11) unsigned NOT NULL DEFAULT 0,
	todo_total    int(11) unsigned NOT NULL DEFAULT 0,
	parent_uid    int(11) DEFAULT '0' NOT NULL,
	PRIMARY KEY (uid),
	KEY foreign_record (foreign_table(64), foreign_uid),
	KEY parent (parent_uid)
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

CREATE TABLE tt_content
(
    tx_ximatypo3contentplanner_status   int(11) DEFAULT NULL,
    tx_ximatypo3contentplanner_assignee int(11) DEFAULT NULL,
    tx_ximatypo3contentplanner_comments int(11) unsigned DEFAULT '0' NOT NULL,
    KEY contentplanner_status (tx_ximatypo3contentplanner_status),
    KEY contentplanner_assignee (tx_ximatypo3contentplanner_assignee)
);

CREATE TABLE sys_file_metadata
(
	tx_ximatypo3contentplanner_status   int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_assignee int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_comments int(11) unsigned DEFAULT '0' NOT NULL,
	KEY contentplanner_status (tx_ximatypo3contentplanner_status),
	KEY contentplanner_assignee (tx_ximatypo3contentplanner_assignee)
);

CREATE TABLE tx_ximatypo3contentplanner_watcher
(
	uid           int(11) NOT NULL auto_increment,
	pid           int(11) DEFAULT '0' NOT NULL,

	tablename     varchar(255) DEFAULT '' NOT NULL,
	record_uid    int(11) DEFAULT '0' NOT NULL,
	backend_user  int(11) DEFAULT '0' NOT NULL,

	mode          varchar(32) DEFAULT 'auto' NOT NULL,
	source        varchar(32) DEFAULT 'manual' NOT NULL,

	crdate        int(11) DEFAULT '0' NOT NULL,
	tstamp        int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	UNIQUE KEY watcher_lookup (tablename(64), record_uid, backend_user),
	-- backend_user is the third column of watcher_lookup, so that key cannot serve
	-- "everything user X watches". That is the lookup behind the "watched by me" filter
	-- and the retention cleanup, both of which would otherwise scan the whole table.
	KEY watcher_by_user (backend_user, mode)
);

CREATE TABLE tx_ximatypo3contentplanner_notification
(
	uid           int(11) NOT NULL auto_increment,
	pid           int(11) DEFAULT '0' NOT NULL,

	backend_user  int(11) DEFAULT '0' NOT NULL,
	event_type    varchar(32) DEFAULT '' NOT NULL,
	tablename     varchar(255) DEFAULT '' NOT NULL,
	record_uid    int(11) DEFAULT '0' NOT NULL,
	actor         int(11) DEFAULT NULL,
	reason        varchar(64) DEFAULT '' NOT NULL,
	payload       text,

	read_at       int(11) DEFAULT NULL,
	digested_at   int(11) DEFAULT NULL,
	crdate        int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY recipient (backend_user),
	KEY record (tablename(64), record_uid)
);

CREATE TABLE tx_ximatypo3contentplanner_notification
(
	uid           int(11) NOT NULL auto_increment,
	pid           int(11) DEFAULT '0' NOT NULL,

	backend_user  int(11) DEFAULT '0' NOT NULL,
	event_type    varchar(32) DEFAULT '' NOT NULL,
	tablename     varchar(255) DEFAULT '' NOT NULL,
	record_uid    int(11) DEFAULT '0' NOT NULL,
	actor         int(11) DEFAULT NULL,
	reason        varchar(64) DEFAULT '' NOT NULL,
	payload       text,

	read_at       int(11) DEFAULT NULL,
	digested_at   int(11) DEFAULT NULL,
	crdate        int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY recipient (backend_user),
	KEY record (tablename(64), record_uid)
);

CREATE TABLE tx_ximatypo3contentplanner_folder
(
	uid               int(11) NOT NULL auto_increment,
	pid               int(11) DEFAULT '0' NOT NULL,

	folder_identifier varchar(255) DEFAULT '' NOT NULL,
	storage_uid       int(11) DEFAULT '0' NOT NULL,

	tx_ximatypo3contentplanner_status   int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_assignee int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_comments int(11) unsigned DEFAULT '0' NOT NULL,

	tstamp            int(11) DEFAULT '0' NOT NULL,
	crdate            int(11) DEFAULT '0' NOT NULL,
	deleted           tinyint(4) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	UNIQUE KEY folder_lookup (storage_uid, folder_identifier(191))
);
