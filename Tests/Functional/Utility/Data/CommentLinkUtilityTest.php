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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Utility\Data;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\{NormalizedParams, ServerRequest};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Data\CommentLinkUtility;

/**
 * CommentLinkUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentLinkUtilityTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->initBackendRequest();
    }

    #[Test]
    public function turnsAPageLinkIntoTheLayoutModuleOfThatPage(): void
    {
        $rendered = CommentLinkUtility::resolveLinks('<p><a href="t3://page?uid=2">Team</a></p>');

        self::assertStringNotContainsString('t3://', $rendered);
        self::assertStringContainsString('/module/web/layout', $rendered);
        self::assertStringContainsString('id=2', $rendered);
    }

    #[Test]
    public function opensAnExternalLinkInANewTab(): void
    {
        $rendered = CommentLinkUtility::resolveLinks('<a href="https://typo3.org">TYPO3</a>');

        self::assertStringContainsString('href="https://typo3.org"', $rendered);
        self::assertStringContainsString('target="_blank"', $rendered);
        self::assertStringContainsString('rel="noopener noreferrer"', $rendered);
    }

    #[Test]
    public function dropsTheHrefOfAnUnsafeScheme(): void
    {
        $rendered = CommentLinkUtility::resolveLinks('<a href="javascript:alert(1)">x</a>');

        self::assertStringNotContainsString('javascript:', $rendered);
        self::assertStringContainsString('>x</a>', $rendered);
    }

    #[Test]
    public function leavesContentWithoutLinksUntouched(): void
    {
        self::assertSame('<p>Plain</p>', CommentLinkUtility::resolveLinks('<p>Plain</p>'));
    }

    private function initBackendRequest(): void
    {
        $request = (new ServerRequest('https://example.com/typo3/', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $GLOBALS['TYPO3_REQUEST'] = $request->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromServerParams($request->getServerParams()),
        );
    }
}
