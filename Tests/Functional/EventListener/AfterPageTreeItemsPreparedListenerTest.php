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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent;
use TYPO3\CMS\Backend\Dto\Tree\Label\Label;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\EventListener\AfterPageTreeItemsPreparedListener;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * AfterPageTreeItemsPreparedListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AfterPageTreeItemsPreparedListenerTest extends AbstractFunctionalTestCase
{
    private AfterPageTreeItemsPreparedListener $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_TREE_STATUS_INFORMATION] = 'comments';
        $this->importSharedDataSet('status.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('pagetree');
        $this->subject = $this->get(AfterPageTreeItemsPreparedListener::class);
    }

    #[Test]
    public function appliesStatusLabelForPageWithStatus(): void
    {
        $event = $this->createEvent([
            ['_page' => ['uid' => 1, Configuration::FIELD_STATUS => 1, Configuration::FIELD_COMMENTS => 0]],
        ]);

        $this->subject->__invoke($event);

        $items = $event->getItems();
        self::assertArrayHasKey('labels', $items[0]);
        self::assertInstanceOf(Label::class, $items[0]['labels'][0]);
        self::assertSame('Draft', $items[0]['labels'][0]->label);
    }

    #[Test]
    public function appliesEmptyLabelWorkaroundForPageWithoutStatus(): void
    {
        $event = $this->createEvent([
            ['_page' => ['uid' => 2, Configuration::FIELD_STATUS => 0]],
        ]);

        $this->subject->__invoke($event);

        $items = $event->getItems();
        self::assertInstanceOf(Label::class, $items[0]['labels'][0]);
        self::assertSame('', $items[0]['labels'][0]->label);
        self::assertSame('inherit', $items[0]['labels'][0]->color);
    }

    #[Test]
    public function ignoresUnknownStatusUid(): void
    {
        $event = $this->createEvent([
            ['_page' => ['uid' => 1, Configuration::FIELD_STATUS => 999, Configuration::FIELD_COMMENTS => 0]],
        ]);

        $this->subject->__invoke($event);

        $items = $event->getItems();
        self::assertArrayNotHasKey('labels', $items[0]);
    }

    #[Test]
    public function addsStatusInformationWhenCommentsPresentAndFeatureEnabled(): void
    {
        $event = $this->createEvent([
            ['_page' => ['uid' => 1, Configuration::FIELD_STATUS => 2, Configuration::FIELD_COMMENTS => 4]],
        ]);

        $this->subject->__invoke($event);

        $items = $event->getItems();
        self::assertArrayHasKey('statusInformation', $items[0]);
        self::assertNotEmpty($items[0]['statusInformation']);
    }

    #[Test]
    public function leavesItemsUntouchedWhenNoPageData(): void
    {
        $event = $this->createEvent([
            ['_page' => []],
        ]);

        $this->subject->__invoke($event);

        $items = $event->getItems();
        self::assertSame('', $items[0]['labels'][0]->label);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function createEvent(array $items): AfterPageTreeItemsPreparedEvent
    {
        return new AfterPageTreeItemsPreparedEvent(
            $this->createMock(ServerRequestInterface::class),
            $items,
        );
    }
}
