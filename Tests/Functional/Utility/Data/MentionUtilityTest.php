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
use Xima\XimaTypo3ContentPlanner\Utility\Data\MentionUtility;

/**
 * MentionUtilityTest.
 *
 * Covers {@see MentionUtility::renderContentWithMentionLinks()}, the DB-backed half of the
 * mention marker contract: it must re-resolve the mentioned user's *current* display name -
 * proving the whole reason markers store a stable UID rather than baking in a name that can go
 * stale.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class MentionUtilityTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->initBackendRequest();
    }

    #[Test]
    public function rendersAMentionMarkerAsALinkWithTheCurrentDisplayName(): void
    {
        $content = '<p>Hey <a class="ctp-mention" data-mention-uid="2">@stale-name</a>!</p>';

        $rendered = MentionUtility::renderContentWithMentionLinks($content);

        self::assertStringContainsString('class="ctp-mention"', $rendered);
        self::assertStringContainsString('data-mention-uid="2"', $rendered);
        // "Editor User (editor)" is the *current* be_users(2) display name - not "stale-name",
        // which is what the marker text happened to say when the mention was authored.
        self::assertStringContainsString('@Editor User (editor)', $rendered);
        self::assertStringNotContainsString('stale-name', $rendered);
    }

    #[Test]
    public function addsAnHrefPointingAtTheMentionedUsersRecord(): void
    {
        $content = '<a class="ctp-mention" data-mention-uid="2">@editor</a>';

        $rendered = MentionUtility::renderContentWithMentionLinks($content);

        self::assertMatchesRegularExpression('/href="[^"]+"/', $rendered);
    }

    #[Test]
    public function leavesAMarkerForAnUnknownUserUntouched(): void
    {
        $content = '<a class="ctp-mention" data-mention-uid="99999">@ghost</a>';

        $rendered = MentionUtility::renderContentWithMentionLinks($content);

        self::assertStringContainsString('@ghost', $rendered);
        self::assertStringNotContainsString('href=', $rendered);
    }

    #[Test]
    public function leavesContentWithoutMarkersUnchanged(): void
    {
        $content = '<p>No mentions here.</p>';

        self::assertSame($content, MentionUtility::renderContentWithMentionLinks($content));
    }

    private function initBackendRequest(): void
    {
        $request = (new ServerRequest('https://example.com/typo3/', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $request = $request->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromServerParams($request->getServerParams()),
        );
        $GLOBALS['TYPO3_REQUEST'] = $request;
    }
}
