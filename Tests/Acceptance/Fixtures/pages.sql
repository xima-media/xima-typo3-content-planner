insert into `pages` (`uid`, `pid`, `title`, `slug`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`,
										 `perms_groupid`, `perms_user`, `perms_group`, `perms_everybody`, `doktype`, `is_siteroot`, `tx_ximatypo3contentplanner_status`, `tx_ximatypo3contentplanner_assignee`)
values
	(2,1,'Projects','projects',0,0,0,1,1,31,31,1,1,0, 1,1),
	(3,1,'Team','team',0,0,0,1,1,31,31,1,1,0, 2,1),
	(4,1,'Services','services',0,0,0,1,1,31,31,1,1,0, 3,1),
	(5,1,'Contact','contact',0,0,0,1,1,31,31,1,1,0, 4,1),
	(6,1,'Categories','categories',0,0,0,1,1,31,31,1,254,0, null,null)
;
