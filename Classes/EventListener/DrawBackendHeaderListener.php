<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use Xima\XimaTypo3ContentPlanner\Service\Header\{HeaderMode, InfoGenerator};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * DrawBackendHeaderListener.
 *
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Events/Events/Backend/ModifyPageLayoutContentEvent.html
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(identifier: 'xima-typo3-content-planner/backend/modify-page-module-content')]
final readonly class DrawBackendHeaderListener
{
    public function __construct(
        private PageRepository $pageRepository,
        private InfoGenerator $headerInfoGenerator,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return;
        }

        $id = (int) ($event->getRequest()->getQueryParams()['id'] ?? 0);
        $pageInfo = $this->pageRepository->getPage($id);

        if ([] === $pageInfo) {
            return;
        }

        $content = $this->headerInfoGenerator->generateStatusHeader(HeaderMode::WEB_LAYOUT, record: $pageInfo, table: 'pages');
        if (!$content) {
            return;
        }
        $event->addHeaderContent($content);
    }
}
