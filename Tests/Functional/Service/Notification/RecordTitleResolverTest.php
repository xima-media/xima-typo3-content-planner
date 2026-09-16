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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\Notification;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use Xima\XimaTypo3ContentPlanner\Service\Notification\RecordTitleResolver;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * RecordTitleResolverTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordTitleResolverTest extends AbstractFunctionalTestCase
{
    private RecordTitleResolver $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/../Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(RecordTitleResolver::class);
    }

    #[Test]
    public function resolveReturnsTheRecordTitleWhenTheRecordExists(): void
    {
        self::assertSame('Home', $this->subject->resolve('pages', 1));
    }

    #[Test]
    public function resolveReturnsTheNoRecordTitleFallbackWhenTheRecordDoesNotExist(): void
    {
        self::assertSame(BackendUtility::getNoRecordTitle(), $this->subject->resolve('pages', 9999));
    }
}
