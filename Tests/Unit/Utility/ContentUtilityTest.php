<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

/**
 * ContentUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class ContentUtilityTest extends TestCase
{
    public function testGenerateDisplayNameWithrealNameAndUsername(): void
    {
        $user = [
            'username' => 'john.doe',
            'realName' => 'John Doe',
        ];

        $result = ContentUtility::generateDisplayName($user);

        self::assertSame('John Doe (john.doe)', $result);
    }

    public function testGenerateDisplayNameWithUsernameOnly(): void
    {
        $user = [
            'username' => 'jane.smith',
        ];

        $result = ContentUtility::generateDisplayName($user);

        self::assertSame('jane.smith', $result);
    }

    public function testGenerateDisplayNameWithEmptyrealName(): void
    {
        $user = [
            'username' => 'test.user',
            'realName' => '',
        ];

        $result = ContentUtility::generateDisplayName($user);

        self::assertSame('test.user', $result);
    }

    public function testGenerateDisplayNameWithoutUsername(): void
    {
        $user = [
            'realName' => 'Test User',
        ];

        $result = ContentUtility::generateDisplayName($user);

        self::assertSame('', $result);
    }

    public function testGenerateDisplayNameWithEmptyArray(): void
    {
        $user = [];

        $result = ContentUtility::generateDisplayName($user);

        self::assertSame('', $result);
    }

    public function testGenerateDisplayNameWithNullrealName(): void
    {
        $user = [
            'username' => 'test.user',
            'realName' => null,
        ];

        $result = ContentUtility::generateDisplayName($user);

        self::assertSame('test.user', $result);
    }

    public function testGenerateDisplayNameWithNumericValues(): void
    {
        $user = [
            'username' => 'user123',
            'realName' => '123 Test User',
        ];

        $result = ContentUtility::generateDisplayName($user);

        self::assertSame('123 Test User (user123)', $result);
    }

    public function testGenerateDisplayNameWithSpecialCharacters(): void
    {
        $user = [
            'username' => 'test.user@example.com',
            'realName' => 'Test User (Admin)',
        ];

        $result = ContentUtility::generateDisplayName($user);

        self::assertSame('Test User (Admin) (test.user@example.com)', $result);
    }

    public function testGenerateDisplayNameWithrealNameZeroString(): void
    {
        $user = [
            'username' => 'test.user',
            'realName' => '0',
        ];

        $result = ContentUtility::generateDisplayName($user);

        self::assertSame('0 (test.user)', $result);
    }

    public function testGenerateDisplayNameWithWhitespaceOnlyrealName(): void
    {
        $user = [
            'username' => 'test.user',
            'realName' => '     ',
        ];

        $result = ContentUtility::generateDisplayName($user);

        self::assertSame('      (test.user)', $result);
    }
}
