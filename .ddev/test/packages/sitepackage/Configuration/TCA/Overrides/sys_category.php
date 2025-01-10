<?php

defined('TYPO3') or die('Access denied.');

call_user_func(function () {
    \Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility::addContentPlannerTabToTCA('sys_category');
});