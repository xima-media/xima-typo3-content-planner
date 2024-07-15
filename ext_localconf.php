<?php

declare(strict_types=1);

use Xima\XimaTypo3ContentPlanner\Configuration;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\Page\TreeController::class] = [
    'className' => \Xima\XimaTypo3ContentPlanner\Controller\TreeController::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1719240005] = [
    'nodeName' => 'currentUser',
    'priority' => 40,
    'class' => \Xima\XimaTypo3ContentPlanner\Form\Element\CurrentUser::class,
];

$GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems'][1719820170] = \Xima\XimaTypo3ContentPlanner\Backend\ToolbarItems\UpdateItem::class;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ximatypo3contentplanner_toolbarcache'] ??= [];

$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][486] = 'EXT:xima_typo3_content_planner/Resources/Private/Templates/Email/';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook::class;


// Feature toggles
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['features']['autoAssignment'] = true;
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['features']['autoUnassignment'] = true;

$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = [];
