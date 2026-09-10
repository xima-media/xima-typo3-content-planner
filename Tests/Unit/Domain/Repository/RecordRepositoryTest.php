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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Domain\Repository;

use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;

/**
 * RecordRepositoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([]);
        // getRecordTables() calls isFeatureEnabled() twice (filelist + content element support),
        // each consuming one queued makeInstance() result.
        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfiguration);
        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfiguration);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function findAllByFilterBindsLimitParameterAsInteger(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeQuery')
            ->with(self::isType('string'), ['limit' => 5], ['limit' => Connection::PARAM_INT])
            ->willReturn($result);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getConnection')->willReturn($connection);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->with('pages')->willReturn($queryBuilder);

        $cache = $this->createMock(FrontendInterface::class);

        $subject = new RecordRepository($cache, $connectionPool);

        self::assertSame([], $subject->findAllByFilter(null, null, null, null, null, 5));
    }
}
