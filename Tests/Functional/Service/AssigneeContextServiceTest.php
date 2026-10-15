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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Service\AssigneeContextService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * AssigneeContextServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AssigneeContextServiceTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('be_users.csv');
        $this->importCSVDataSet(__DIR__.'/../Domain/Repository/Fixtures/sys_history_assignments.csv');
    }

    #[Test]
    public function describeNamesTheAssigneeAndWhenAndByWhomTheyWereAssigned(): void
    {
        $context = $this->get(AssigneeContextService::class)->describe('pages', 1, 1);

        self::assertSame(1, $context['uid']);
        self::assertStringContainsString('admin', $context['name']);
        self::assertSame('Administrator', $context['shortName']);
        self::assertSame(2000, $context['since']);
        self::assertStringContainsString('editor', $context['by']);
    }

    #[Test]
    public function describeKeepsTheAssigneeWhenNoHistoryExplainsTheAssignment(): void
    {
        $context = $this->get(AssigneeContextService::class)->describe('pages', 3, 1);

        self::assertSame(1, $context['uid']);
        self::assertNull($context['since']);
        self::assertSame('', $context['by']);
    }

    #[Test]
    public function describeReturnsNullForARecordWithoutAssignee(): void
    {
        self::assertNull($this->get(AssigneeContextService::class)->describe('pages', 1, 0));
    }

    #[Test]
    public function describeReturnsNullForAnAssigneeWhoNoLongerExists(): void
    {
        self::assertNull($this->get(AssigneeContextService::class)->describe('pages', 1, 9999));
    }
}
