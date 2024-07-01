<?php

declare(strict_types=1);

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\Page\TreeController::class] = [
    'className' => \Xima\XimaTypo3ContentPlanner\Controller\TreeController::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1719240005] = [
    'nodeName' => 'currentUser',
    'priority' => 40,
    'class' => \Xima\XimaTypo3ContentPlanner\Form\Element\CurrentUser::class,
];
