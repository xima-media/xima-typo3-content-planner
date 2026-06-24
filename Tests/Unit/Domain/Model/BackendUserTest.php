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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\BackendUser;

/**
 * BackendUserTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class BackendUserTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $user = new BackendUser();

        self::assertFalse($user->isHide());
        self::assertSame('', $user->getSubscribe());
        self::assertSame(0, $user->getLastMail());
    }

    #[Test]
    public function hideCanBeSetAndRetrieved(): void
    {
        $user = new BackendUser();
        $user->setHide(true);

        self::assertTrue($user->isHide());
    }

    #[Test]
    public function subscribeCanBeSetAndRetrieved(): void
    {
        $user = new BackendUser();
        $user->setSubscribe('pages,tt_content');

        self::assertSame('pages,tt_content', $user->getSubscribe());
    }

    #[Test]
    public function lastMailCanBeSetAndRetrieved(): void
    {
        $user = new BackendUser();
        $user->setLastMail(1718000000);

        self::assertSame(1718000000, $user->getLastMail());
    }
}
