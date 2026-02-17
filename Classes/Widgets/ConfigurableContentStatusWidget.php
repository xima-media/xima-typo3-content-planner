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

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\{JavaScriptModuleInstruction, PageRenderer};
use TYPO3\CMS\Core\Settings\SettingDefinition;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\{AdditionalCssInterface, JavaScriptInterface, WidgetConfigurationInterface, WidgetContext, WidgetRendererInterface, WidgetResult};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\{IconUtility, ViewUtility};

use function count;
use function is_array;
use function sprintf;

/**
 * ConfigurableContentStatusWidget.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ConfigurableContentStatusWidget implements WidgetRendererInterface, AdditionalCssInterface, JavaScriptInterface
{
    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly StatusRepository $statusRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly RecordRepository $recordRepository,
        private readonly PageRenderer $pageRenderer,
    ) {}

    /**
     * @return SettingDefinition[]
     *
     * @throws Exception
     */
    public function getSettingsDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'title',
                type: 'string',
                default: '',
                label: 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.title.label',
                description: 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.title.description',
            ),
            new SettingDefinition(
                key: 'mode',
                type: 'string',
                default: 'status',
                label: 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.mode.label',
                description: 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.mode.description',
                enum: $this->getModeOptions(),
            ),
            new SettingDefinition(
                key: 'status',
                type: 'string',
                default: '',
                label: 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.status.label',
                description: 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.status.description',
                enum: $this->getStatusOptions(),
            ),
            new SettingDefinition(
                key: 'assignee',
                type: 'string',
                default: '',
                label: 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.assignee.label',
                description: 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.assignee.description',
                enum: $this->getAssigneeOptions(),
            ),
            new SettingDefinition(
                key: 'recordType',
                type: 'string',
                default: '',
                label: 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.recordType.label',
                description: 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.recordType.description',
                enum: $this->getRecordTypeOptions(),
            ),
        ];
    }

    /**
     * @throws Exception
     */
    public function renderWidget(WidgetContext $context): WidgetResult
    {
        $pageRenderer = $this->pageRenderer;
        $pageRenderer->addInlineLanguageLabelFile('EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf');

        $customTitle = $this->getSetting($context, 'title', '');
        $mode = $this->getSetting($context, 'mode', 'status');
        $statusFilter = $this->getSetting($context, 'status', '');
        $assignee = $this->resolveAssigneeFilter($context);
        $status = '' !== $statusFilter ? (int) $statusFilter : null;
        $type = $this->resolveRecordTypeFilter($context);
        $todo = 'todo' === $mode;

        $items = $this->loadItems($status, $assignee, $type, $todo);
        $hasSite = array_filter($items, static fn (array $item): bool => '' !== ($item['site'] ?? ''));

        $content = ViewUtility::render(
            'Backend/Widgets/ConfigurableContentStatusList.html',
            [
                'configuration' => $this->configuration,
                'icon' => $this->determineIcon($mode, $assignee),
                'items' => $items,
                'itemCount' => count($items),
                'todo' => $todo ? $this->buildTodoInfo() : false,
                'mode' => $mode,
                'hasAssigneeFilter' => null !== $assignee,
                'hasSite' => [] !== $hasSite,
            ],
            $context->request,
        );

        return new WidgetResult(
            content: $content,
            label: $this->getWidgetLabel($customTitle, $mode, $statusFilter, $assignee),
            refreshable: true,
        );
    }

    /**
     * @return string[]
     */
    public function getCssFiles(): array
    {
        return [
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Widgets.css',
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Comments.css',
        ];
    }

    /**
     * @return list<JavaScriptModuleInstruction>
     */
    public function getJavaScriptModuleInstructions(): array
    {
        return [
            JavaScriptModuleInstruction::create('@xima/ximatypo3contentplanner/comments-list-modal.js'),
        ];
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function getSetting(WidgetContext $context, string $key, string $default): string
    {
        return $context->settings->has($key) ? (string) $context->settings->get($key) : $default;
    }

    private function resolveAssigneeFilter(WidgetContext $context): ?int
    {
        $assigneeFilter = $this->getSetting($context, 'assignee', '');

        if ('current_user' === $assigneeFilter) {
            return $GLOBALS['BE_USER']->getUserId();
        }

        return '' !== $assigneeFilter ? (int) $assigneeFilter : null;
    }

    private function resolveRecordTypeFilter(WidgetContext $context): ?string
    {
        $recordTypeFilter = $this->getSetting($context, 'recordType', '');

        return '' !== $recordTypeFilter ? $recordTypeFilter : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    private function loadItems(?int $status, ?int $assignee, ?string $type, bool $todo): array
    {
        $records = $this->recordRepository->findAllByFilter(
            status: $status,
            assignee: $assignee,
            type: $type,
            todo: $todo,
        );

        $items = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                $items[] = StatusItem::create($record)->toArray();
            }
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function getModeOptions(): array
    {
        return [
            'status' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.mode.status',
            'assignee' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.mode.assignee',
            'todo' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.mode.todo',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getStatusOptions(): array
    {
        $options = [
            '' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.status.all',
        ];

        foreach ($this->statusRepository->findAll() as $status) {
            $options[(string) $status->getUid()] = $status->getTitle();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     *
     * @throws Exception
     */
    private function getAssigneeOptions(): array
    {
        $options = [
            '' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.assignee.all',
            'current_user' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.assignee.currentUser',
        ];

        foreach ($this->backendUserRepository->findAll() as $user) {
            $options[(string) $user['uid']] = $user['username'].('' !== $user['realName'] ? ' ('.$user['realName'].')' : '');
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function getRecordTypeOptions(): array
    {
        $options = [
            '' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.configurable.setting.recordType.all',
        ];

        foreach (ExtensionUtility::getRecordTables() as $table) {
            $options[$table] = $GLOBALS['TCA'][$table]['ctrl']['title'] ?? $table;
        }

        return $options;
    }

    private function determineIcon(string $mode, ?int $assignee): string
    {
        return match (true) {
            null !== $assignee && $assignee > 0 => 'status-user-backend',
            'todo' === $mode => 'form-multi-checkbox',
            default => 'flag-gray',
        };
    }

    private function buildTodoInfo(): string|bool
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            return false;
        }

        $commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
        $todoResolved = $commentRepository->countTodoAllByRecord(allRecords: true);
        $todoTotal = $commentRepository->countTodoAllByRecord(todoField: 'todo_total', allRecords: true);

        if ($todoTotal <= 0) {
            return '';
        }

        return sprintf(
            '%s <span class="xima-typo3-content-planner--comment-todo badge" data-status="%s">%d/%d</span>',
            IconUtility::getIconByIdentifier('actions-check-square'),
            $todoResolved === $todoTotal ? 'resolved' : 'pending',
            $todoResolved,
            $todoTotal,
        );
    }

    private function getWidgetLabel(string $customTitle, string $mode, string $statusFilter, ?int $assignee): string
    {
        if ('' !== $customTitle) {
            return $customTitle;
        }

        $langService = $this->getLanguageService();

        if ('todo' === $mode) {
            return $langService->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.contentPlanner.todo.title');
        }

        if (null !== $assignee && $assignee > 0) {
            return $langService->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.contentPlanner.current.title');
        }

        if ('' !== $statusFilter) {
            $status = $this->statusRepository->findByUid((int) $statusFilter);
            if (null !== $status) {
                return sprintf('%s: %s', $langService->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.contentPlanner.status.title'), $status->getTitle());
            }
        }

        return $langService->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.contentPlanner.status.title');
    }
}
