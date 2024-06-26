CREATE TABLE pages
(
	tx_ximacontentplanner_status   varchar(255) default null,
	tx_ximacontentplanner_assignee int(11) DEFAULT NULL,
	tx_ximacontentplanner_comments int(11) unsigned default '0' not null,
);

CREATE TABLE be_users
(
	tx_ximacontentplanner_hide tinyint(4) unsigned DEFAULT 0 NOT NULL,
);

CREATE TABLE tx_ximacontentplanner_comment
(
	foreign_uid   int(11) default '0' not null,
	foreign_table varchar(255) default '' not null,
	record_type   varchar(255) default '' not null,
	sorting       int(11) unsigned default '0' not null,

	content       text,
	author        int(11) DEFAULT NULL,
);

CREATE TABLE tx_ximacontentplanner_note
(
	title   varchar(255) DEFAULT '' NOT NULL,
	content mediumtext
);
