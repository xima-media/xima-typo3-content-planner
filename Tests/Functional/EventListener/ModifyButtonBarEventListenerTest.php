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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Template\Components\{ButtonBar, ModifyButtonBarEvent};
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\EventListener\ModifyButtonBarEventListener;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\VersionUtility;

/**
 * ModifyButtonBarEventListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ModifyButtonBarEventListenerTest extends AbstractFunctionalTestCase
{
    private ModifyButtonBarEventListener $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importSharedDataSet('folders.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(ModifyButtonBarEventListener::class);
    }

    #[Test]
    public function addsStatusDropdownForPageEdit(): void
    {
        $this->setUpBackendRequest('record_edit', ['edit' => ['pages' => [1 => 'edit']]]);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertArrayHasKey('right', $event->getButtons());
        self::assertNotEmpty($event->getButtons()['right']);
    }

    #[Test]
    public function addsStatusDropdownForPageViaIdParam(): void
    {
        $this->setUpBackendRequest('web_layout', ['id' => 1]);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertArrayHasKey('right', $event->getButtons());
    }

    #[Test]
    public function doesNothingWhenNoTableResolvable(): void
    {
        $this->setUpBackendRequest('web_layout', []);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertArrayNotHasKey('right', $event->getButtons());
    }

    #[Test]
    public function doesNothingForUnregisteredTable(): void
    {
        $this->setUpBackendRequest('record_edit', ['edit' => ['sys_news' => [1 => 'edit']]]);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertArrayNotHasKey('right', $event->getButtons());
    }

    #[Test]
    public function doesNothingForUnknownPageRecord(): void
    {
        $this->setUpBackendRequest('record_edit', ['edit' => ['pages' => [999 => 'edit']]]);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertArrayNotHasKey('right', $event->getButtons());
    }

    #[Test]
    public function chipModeAddsAssigneeAndCommentButtonsForPageEdit(): void
    {
        // Default headerDisplayMode ("chip") is unset in the fixture setup.
        $this->setUpBackendRequest('record_edit', ['edit' => ['pages' => [1 => 'edit']]]);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        $linkButtons = $this->getLinkButtons($event);
        self::assertCount(2, $linkButtons);

        $assigneeButton = $linkButtons[0];
        self::assertStringContainsString('content-planner-link--assignee', $assigneeButton->getClasses());
        self::assertSame('1', $assigneeButton->getDataAttributes()['id']);
        self::assertSame('pages', $assigneeButton->getDataAttributes()['table']);

        $commentsButton = $linkButtons[1];
        self::assertStringContainsString('content-planner-link--comments', $commentsButton->getClasses());
        self::assertSame('pages', $commentsButton->getDataAttributes()['table']);
    }

    #[Test]
    public function bannerModeDoesNotAddAssigneeOrCommentButtons(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_HEADER_DISPLAY_MODE] = Configuration::HEADER_DISPLAY_MODE_BANNER;

        $this->setUpBackendRequest('record_edit', ['edit' => ['pages' => [1 => 'edit']]]);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertCount(0, $this->getLinkButtons($event));

        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_HEADER_DISPLAY_MODE]);
    }

    /**
     * @return LinkButton[]
     */
    private function getLinkButtons(ModifyButtonBarEvent $event): array
    {
        $buttons = [];
        foreach ($event->getButtons()['right'] ?? [] as $buttonGroup) {
            if ($buttonGroup[0] instanceof LinkButton) {
                $buttons[] = $buttonGroup[0];
            }
        }

        return $buttons;
    }

    /**
     * @param array<string, mixed> $buttons
     */
    private function createEvent(array $buttons = []): ModifyButtonBarEvent
    {
        $buttonBar = GeneralUtility::makeInstance(ButtonBar::class);

        // TYPO3 v14 added the request as a third constructor argument.
        return VersionUtility::is14OrHigher()
            ? new ModifyButtonBarEvent($buttons, $buttonBar, $GLOBALS['TYPO3_REQUEST'])
            : new ModifyButtonBarEvent($buttons, $buttonBar);
    }
}
