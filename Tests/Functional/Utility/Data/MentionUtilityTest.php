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
 * Covers {@see MentionUtility::renderContentWithMentions()}, the DB-backed half of the mention
 * marker contract: it must re-resolve the mentioned user's *current* display name - proving the
 * whole reason markers store a stable UID rather than baking in a name that can go stale - and
 * turn the stored anchor into the focusable profile-card trigger.
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
    public function rendersAMentionMarkerWithTheCurrentDisplayName(): void
    {
        $content = '<p>Hey <a class="ctp-mention" data-mention-uid="2">@stale-name</a>!</p>';

        $rendered = MentionUtility::renderContentWithMentions($content);

        self::assertStringContainsString('data-mention-uid="2"', $rendered);
        // "Editor User (editor)" is the *current* be_users(2) display name - not "stale-name",
        // which is what the marker text happened to say when the mention was authored.
        self::assertStringContainsString('@Editor User (editor)', $rendered);
        self::assertStringNotContainsString('stale-name', $rendered);
    }

    #[Test]
    public function turnsTheStoredAnchorIntoAFocusableButtonWithoutALink(): void
    {
        $content = '<a class="ctp-mention" data-mention-uid="2">@editor</a>';

        $rendered = MentionUtility::renderContentWithMentions($content);

        // be_users is adminOnly, so the record link the marker used to carry was a dead end for
        // most readers - the trigger is a button the profile card hangs off instead.
        self::assertStringContainsString('<button', $rendered);
        self::assertStringContainsString('type="button"', $rendered);
        self::assertStringContainsString('class="ctp-mention"', $rendered);
        self::assertStringNotContainsString('href=', $rendered);
    }

    #[Test]
    public function marksTheCurrentUsersOwnMentionSoItStandsOut(): void
    {
        $ownUid = (int) $GLOBALS['BE_USER']->user['uid'];

        $rendered = MentionUtility::renderContentWithMentions(
            '<a class="ctp-mention" data-mention-uid="'.$ownUid.'">@me</a>',
        );

        self::assertStringContainsString('ctp-mention--self', $rendered);
        // Not colour alone: the hint has to reach a screen reader too.
        self::assertStringContainsString('visually-hidden', $rendered);
    }

    #[Test]
    public function doesNotMarkSomeoneElsesMentionAsOwn(): void
    {
        $otherUid = (int) $GLOBALS['BE_USER']->user['uid'] + 1;

        $rendered = MentionUtility::renderContentWithMentions(
            '<a class="ctp-mention" data-mention-uid="'.$otherUid.'">@somebody</a>',
        );

        self::assertStringNotContainsString('ctp-mention--self', $rendered);
    }

    #[Test]
    public function rendersTheOwnMentionWithoutALanguageService(): void
    {
        $ownUid = (int) $GLOBALS['BE_USER']->user['uid'];
        // The public API renders comments from CLI and scheduler contexts too, where there is no
        // $GLOBALS['LANG'] - losing the hint is fine, taking the comment down with it is not.
        unset($GLOBALS['LANG']);

        $rendered = MentionUtility::renderContentWithMentions(
            '<a class="ctp-mention" data-mention-uid="'.$ownUid.'">@me</a>',
        );

        self::assertStringContainsString('ctp-mention--self', $rendered);
        self::assertStringNotContainsString('visually-hidden', $rendered);
    }

    #[Test]
    public function leavesAMarkerForAnUnknownUserUntouched(): void
    {
        $content = '<a class="ctp-mention" data-mention-uid="99999">@ghost</a>';

        $rendered = MentionUtility::renderContentWithMentions($content);

        self::assertStringContainsString('@ghost', $rendered);
        self::assertStringNotContainsString('<button', $rendered);
    }

    #[Test]
    public function leavesContentWithoutMarkersUnchanged(): void
    {
        $content = '<p>No mentions here.</p>';

        self::assertSame($content, MentionUtility::renderContentWithMentions($content));
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
