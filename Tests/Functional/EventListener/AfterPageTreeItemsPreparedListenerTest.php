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
    public function statusLabelOutranksLabelsFromOtherSources(): void
    {
        // The tree renders only the highest-priority label per node. Core appends the
        // TSconfig label (priority 0) and the background color label (priority -1)
        // before this event is dispatched, so both already sit in front of ours.
        $event = $this->createEvent([
            [
                '_page' => ['uid' => 1, Configuration::FIELD_STATUS => 1, Configuration::FIELD_COMMENTS => 0],
                'labels' => [
                    new Label(label: 'Color: #00ff00', color: '#00ff00', priority: -1),
                    new Label(label: 'TSconfig', color: '#ff0000'),
                ],
            ],
        ]);

        $this->subject->__invoke($event);

        $items = $event->getItems();
        self::assertSame('Draft', $this->winningLabel($items[0]['labels'])->label);
    }

    #[Test]
    public function emptyLabelWorkaroundDoesNotOutrankLabelsFromOtherSources(): void
    {
        $event = $this->createEvent([
            [
                '_page' => ['uid' => 2, Configuration::FIELD_STATUS => 0],
                'labels' => [
                    new Label(label: 'Color: #00ff00', color: '#00ff00', priority: -1),
                ],
            ],
        ]);

        $this->subject->__invoke($event);

        $items = $event->getItems();
        self::assertSame('Color: #00ff00', $this->winningLabel($items[0]['labels'])->label);
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
     * Mirrors the core tree rendering, which sorts labels by priority descending
     * (stable) and paints only the first one.
     *
     * @see typo3/cms-backend/Resources/Public/JavaScript/tree/tree.js getNodeLabels()
     *
     * @param array<int, Label> $labels
     */
    private function winningLabel(array $labels): Label
    {
        usort($labels, static fn (Label $a, Label $b): int => $b->priority - $a->priority);

        return $labels[0];
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
