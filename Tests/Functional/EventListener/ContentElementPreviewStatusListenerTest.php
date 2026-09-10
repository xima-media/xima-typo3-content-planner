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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Domain\RecordFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\EventListener\ContentElementPreviewStatusListener;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\VersionUtility;

/**
 * ContentElementPreviewStatusListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentElementPreviewStatusListenerTest extends AbstractFunctionalTestCase
{
    private ContentElementPreviewStatusListener $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/tt_content.csv');
        $this->loginBackendUser();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableContentElementSupport'] = 1;
        $this->subject = $this->get(ContentElementPreviewStatusListener::class);
    }

    #[Test]
    public function decoratesContentElementWithStatusAccentInChipMode(): void
    {
        $event = $this->buildEvent(1);

        ($this->subject)($event);

        $content = $event->getPreviewContent();
        self::assertNotNull($content);
        self::assertStringContainsString('content-planner-ce-accent--color-yellow', $content);
        self::assertStringContainsString('In Progress', $content);
    }

    #[Test]
    public function doesNothingWhenRecordHasNoStatus(): void
    {
        $event = $this->buildEvent(2);

        ($this->subject)($event);

        self::assertNull($event->getPreviewContent());
    }

    #[Test]
    public function doesNothingInBannerDisplayMode(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_HEADER_DISPLAY_MODE] = Configuration::HEADER_DISPLAY_MODE_BANNER;

        $event = $this->buildEvent(1);
        ($this->subject)($event);

        self::assertNull($event->getPreviewContent());

        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_HEADER_DISPLAY_MODE]);
    }

    #[Test]
    public function doesNothingWhenContentElementSupportDisabled(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableContentElementSupport']);

        $event = $this->buildEvent(1);
        ($this->subject)($event);

        self::assertNull($event->getPreviewContent());
    }

    private function buildEvent(int $uid): PageContentPreviewRenderingEvent
    {
        $record = BackendUtility::getRecord('tt_content', $uid);
        self::assertIsArray($record);

        /** @var PageLayoutContext&\PHPUnit\Framework\MockObject\MockObject $context */
        $context = $this->createMock(PageLayoutContext::class);
        $context->method('getPageId')->willReturn(1);

        // TYPO3 v13/v14 compatibility: the event's constructor takes a plain array in v13,
        // but requires a TYPO3\CMS\Core\Domain\RecordInterface in v14.
        $eventRecord = VersionUtility::is14OrHigher()
            ? $this->get(RecordFactory::class)->createResolvedRecordFromDatabaseRow('tt_content', $record)
            : $record;

        return new PageContentPreviewRenderingEvent('tt_content', 'header', $eventRecord, $context);
    }
}
