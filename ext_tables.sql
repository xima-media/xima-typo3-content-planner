CREATE TABLE pages
(
	tx_ximatypo3contentplanner_status   varchar(255) default null,
	tx_ximatypo3contentplanner_assignee int(11) DEFAULT NULL,
	tx_ximatypo3contentplanner_comments int(11) unsigned default '0' not null,
);

CREATE TABLE be_users
(
	tx_ximatypo3contentplanner_hide tinyint(4) unsigned DEFAULT 0 NOT NULL,
);

CREATE TABLE tx_ximatypo3contentplanner_comment
(
	foreign_uid   int(11) default '0' not null,
	foreign_table varchar(255) default '' not null,
	record_type   varchar(255) default '' not null,
	sorting       int(11) unsigned default '0' not null,

	content       text,
	author        int(11) DEFAULT NULL,
);

CREATE TABLE tx_ximatypo3contentplanner_note
(
	title   varchar(255) DEFAULT '' NOT NULL,
	content mediumtext
);
