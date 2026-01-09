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

use Doctrine\DBAL\Exception;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\RenderAdditionalContentToRecordListEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;

/**
 * RenderAdditionalContentToRecordListListener.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(identifier: 'xima-typo3-content-planner/backend/render-additional-content-to-record-list')]
final readonly class RenderAdditionalContentToRecordListListener
{
    public function __construct(
        private StatusRepository $statusRepository,
        private RecordRepository $recordRepository,
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(RenderAdditionalContentToRecordListEvent $event): void
    {
        if (!PermissionUtility::checkContentStatusVisibility()
            || !ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RECORD_LIST_STATUS_INFO)
        ) {
            return;
        }

        $request = $event->getRequest();
        $pid = $this->extractPidFromRequest($request);
        if (null === $pid) {
            return;
        }

        $table = $request->getQueryParams()['table'] ?? null;
        $records = $this->loadRecordsForTables($table, $pid);

        $css = $this->generateStatusCss($records);
        if ('' !== $css) {
            $event->addContentAbove("<style>$css</style>");
        }

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/record-list-status.js');
    }

    private function extractPidFromRequest(ServerRequestInterface $request): ?int
    {
        if (!array_key_exists('id', $request->getQueryParams())) {
            return null;
        }

        return (int) $request->getQueryParams()['id'];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     *
     * @throws Exception
     */
    private function loadRecordsForTables(?string $table, int $pid): array
    {
        if (null !== $table) {
            if (!ExtensionUtility::isRegisteredRecordTable($table)) {
                return [];
            }

            return [$table => $this->recordRepository->findByPid($table, $pid, ignoreVisibilityRestriction: true)];
        }

        $records = [];
        foreach (ExtensionUtility::getRecordTables() as $recordTable) {
            $records[$recordTable] = $this->recordRepository->findByPid($recordTable, $pid, ignoreVisibilityRestriction: true);
        }

        return $records;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $records
     */
    private function generateStatusCss(array $records): string
    {
        $css = '';

        foreach ($records as $tableName => $tableRecords) {
            if ([] === $tableRecords) {
                continue;
            }

            foreach ($tableRecords as $tableRecord) {
                $status = $this->statusRepository->findByUid($tableRecord[Configuration::FIELD_STATUS]);
                if ($status instanceof Status) {
                    $css .= 'tr[data-table="'.$tableName.'"][data-uid="'.$tableRecord['uid'].'"] > td { background-color: '.Configuration\Colors::get($status->getColor(), true).'; } ';
                }
            }
        }

        return $css;
    }
}
