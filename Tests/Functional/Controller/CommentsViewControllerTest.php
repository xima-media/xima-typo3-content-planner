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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\{NormalizedParams, ServerRequest};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\{CommentsViewController, RecordController};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

use function dirname;
use function explode;
use function preg_match_all;
use function rawurlencode;
use function str_contains;
use function urldecode;

/**
 * CommentsViewControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentsViewControllerTest extends AbstractFunctionalTestCase
{
    /**
     * The route is registered while the container is built, and the view builds its own
     * filter URLs through the UriBuilder — so the flag has to be on before the instance
     * boots, not merely set inside a test method.
     */
    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            Configuration::EXT_KEY => [
                Configuration::FEATURE_EMBEDDABLE_COMMENTS_VIEW => 1,
            ],
        ],
    ];

    private CommentsViewController $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/../Service/Fixtures/summary_pages.csv');
        $this->importCSVDataSet(__DIR__.'/../Service/Fixtures/summary_comments.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('ximatypo3contentplanner_comments_view');
        $this->subject = $this->get(CommentsViewController::class);
    }

    #[Test]
    public function theRouteIsNotRegisteredWhileTheFlagIsOff(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_EMBEDDABLE_COMMENTS_VIEW]);

        self::assertArrayNotHasKey('ximatypo3contentplanner_comments_view', $this->loadRoutes());
    }

    #[Test]
    public function theRouteIsRegisteredOnceTheFlagIsOn(): void
    {
        $routes = $this->loadRoutes();

        self::assertArrayHasKey('ximatypo3contentplanner_comments_view', $routes);
        self::assertSame('/content-planner/comments/view', $routes['ximatypo3contentplanner_comments_view']['path']);
        // Not a public route: the view must stay behind the backend session, unlike the
        // share route next to it.
        self::assertArrayNotHasKey('access', $routes['ximatypo3contentplanner_comments_view']);
    }

    #[Test]
    public function requiresBothTableAndUid(): void
    {
        self::assertSame(400, $this->subject->indexAction($this->viewRequest(['uid' => '1']))->getStatusCode());
        self::assertSame(400, $this->subject->indexAction($this->viewRequest(['table' => 'pages']))->getStatusCode());
        self::assertSame(400, $this->subject->indexAction($this->viewRequest(['table' => 'pages', 'uid' => '0']))->getStatusCode());
    }

    #[Test]
    public function refusesRecordsTheUserMayNotRead(): void
    {
        // Both outcomes match RecordController::commentsAction(): a missing row and a table
        // the content planner does not track are equally "not found" here.
        self::assertSame(404, $this->subject->indexAction($this->viewRequest(['table' => 'pages', 'uid' => '9999']))->getStatusCode());
        self::assertSame(404, $this->subject->indexAction($this->viewRequest(['table' => 'be_users', 'uid' => '1']))->getStatusCode());
    }

    #[Test]
    public function refusalsAreStillDocuments(): void
    {
        // The response lands in somebody else's iframe, so even an error has to be
        // renderable there rather than a bare JSON fragment.
        $body = (string) $this->subject->indexAction($this->viewRequest(['table' => 'pages']))->getBody();

        self::assertStringStartsWith('<!DOCTYPE html>', $body);
        self::assertStringContainsString('</html>', $body);
    }

    #[Test]
    public function rendersACompleteDocument(): void
    {
        $body = $this->render();

        self::assertStringStartsWith('<!DOCTYPE html>', $body);
        self::assertStringContainsString('<head>', $body);
        self::assertStringContainsString('</body>', $body);
        self::assertStringContainsString('</html>', $body);
        self::assertStringContainsString('<html lang=', $body, 'htmlTagAttributes() must supply the language attributes');
    }

    #[Test]
    public function bringsItsOwnStylesheetsSoTheParentPageNeedsNone(): void
    {
        $body = $this->render();

        // backend.css carries the Bootstrap layer the comment markup is written against.
        self::assertStringContainsString('backend.css', $body);
        self::assertStringContainsString('Comments.css', $body);
        self::assertStringContainsString('CommentsView.css', $body);
    }

    #[Test]
    public function shipsNoJavaScriptAtAll(): void
    {
        // Two reasons, and the second is why this view does not go through PageRenderer:
        // the consumer's `top` window is a foreign origin, so nothing here may depend on the
        // backend's JS modules or `top.TYPO3`; and PageRenderer inlines TYPO3.settings,
        // which carries a CSRF token for every registered backend route.
        $body = $this->render();

        self::assertStringNotContainsString('<script', $body);
        self::assertStringNotContainsString('ajaxUrls', $body);
        self::assertStringNotContainsString('token=', $this->headOf($body), 'no route token may leak into the document head');
    }

    #[Test]
    public function followsTheBackendUsersColourScheme(): void
    {
        // The <html> attributes are assembled here rather than by the PageRenderer, so the
        // preference backend.css keys its dark variables off has to be carried over.
        $GLOBALS['BE_USER']->uc['colorScheme'] = 'dark';

        self::assertStringContainsString('data-color-scheme="dark"', $this->render());
    }

    #[Test]
    public function leavesTheColourSchemeToTheSystemWhenTheUserDidNotPickOne(): void
    {
        $GLOBALS['BE_USER']->uc['colorScheme'] = 'auto';

        // Same as core: no attribute means "follow prefers-color-scheme".
        self::assertStringNotContainsString('data-color-scheme', $this->render());
    }

    #[Test]
    public function rendersTheCommentThreadIncludingReplies(): void
    {
        $body = $this->render();

        self::assertStringContainsString('Open comment', $body);
        self::assertStringContainsString('Reply', $body);
        self::assertStringNotContainsString('Deleted comment', $body);
        // Resolved comments stay hidden until asked for, exactly like the backend modal.
        self::assertStringNotContainsString('Resolved comment', $body);
    }

    #[Test]
    public function showsResolvedCommentsOnRequest(): void
    {
        $body = $this->render(['showResolvedComments' => '1']);

        self::assertStringContainsString('Resolved comment', $body);
    }

    #[Test]
    public function repliesAreVisibleWithoutABootstrapCollapse(): void
    {
        $body = $this->render();

        self::assertStringContainsString('Reply', $body, 'the reply must be rendered, not merely collapsed away');
        self::assertStringNotContainsString('data-bs-toggle', $body, 'no control may depend on Bootstrap JS');
        self::assertStringNotContainsString('class="collapse', $body);
    }

    #[Test]
    public function actionsAreLinksThatReturnToTheView(): void
    {
        $body = $this->render();

        self::assertStringContainsString('record/edit', $body);
        // returnUrl is derived from the current request and its encoding differs between
        // TYPO3 versions, so accept either form rather than pinning this environment's.
        $requestUri = $this->currentRequestUri();
        self::assertTrue(
            str_contains($body, 'returnUrl='.$requestUri) || str_contains($body, 'returnUrl='.rawurlencode($requestUri)),
            'the action links must return to the embeddable view, got: '.$requestUri,
        );
        self::assertStringNotContainsString('data-reply-comment-uri', $body, 'the embedded view must not rely on the modal JS');
        self::assertStringNotContainsString('data-edit-comment-uri', $body);
    }

    #[Test]
    public function theReturnUrlIsRecognisableAsThisViewsOwn(): void
    {
        // ModifyButtonBarEventListener tells this flow apart from the backend modal by
        // matching the returnUrl against Configuration::ROUTE_PATH_COMMENTS_VIEW, and it has
        // to: without that, the comment form keeps only a save button and a user who
        // followed one of these links has no way back here. Asserting the signal keeps the
        // two sides from drifting apart.
        // The shared helper registers /typo3/index.php as the request URI, and returnUrl is
        // derived from it — so without a request that actually is this route, the assertion
        // below would only be testing the test harness.
        $this->registerRequestOnTheCommentsViewRoute();

        preg_match_all('/returnUrl=([^"&]+)/', $this->render(), $matches);

        self::assertNotEmpty($matches[1], 'the action links must carry a returnUrl');
        foreach ($matches[1] as $returnUrl) {
            self::assertStringContainsString(
                Configuration::ROUTE_PATH_COMMENTS_VIEW,
                urldecode($returnUrl),
                'every returnUrl must point back at the comments view',
            );
        }
    }

    #[Test]
    public function linksLeavingTheThreadOpenOutsideTheFrame(): void
    {
        $body = $this->render();

        // Share and record links replace a whole backend page, which must not happen inside
        // the consumer's frame.
        self::assertStringContainsString('target="_top"', $body);
    }

    #[Test]
    public function omitsDeleteBecauseItCannotBeConfirmedWithoutJavaScript(): void
    {
        self::assertStringNotContainsString('cmd%5Btx_ximatypo3contentplanner_comment%5D', $this->render());
    }

    #[Test]
    public function theBackendModalKeepsItsJavaScriptDrivenMarkup(): void
    {
        // Regression guard for the shared Comment partial: the embedded branch must not
        // reach the modal path.
        $this->setUpBackendRequest('web_layout');
        $request = (new ServerRequest('https://example.com/typo3/ajax/content-planner/comments', 'GET'))
            ->withQueryParams(['table' => 'pages', 'uid' => '1']);

        $payload = json_decode((string) $this->get(RecordController::class)->commentsAction($request)->getBody(), true);

        self::assertStringContainsString('data-bs-toggle="dropdown"', $payload['result']);
        self::assertStringContainsString('data-reply-comment-uri', $payload['result']);
        self::assertStringContainsString('data-delete-comment-uri', $payload['result']);
    }

    /**
     * @param array<string, string> $queryParams
     */
    private function render(array $queryParams = []): string
    {
        $response = $this->subject->indexAction(
            $this->viewRequest(['table' => 'pages', 'uid' => '1', ...$queryParams]),
        );
        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    /**
     * @param array<string, string> $queryParams
     */
    private function viewRequest(array $queryParams): ServerRequest
    {
        return (new ServerRequest('https://example.com/typo3/content-planner/comments/view', 'GET'))
            ->withQueryParams($queryParams);
    }

    /**
     * Mirrors what the real route looks like: a backend request whose own URI is the
     * comments view, which is what makes returnUrl point back here in production.
     */
    private function registerRequestOnTheCommentsViewRoute(): void
    {
        $requestUri = '/typo3'.Configuration::ROUTE_PATH_COMMENTS_VIEW.'?table=pages&uid=1';

        $GLOBALS['TYPO3_REQUEST'] = $GLOBALS['TYPO3_REQUEST']->withAttribute(
            'normalizedParams',
            new NormalizedParams(
                ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => $requestUri],
                [],
                '',
                '',
            ),
        );
    }

    private function headOf(string $body): string
    {
        return (string) (explode('</head>', $body, 2)[0] ?? '');
    }

    private function currentRequestUri(): string
    {
        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $GLOBALS['TYPO3_REQUEST']->getAttribute('normalizedParams');

        return $normalizedParams->getRequestUri();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadRoutes(): array
    {
        return require dirname(__DIR__, 3).'/Configuration/Backend/Routes.php';
    }
}
