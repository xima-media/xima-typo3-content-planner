<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
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
                $status = ContentUtility::getStatus($item['_page']['tx_ximatypo3contentplanner_status']);
                if ($status) {
                    $version = VersionNumberUtility::getCurrentTypo3Version();
                    if (version_compare($version, '13.0.0', '>=')) {
                        // @phpstan-ignore-next-line
                        $item['labels'][] = new \TYPO3\CMS\Backend\Dto\Tree\Label\Label(
                            label: $status->getTitle(),
                            color: Configuration::STATUS_COLOR_CODES[$status->getColor()],
                            priority: 1,
                        );
                    } else {
                        $item['backgroundColor'] = Configuration::STATUS_COLOR_CODES[$status->getColor()];
                    }
                }
            }
        }

        $event->setItems($items);
    }
}
