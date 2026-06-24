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
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentStatusDataProvider;

/**
 * ContentStatusDataProviderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentStatusDataProviderTest extends AbstractFunctionalTestCase
{
    private ContentStatusDataProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/../../Fixtures/be_users.csv');
        unset($GLOBALS['TYPO3_REQUEST']);
        $this->subject = new ContentStatusDataProvider(
            $this->get(StatusRepository::class),
            $this->get(BackendUserRepository::class),
        );
    }

    #[Test]
    public function getItemsReturnsEmptyArray(): void
    {
        self::assertSame([], $this->subject->getItems());
    }

    #[Test]
    public function getStatusReturnsAllStatusModels(): void
    {
        $status = $this->subject->getStatus();

        self::assertCount(3, $status);
        foreach ($status as $entry) {
            self::assertInstanceOf(Status::class, $entry);
        }
    }

    #[Test]
    public function getUsersReturnsBackendUsers(): void
    {
        $users = $this->subject->getUsers();

        self::assertNotEmpty($users);
    }
}
