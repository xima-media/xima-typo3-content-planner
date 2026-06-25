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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Repository;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * BackendUserRepositoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class BackendUserRepositoryTest extends AbstractFunctionalTestCase
{
    private BackendUserRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups.csv');
        $this->subject = $this->get(BackendUserRepository::class);
    }

    #[Test]
    public function findAllReturnsAllBackendUsers(): void
    {
        $result = $this->subject->findAll();

        $usernames = array_map(static fn (array $row): string => $row['username'], $result);
        self::assertContains('admin', $usernames);
        self::assertContains('member', $usernames);
        self::assertContains('nogroup', $usernames);
    }

    #[Test]
    public function findByUidReturnsMatchingUser(): void
    {
        $result = $this->subject->findByUid(1);

        self::assertIsArray($result);
        self::assertSame('admin', $result['username']);
    }

    #[Test]
    public function findByUidReturnsFalseForUnknownUid(): void
    {
        self::assertFalse($this->subject->findByUid(999));
    }

    #[Test]
    public function findByUsernameReturnsMatchingUser(): void
    {
        $result = $this->subject->findByUsername('member');

        self::assertIsArray($result);
        self::assertSame(10, (int) $result['uid']);
    }

    #[Test]
    public function findByUsernameReturnsFalseForUnknownUsername(): void
    {
        self::assertFalse($this->subject->findByUsername('ghost'));
    }

    #[Test]
    public function getUsernameByUidReturnsRealNameWithUsername(): void
    {
        self::assertSame('Administrator (admin)', $this->subject->getUsernameByUid(1));
    }

    #[Test]
    public function getUsernameByUidReturnsEmptyStringForNull(): void
    {
        self::assertSame('', $this->subject->getUsernameByUid(null));
    }

    #[Test]
    public function getUsernameByUidReturnsEmptyStringForUnknownUid(): void
    {
        self::assertSame('', $this->subject->getUsernameByUid(999));
    }

    #[Test]
    public function findAllWithPermissionIncludesAdminAndAuthorizedGroupMembers(): void
    {
        $result = $this->subject->findAllWithPermission();

        $usernames = array_map(static fn (array $row): string => $row['username'], $result);
        // admin (admin flag), member (group 1 authorized), parentmember (group 2 -> subgroup 1 authorized).
        self::assertContains('admin', $usernames);
        self::assertContains('member', $usernames);
        self::assertContains('parentmember', $usernames);
    }

    #[Test]
    public function findAllWithPermissionExcludesDisabledDeletedAndUnauthorizedUsers(): void
    {
        $result = $this->subject->findAllWithPermission();

        $usernames = array_map(static fn (array $row): string => $row['username'], $result);
        self::assertNotContains('nogroup', $usernames);
        self::assertNotContains('disabled', $usernames);
        self::assertNotContains('deleted', $usernames);
    }
}
