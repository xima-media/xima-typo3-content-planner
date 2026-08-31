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

use TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * AfterPageTreeItemsPreparedListener.
 *
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ApiOverview/Events/Events/Backend/AfterPageTreeItemsPreparedEvent.html
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(identifier: 'xima-typo3-content-planner/backend/modify-page-tree-items')]
final readonly class AfterPageTreeItemsPreparedListener
{
    public function __construct(private StatusRepository $statusRepository) {}

    public function __invoke(AfterPageTreeItemsPreparedEvent $event): void
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return;
        }

        $items = $event->getItems();

        foreach ($items as $key => $item) {
            $statusUid = $item['_page'][Configuration::FIELD_STATUS] ?? null;

            if (null !== $statusUid && 0 !== (int) $statusUid) {
                $items[$key] = $this->applyStatusToItem($item, (int) $statusUid);
            } else {
                $items[$key] = $this->applyEmptyLabelWorkaround($item);
            }
        }

        $event->setItems($items);
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function applyStatusToItem(array $item, int $statusUid): array
    {
        $status = $this->statusRepository->findByUid($statusUid);
        if (!$status instanceof Status) {
            return $item;
        }

        $item['labels'][] = new \TYPO3\CMS\Backend\Dto\Tree\Label\Label(
            $status->getTitle(),
            Configuration\Colors::get($status->getColor()),
            Configuration::TREE_LABEL_PRIORITY_STATUS,
        );

        return $this->addStatusInformationIfEnabled($item);
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function addStatusInformationIfEnabled(array $item): array
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_TREE_STATUS_INFORMATION)) {
            return $item;
        }

        $commentCount = $item['_page'][Configuration::FIELD_COMMENTS] ?? 0;
        if ($commentCount <= 0) {
            return $item;
        }

        $statusInfo = $this->buildStatusInformation($item, $commentCount);
        if (null !== $statusInfo) {
            $item['statusInformation'][] = $statusInfo;
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function buildStatusInformation(array $item, int $commentCount): ?\TYPO3\CMS\Backend\Dto\Tree\Status\StatusInformation
    {
        $setting = ExtensionUtility::getExtensionSetting(Configuration::FEATURE_TREE_STATUS_INFORMATION);

        if ('todos' === $setting) {
            $pageUid = (int) ($item['_page']['uid'] ?? 0);
            if (0 === $pageUid) {
                return null;
            }

            $commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
            $todoResolved = $commentRepository->countTodoAllByRecord($pageUid, 'pages');
            $todoTotal = $commentRepository->countTodoAllByRecord($pageUid, 'pages', 'todo_total');

            if (0 === $todoTotal || $todoResolved === $todoTotal) {
                return null;
            }

            /** @var LanguageService $languageService */
            $languageService = $GLOBALS['LANG'];
            $label = "$todoResolved/$todoTotal ".$languageService->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments.todo');
            $icon = 'actions-check-square';
        } else {
            /** @var LanguageService $languageService */
            $languageService = $GLOBALS['LANG'];
            $label = $commentCount.' '.$languageService->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments');
            $icon = 'actions-message';
        }

        return new \TYPO3\CMS\Backend\Dto\Tree\Status\StatusInformation(
            $label,
            \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::NOTICE,
            0,
            $icon,
        );
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function applyEmptyLabelWorkaround(array $item): array
    {
        // Workaround for label behavior in TYPO3 13
        // Labels will be inherited from parent pages, if not set explicitly
        // Currently there is no way to suppress this behavior
        // @see https://github.com/TYPO3/typo3/blob/5619d59f00808f7bec7a311106fda6a52854c0bd/Build/Sources/TypeScript/backend/tree/tree.ts#L1224
        $item['labels'][] = new \TYPO3\CMS\Backend\Dto\Tree\Label\Label(
            '',
            'inherit',
            Configuration::TREE_LABEL_PRIORITY_EMPTY,
        );

        return $item;
    }
}
