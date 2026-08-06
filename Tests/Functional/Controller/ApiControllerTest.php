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
use TYPO3\CMS\Core\Http\{ServerRequest, StreamFactory};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\ApiController;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

use function dirname;

/**
 * ApiControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ApiControllerTest extends AbstractFunctionalTestCase
{
    private ApiController $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/../Service/Fixtures/summary_pages.csv');
        $this->importCSVDataSet(__DIR__.'/../Service/Fixtures/summary_comments.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest();
        $this->subject = $this->get(ApiController::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_FRONTEND_API]);
        parent::tearDown();
    }

    #[Test]
    public function theRouteIsNotRegisteredWhileTheFlagIsOff(): void
    {
        self::assertArrayNotHasKey('ximatypo3contentplanner_api_summary', $this->loadAjaxRoutes());
    }

    #[Test]
    public function theRouteIsRegisteredAsAPostRouteOnceTheFlagIsOn(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_FRONTEND_API] = 1;

        $routes = $this->loadAjaxRoutes();

        self::assertArrayHasKey('ximatypo3contentplanner_api_summary', $routes);
        self::assertSame('/content-planner/api/summary', $routes['ximatypo3contentplanner_api_summary']['path']);
        self::assertSame(['POST'], $routes['ximatypo3contentplanner_api_summary']['methods']);
    }

    #[Test]
    public function returnsASummaryPerRequestedRecord(): void
    {
        $response = $this->subject->summaryAction($this->requestWithBody([
            'items' => [
                ['table' => 'pages', 'uid' => 1],
                ['table' => 'pages', 'uid' => 3],
            ],
        ]));

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        self::assertCount(2, $payload['items']);
        self::assertSame(['table', 'uid', 'status', 'assignee', 'comments', 'capabilities'], array_keys($payload['items'][0]));
    }

    #[Test]
    public function theResponseCarriesNoHtml(): void
    {
        $response = $this->subject->summaryAction($this->requestWithBody([
            'items' => [['table' => 'pages', 'uid' => 1]],
        ]));

        self::assertStringNotContainsString('<', (string) $response->getBody());
    }

    #[Test]
    public function omitsForbiddenRecordsInsteadOfFailingTheWholeBatch(): void
    {
        $response = $this->subject->summaryAction($this->requestWithBody([
            'items' => [
                ['table' => 'pages', 'uid' => 1],
                ['table' => 'be_users', 'uid' => 1],
                ['table' => 'pages', 'uid' => 9999],
            ],
        ]));

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        self::assertCount(1, $payload['items']);
        self::assertSame(1, $payload['items'][0]['uid']);
    }

    #[Test]
    public function rejectsAPayloadWithoutAnItemsArray(): void
    {
        self::assertSame(400, $this->subject->summaryAction($this->requestWithBody(['nope' => true]))->getStatusCode());
    }

    #[Test]
    public function rejectsAnUnparseableBody(): void
    {
        $request = (new ServerRequest('https://example.com/typo3/ajax/content-planner/api/summary', 'POST'))
            ->withBody($this->get(StreamFactory::class)->createStream('not json'));

        self::assertSame(400, $this->subject->summaryAction($request)->getStatusCode());
    }

    #[Test]
    public function rejectsMoreItemsThanTheBatchLimit(): void
    {
        $items = [];
        for ($uid = 1; $uid <= 501; ++$uid) {
            $items[] = ['table' => 'pages', 'uid' => $uid];
        }

        self::assertSame(400, $this->subject->summaryAction($this->requestWithBody(['items' => $items]))->getStatusCode());
    }

    #[Test]
    public function skipsMalformedItemEntries(): void
    {
        $response = $this->subject->summaryAction($this->requestWithBody([
            'items' => [
                'not-an-array',
                ['uid' => 1],
                ['table' => 'pages'],
                ['table' => 'pages', 'uid' => 1],
            ],
        ]));

        $payload = json_decode((string) $response->getBody(), true);
        self::assertCount(1, $payload['items']);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requestWithBody(array $body): ServerRequest
    {
        return (new ServerRequest('https://example.com/typo3/ajax/content-planner/api/summary', 'POST'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->get(StreamFactory::class)->createStream((string) json_encode($body)));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadAjaxRoutes(): array
    {
        return require dirname(__DIR__, 3).'/Configuration/Backend/AjaxRoutes.php';
    }
}
