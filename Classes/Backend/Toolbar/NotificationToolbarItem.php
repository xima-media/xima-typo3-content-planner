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

namespace Xima\XimaTypo3ContentPlanner\Backend\Toolbar;

use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationCenterDataProvider;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\ViewUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * NotificationToolbarItem.
 *
 * Backend toolbar notification center (issue #301): an unread-count badge and a dropdown listing
 * the 10 most recent notifications for the current backend user, unread first. Deliberately
 * pull-based per the issue's motivation - no websockets/SSE - refreshed on backend load and,
 * optionally, via client-side polling (see {@see ExtensionUtility::getNotificationPollInterval()}
 * and `Resources/Public/JavaScript/toolbar-notification-center.js`).
 *
 * Kept thin: all permission-checked data assembly (title/link resolution, reason localization,
 * unread counting) lives in {@see NotificationCenterDataProvider}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class NotificationToolbarItem implements ToolbarItemInterface
{
    public function __construct(
        private readonly NotificationCenterDataProvider $dataProvider,
        private readonly PageRenderer $pageRenderer,
    ) {}

    public function checkAccess(): bool
    {
        $backendUser = $this->getBackendUser();
        if (null === $backendUser->user || '_cli_' === ($backendUser->user['username'] ?? null)) {
            return false;
        }

        return ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_NOTIFICATION_CHANNEL_DATABASE)
            && PermissionUtility::checkContentStatusVisibility();
    }

    public function getItem(): string
    {
        $this->loadAssets();

        $backendUserUid = PermissionUtility::getCurrentUserId();
        $unreadCount = null !== $backendUserUid ? $this->dataProvider->getUnreadCount($backendUserUid) : 0;

        return ViewUtility::render('Toolbar/NotificationToolbarItem.html', [
            'unreadCount' => $unreadCount,
            'badgeLabel' => null !== $backendUserUid ? $this->dataProvider->getUnreadBadgeLabel($backendUserUid, $unreadCount) : '0',
            'pollInterval' => ExtensionUtility::getNotificationPollInterval(),
        ]);
    }

    public function hasDropDown(): bool
    {
        return true;
    }

    /**
     * Rendered as part of the backend chrome on every full page load, whether or not anyone
     * opens it. Building the list here meant a SELECT plus, per entry, a record lookup and a
     * permission check on every one of those loads. The list is therefore left empty and
     * fetched by toolbar-notification-center.js the first time the dropdown is opened, which
     * is the same endpoint the poll already uses. Only the unread badge in getItem() still
     * costs a query, and that one has to be current without opening anything.
     */
    public function getDropDown(): string
    {
        return ViewUtility::render('Toolbar/NotificationDropDown.html', ['items' => []]);
    }

    /**
     * TYPO3\CMS\Backend\ViewHelpers\Toolbar\AttributesViewHelper only ever forwards this array's
     * `class` entry onto the toolbar `<li>` (it derives `id` itself from the class name and
     * ignores every other key) - so any other data this class's own JS module needs (the poll
     * interval) has to be rendered as an attribute inside {@see self::getItem()}'s own markup
     * instead, where it becomes a genuine DOM attribute.
     *
     * @return array<string, string>
     */
    public function getAdditionalAttributes(): array
    {
        return [
            'class' => 'content-planner-notification-toolbar-item',
        ];
    }

    public function getIndex(): int
    {
        return 45;
    }

    private function loadAssets(): void
    {
        $this->pageRenderer->loadJavaScriptModule(Configuration::JAVASCRIPT_MODULE_PREFIX.'toolbar-notification-center.js');
        $this->pageRenderer->addCssFile('EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Toolbar.css');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf');
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
