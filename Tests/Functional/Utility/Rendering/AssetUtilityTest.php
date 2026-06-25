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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Utility\Rendering;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\AssetUtility;

/**
 * AssetUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AssetUtilityTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->setUpBackendRequest();
    }

    #[Test]
    public function getCssTagBuildsStylesheetLink(): void
    {
        $tag = AssetUtility::getCssTag(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Header.css',
            ['nonce' => 'abc123'],
        );

        self::assertStringContainsString('<link', $tag);
        self::assertStringContainsString('rel="stylesheet"', $tag);
        self::assertStringContainsString('nonce="abc123"', $tag);
    }

    #[Test]
    public function getJsTagBuildsModuleScript(): void
    {
        $tag = AssetUtility::getJsTag(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/JavaScript/comments-list-modal.js',
            ['nonce' => 'abc123'],
        );

        self::assertStringContainsString('<script', $tag);
        self::assertStringContainsString('type="module"', $tag);
        self::assertStringContainsString('nonce="abc123"', $tag);
    }

    #[Test]
    public function getPublicResourcePathResolvesExtensionPath(): void
    {
        $path = AssetUtility::getPublicResourcePath(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Header.css',
        );

        self::assertStringContainsString('Header.css', $path);
    }
}
