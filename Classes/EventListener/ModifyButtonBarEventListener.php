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
use TYPO3\CMS\Backend\Template\Components\Buttons\InputButton;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\Header\ChipTrioButtonBuilder;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\DropDownSelectionService;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\RouteUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;

/**
 * ModifyButtonBarEventListener.
 *
 * CP-25 (#324): in the default "chip" headerDisplayMode, this listener is also responsible
 * for the doc header trio (status dropdown, assignee button, comment button) that replaces
 * the retired RecordEditModifier banner. In the legacy "banner" mode it only adds the status
 * dropdown, as before.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(identifier: 'xima-typo3-content-planner/backend/modify-button-bar')]
final readonly class ModifyButtonBarEventListener
{
    public function __construct(
        private StatusRepository $statusRepository,
        private RecordRepository $recordRepository,
        private DropDownSelectionService $dropDownSelectionService,
        private FolderStatusRepository $folderStatusRepository,
        private ChipTrioButtonBuilder $chipTrioButtonBuilder,
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
            $this->removeButtonsExceptSave($event);

            return;
        }

        if (!ExtensionUtility::isRegisteredRecordTable($table)) {
            return;
        }

        $uid = $this->extractUidFromRequest($request, $table);
        $record = $this->recordRepository->findByUid($table, $uid, true);

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

        $this->chipTrioButtonBuilder->attachStatusDropdown($event, $status, $buttonsToAdd);

        // generateSelection() returns false when the user is not allowed to see status info
        // for this table (PermissionUtility::isTableAllowedForUser()) or none is configured;
        // the assignee/comment buttons must respect that same gate, not just the dropdown.
        if (false !== $buttonsToAdd) {
            $this->addChipTrioButtons($event, $table, $uid, $record);
        }
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

    private function removeButtonsExceptSave(ModifyButtonBarEvent $event): void
    {
        $buttons = [];

        foreach ($event->getButtons() as $position => $buttonGroup) {
            if ('right' === $position) {
                continue;
            }
            foreach ($buttonGroup as $button) {
                if ($button[0] instanceof InputButton && str_contains($button[0]->getName(), '_save')) {
                    $button[0]->setTitle($this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:save_and_close'));

                    $buttons[$position][] = $button;
                }
            }
        }
        $event->setButtons($buttons);
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

        $this->addFolderStatusDropdownButton($event, $folderIdentifier, $status, is_array($folderRecord) ? $folderRecord : []);
    }

    /**
     * @param array<string, mixed> $folderRecord
     */
    private function addFolderStatusDropdownButton(ModifyButtonBarEvent $event, string $folderIdentifier, ?Status $status, array $folderRecord): void
    {
        $buttonsToAdd = $this->dropDownSelectionService->generateFolderSelection($folderIdentifier);

        $this->chipTrioButtonBuilder->attachStatusDropdown($event, $status, $buttonsToAdd);

        if (false !== $buttonsToAdd && [] !== $folderRecord && isset($folderRecord['uid'])) {
            $this->addChipTrioButtons($event, Configuration::TABLE_FOLDER, (int) $folderRecord['uid'], $folderRecord);
        }
    }

    /**
     * CP-25 (#324): Level 1 doc header trio. Adds an assignee button and a comment button
     * next to the status dropdown (see ChipTrioButtonBuilder::attachStatusDropdown()) whenever the "chip"
     * headerDisplayMode is active. No-op in "banner" mode, where the equivalent controls
     * still live in the RecordEditModifier banner. Building the buttons themselves is
     * delegated to ChipTrioButtonBuilder to keep this listener's complexity manageable.
     *
     * @param array<string, mixed> $record
     */
    private function addChipTrioButtons(ModifyButtonBarEvent $event, string $table, int $uid, array $record): void
    {
        if (ExtensionUtility::isBannerDisplayModeEnabled()) {
            return;
        }

        $this->chipTrioButtonBuilder->addButtons($event, $table, $uid, $record);
    }
}
