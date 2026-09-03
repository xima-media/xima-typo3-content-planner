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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Backend\Toolbar;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Page\PageRenderer;
use Xima\XimaTypo3ContentPlanner\Backend\Toolbar\NotificationToolbarItem;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationCenterDataProvider;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * NotificationToolbarItemTest.
 *
 * Issue #301 acceptance criterion: "New ToolbarItemInterface implementation registered and
 * rendering in both TYPO3 v13 and v14". The functional suite runs against whichever core version
 * is installed for the test run, so a green run here already exercises both when the CI matrix
 * runs the suite under both v13 and v14 (see the DDEV `install 13`/`install 14` targets) - full
 * visual/dark-mode verification is a manual browser check, not something this suite can assert.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationToolbarItemTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function toolbarItemIsRegistered(): void
    {
        self::assertContains(
            NotificationToolbarItem::class,
            $GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems'] ?? [],
        );
    }

    #[Test]
    public function checkAccessDeniesWhenTheDatabaseChannelIsDisabled(): void
    {
        $this->loginBackendUser(1);
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_CHANNEL_DATABASE, '0');

        self::assertFalse($this->createSubject()->checkAccess());
    }

    #[Test]
    public function checkAccessDeniesAUserWithoutContentPlannerAccess(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission.
        $this->loginBackendUser(2);
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_CHANNEL_DATABASE, '1');

        self::assertFalse($this->createSubject()->checkAccess());
    }

    #[Test]
    public function checkAccessGrantsAnAdminWhenTheDatabaseChannelIsEnabled(): void
    {
        $this->loginBackendUser(1);
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_CHANNEL_DATABASE, '1');

        self::assertTrue($this->createSubject()->checkAccess());
    }

    #[Test]
    public function hasDropDownIsAlwaysTrue(): void
    {
        self::assertTrue($this->createSubject()->hasDropDown());
    }

    #[Test]
    public function getAdditionalAttributesOnlyExposesTheCssClass(): void
    {
        // TYPO3\CMS\Backend\ViewHelpers\Toolbar\AttributesViewHelper only ever forwards this
        // array's `class` entry onto the toolbar <li> - anything else here would silently never
        // reach the DOM, so this class deliberately keeps the poll interval out of this array
        // and renders it inside getItem()'s own markup instead (see the next test).
        self::assertSame(['class' => 'content-planner-notification-toolbar-item'], $this->createSubject()->getAdditionalAttributes());
    }

    #[Test]
    public function getItemAndGetDropDownRenderWithoutErrorsForAnAdminWithNoNotifications(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->enableExtensionFeature(Configuration::CONF_NOTIFICATION_POLL_INTERVAL, '90');

        $item = $this->createSubject()->getItem();
        $dropDown = $this->createSubject()->getDropDown();

        self::assertStringContainsString('content-planner-notification-badge', $item);
        self::assertStringContainsString('data-poll-interval="90"', $item);
        self::assertStringContainsString('content-planner-notification-dropdown', $dropDown);
    }

    private function createSubject(): NotificationToolbarItem
    {
        return new NotificationToolbarItem(
            $this->get(NotificationCenterDataProvider::class),
            $this->get(PageRenderer::class),
        );
    }
}
