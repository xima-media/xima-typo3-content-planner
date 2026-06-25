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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Widgets\Provider;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentCommentDataProvider;

/**
 * ContentCommentDataProviderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentCommentDataProviderTest extends AbstractFunctionalTestCase
{
    private ContentCommentDataProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('dashboard', ['id' => 1]);
        $this->subject = new ContentCommentDataProvider($this->get(ConnectionPool::class));
    }

    #[Test]
    public function getItemsReturnsOnlyOpenUndeletedComments(): void
    {
        $items = $this->subject->getItems();

        self::assertCount(1, $items);
        self::assertInstanceOf(CommentItem::class, $items[0]);
    }
}
