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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\ButtonBar\CommentFormButtonPolicy;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\DropDownSelectionService;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\{ComponentFactoryUtility, RouteUtility};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;
use function str_contains;

/**
 * ModifyButtonBarEventListener.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(identifier: 'xima-typo3-content-planner/backend/modify-button-bar')]
final readonly class ModifyButtonBarEventListener
{
    public function __construct(
        private IconFactory $iconFactory,
        private StatusRepository $statusRepository,
        private RecordRepository $recordRepository,
        private DropDownSelectionService $dropDownSelectionService,
        private FolderStatusRepository $folderStatusRepository,
        private CommentFormButtonPolicy $commentFormButtonPolicy,
    ) {}

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return;
        }

        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];

        if (!$this->isValidModule($request)) {
            return;
        }

        // Handle file list module (folders)
        if ($this->isFileListModule($request)) {
            $this->handleFileListModule($event, $request);

            return;
        }

        $table = $this->extractTableFromRequest($request);
        if (null === $table) {
            return;
        }

        if (Configuration::TABLE_COMMENT === $table) {
            $this->removeButtonsExceptSave($event, $this->isEmbeddedCommentsViewFlow($request));

            return;
        }

        if (!ExtensionUtility::isRegisteredRecordTable($table)) {
            return;
        }

        $uid = $this->extractUidFromRequest($request, $table);
        $record = $this->recordRepository->findByUid($table, $uid, ignoreVisibilityRestriction: true);

        if (!$record) {
            return;
        }

        $this->addStatusDropdownButton($event, $table, $uid, $record);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function isValidModule(ServerRequestInterface $request): bool
    {
        if (!$request->getAttribute('module') instanceof ModuleInterface) {
            return true;
        }

        return RouteUtility::isContentPlannerSupportedModule($request->getAttribute('module')->getIdentifier());
    }

    private function extractTableFromRequest(ServerRequestInterface $request): ?string
    {
        if (isset($request->getQueryParams()['edit'])) {
            return array_key_first($request->getQueryParams()['edit']);
        }

        if (isset($request->getQueryParams()['id'])) {
            return 'pages';
        }

        return null;
    }

    private function extractUidFromRequest(ServerRequestInterface $request, string $table): int
    {
        if ('pages' === $table) {
            $parsedBody = $request->getParsedBody();
            $queryParams = $request->getQueryParams();

            if (isset($parsedBody['id'])) {
                return (int) $parsedBody['id'];
            }
            if (isset($queryParams['id'])) {
                return (int) $queryParams['id'];
            }
            if (isset($queryParams['edit']['pages'])) {
                return (int) array_key_first($queryParams['edit']['pages']);
            }

            return 0;
        }

        return (int) array_key_first($request->getQueryParams()['edit'][$table]);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function addStatusDropdownButton(ModifyButtonBarEvent $event, string $table, int $uid, array $record): void
    {
        $status = $this->resolveStatusFromRecord($record);
        $buttonsToAdd = $this->dropDownSelectionService->generateSelection($table, $uid);

        $this->attachDropdownToButtonBar($event, $status, $buttonsToAdd);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function resolveStatusFromRecord(array $record): ?Status
    {
        if (!isset($record[Configuration::FIELD_STATUS])
            || !is_numeric($record[Configuration::FIELD_STATUS])
            || $record[Configuration::FIELD_STATUS] <= 0
        ) {
            return null;
        }

        return $this->statusRepository->findByUid($record[Configuration::FIELD_STATUS]);
    }

    /**
     * Strips the comment edit form down to its save button, because the backend modal
     * supplies its own close control.
     *
     * The embeddable comments view is the exception. It reaches the same form through a
     * plain link with a returnUrl, so nothing is going to close the form for it: keeping
     * only the save button would leave the user stranded in the form with no way back.
     * There the core close button stays, and the save button keeps its own label — calling
     * it "Save and Close" is only true while the modal is the one doing the closing.
     */
    private function removeButtonsExceptSave(ModifyButtonBarEvent $event, bool $keepCloseButton = false): void
    {
        $event->setButtons($this->commentFormButtonPolicy->apply($event->getButtons(), $keepCloseButton));
    }

    /**
     * True when the comment form was opened from the embeddable comments view, which is
     * recognisable by the returnUrl it sends along. Present on the GET that renders the form
     * and, through the form action, on the save that follows.
     */
    private function isEmbeddedCommentsViewFlow(ServerRequestInterface $request): bool
    {
        $returnUrl = (string) ($request->getQueryParams()['returnUrl'] ?? '');

        return str_contains($returnUrl, Configuration::ROUTE_PATH_COMMENTS_VIEW);
    }

    private function isFileListModule(ServerRequestInterface $request): bool
    {
        if (!ExtensionUtility::isFilelistSupportEnabled()) {
            return false;
        }

        $module = $request->getAttribute('module');
        if (!$module instanceof ModuleInterface) {
            return false;
        }

        return RouteUtility::isFileListRoute($module->getIdentifier());
    }

    private function handleFileListModule(ModifyButtonBarEvent $event, ServerRequestInterface $request): void
    {
        $folderIdentifier = $request->getQueryParams()['id'] ?? null;
        if (null === $folderIdentifier || '' === $folderIdentifier) {
            return;
        }

        $folderRecord = $this->folderStatusRepository->findByCombinedIdentifier($folderIdentifier);
        $status = null;

        if (is_array($folderRecord) && isset($folderRecord[Configuration::FIELD_STATUS]) && 0 !== (int) $folderRecord[Configuration::FIELD_STATUS]) {
            $status = $this->statusRepository->findByUid((int) $folderRecord[Configuration::FIELD_STATUS]);
        }

        $this->addFolderStatusDropdownButton($event, $folderIdentifier, $status);
    }

    private function addFolderStatusDropdownButton(ModifyButtonBarEvent $event, string $folderIdentifier, ?Status $status): void
    {
        $buttonsToAdd = $this->dropDownSelectionService->generateFolderSelection($folderIdentifier);

        $this->attachDropdownToButtonBar($event, $status, $buttonsToAdd);
    }

    /**
     * @param array<string, \TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItemInterface>|false $buttonsToAdd
     */
    private function attachDropdownToButtonBar(ModifyButtonBarEvent $event, ?Status $status, array|false $buttonsToAdd): void
    {
        if (false === $buttonsToAdd) {
            return;
        }

        $dropDownButton = ComponentFactoryUtility::createDropDownButton()
            ->setLabel('Dropdown')
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:status'))
            ->setIcon($this->iconFactory->getIcon(
                $status instanceof Status ? $status->getColoredIcon() : 'flag-gray',
            ));

        foreach ($buttonsToAdd as $buttonToAdd) {
            if (!$buttonToAdd->isValid()) {
                continue;
            }
            $dropDownButton->addItem($buttonToAdd);
        }

        $buttons = $event->getButtons();
        $buttons['right'] ??= [];
        $buttons['right'][] = [$dropDownButton];
        $event->setButtons($buttons);
    }
}
