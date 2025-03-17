<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use Xima\XimaTypo3ContentPlanner\Service\Header\HeaderMode;
use Xima\XimaTypo3ContentPlanner\Service\Header\InfoGenerator;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

/*
* https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Events/Events/Backend/ModifyPageLayoutContentEvent.html#modifypagelayoutcontentevent
*/

final class DrawBackendHeaderListener
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly InfoGenerator $headerInfoGenerator
    ) {
    }

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }

        $id = (int)($event->getRequest()->getQueryParams()['id'] ?? 0);
        $pageInfo = $this->pageRepository->getPage($id);

        if (empty($pageInfo)) {
            return;
        }

        $content = $this->headerInfoGenerator->generateStatusHeader(HeaderMode::WEB_LAYOUT, record: $pageInfo, table: 'pages');
        if (!$content) {
            return;
        }
        $event->addHeaderContent($content);
    }
}
