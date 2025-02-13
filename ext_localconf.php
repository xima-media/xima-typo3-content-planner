<?php

declare(strict_types=1);

use Xima\XimaTypo3ContentPlanner\Configuration;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\Page\TreeController::class] = [
    'className' => \Xima\XimaTypo3ContentPlanner\Controller\TreeController::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ximatypo3contentplanner_cache'] ??= [];
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['ximatypo3contentplanner_cache'] = \Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook::class . '->clearCachePostProc';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook::class;

$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = [];
