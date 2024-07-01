<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

/*
 * https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/Events/Events/Backend/AfterPageTreeItemsPreparedEvent.html
 */
final class AfterPageTreeItemsPreparedListener
{
    public function __invoke(AfterPageTreeItemsPreparedEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }

        $items = $event->getItems();
        foreach ($items as &$item) {
            if (isset($item['_page']['tx_ximatypo3contentplanner_status'])) {
                $item['backgroundColor'] = Configuration::STATUS_COLORS[$item['_page']['tx_ximatypo3contentplanner_status']];
            }
        }

        $event->setItems($items);
    }
}
