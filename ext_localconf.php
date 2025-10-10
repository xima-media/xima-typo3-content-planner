<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use TYPO3\CMS\Backend\Controller\Page\TreeController as BackendTreeController;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\TreeController;
use Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook;

defined('TYPO3') || exit;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][BackendTreeController::class] = [
    'className' => TreeController::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ximatypo3contentplanner_cache'] ??= [];
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['ximatypo3contentplanner_cache'] = DataHandlerHook::class.'->clearCachePostProc';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = DataHandlerHook::class;

$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = [];

$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['comments'] = 'EXT:'.Configuration::EXT_KEY.'/Configuration/RTE/Comments.yaml';
