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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

/**
 * ExtensionUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ExtensionUtilityTest extends TestCase
{
    public function testIsPagetreeFacetsIntegrationEnabledMethodExists(): void
    {
        self::assertTrue(method_exists(ExtensionUtility::class, 'isPagetreeFacetsIntegrationEnabled'));
    }
}
