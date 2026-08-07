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

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Http\{ServerRequest, StreamFactory};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\ApiController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Event\StatusChangeEvent;
use Xima\XimaTypo3ContentPlanner\Service\{RecordSummaryService, ResultMessageService, StatusChangeApiService, StatusSelectionApiService};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

use function dirname;

/**
 * StatusChangeApiTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusChangeApiTest extends AbstractFunctionalTestCase
{
    private ApiController $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/../Service/Fixtures/summary_pages.csv');
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
    public function theRouteIsGatedByTheFlag(): void
    {
        self::assertArrayNotHasKey('ximatypo3contentplanner_api_status', $this->loadAjaxRoutes());

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_FRONTEND_API] = 1;
        $routes = $this->loadAjaxRoutes();

        self::assertSame('/content-planner/api/status', $routes['ximatypo3contentplanner_api_status']['path']);
        self::assertSame(['POST'], $routes['ximatypo3contentplanner_api_status']['methods']);
    }

    #[Test]
    public function writesTheStatusAndReportsTheFreshSummary(): void
    {
        // Fixture page 3 has no status.
        $response = $this->subject->statusAction($this->bodyRequest(['table' => 'pages', 'uid' => 3, 'status' => 2]));

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);

        self::assertTrue($payload['success']);
        self::assertSame(['success', 'title', 'message', 'severity', 'record'], array_keys($payload));
        self::assertSame(2, $payload['record']['status']['uid']);
        self::assertSame(2, $this->statusInDatabase(3), 'the DataHandler did not persist the status');
    }

    #[Test]
    public function dispatchesTheStatusChangeEvent(): void
    {
        $spy = new class {
            /** @var StatusChangeEvent[] */
            public array $events = [];

            public function __invoke(StatusChangeEvent $event): void
            {
                $this->events[] = $event;
            }
        };

        $this->getContainer()->set('cp.test.status-change-spy', $spy);
        $this->get(ListenerProvider::class)->addListener(
            StatusChangeEvent::class,
            'cp.test.status-change-spy',
            '__invoke',
        );

        $this->subject->statusAction($this->bodyRequest(['table' => 'pages', 'uid' => 3, 'status' => 2]));

        // StatusChangeManager dispatches this from inside the DataHandler pipeline. Writing
        // the field directly would persist the status but never reach here.
        self::assertCount(1, $spy->events);
        self::assertSame('pages', $spy->events[0]->getTable());
        self::assertSame(3, $spy->events[0]->getUid());
        self::assertSame(2, $spy->events[0]->getNewStatus()?->getUid());
        self::assertNull($spy->events[0]->getPreviousStatus());
    }

    #[Test]
    public function unsetsTheStatusAndUsesTheResetWording(): void
    {
        // Fixture page 1 carries status 1.
        $response = $this->subject->statusAction($this->bodyRequest(['table' => 'pages', 'uid' => 1, 'status' => null]));

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        self::assertTrue($payload['success']);
        self::assertNull($payload['record']['status']);
        self::assertNull($this->statusInDatabase(1));
        // status.reset is a NOTICE, status.changed an OK — proves the wording branch.
        self::assertSame(-2, $payload['severity']);
    }

    #[Test]
    public function autoAssignmentFromTheBackendPipelineApplies(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_AUTO_ASSIGN] = 1;

        $this->subject->statusAction($this->bodyRequest(['table' => 'pages', 'uid' => 3, 'status' => 2]));

        $assignee = $this->getConnectionPool()->getConnectionForTable('pages')
            ->select([Configuration::FIELD_ASSIGNEE], 'pages', ['uid' => 3])
            ->fetchOne();

        // Only the DataHandler path performs auto assignment, so a non-empty assignee is
        // evidence the endpoint really went through it.
        self::assertSame(1, (int) $assignee);

        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_AUTO_ASSIGN]);
    }

    #[Test]
    public function reportsAStrippedChangeAsForbidden(): void
    {
        // StatusChangeManager silently unsets the fields when a permission check fails, so
        // the endpoint detects it by re-reading rather than by re-implementing the checks.
        // A pipeline that writes nothing reproduces exactly that shape.
        $service = new class($this->get(RecordRepository::class)) extends StatusChangeApiService {
            protected function processDatamap(string $table, int $uid, ?int $requestedStatus): void
            {
                // deliberately writes nothing, as a stripped change does
            }
        };

        $controller = new ApiController(
            $this->get(RecordSummaryService::class),
            $this->get(StatusSelectionApiService::class),
            $service,
            $this->get(ResultMessageService::class),
        );

        $response = $controller->statusAction($this->bodyRequest(['table' => 'pages', 'uid' => 3, 'status' => 2]));

        self::assertSame(403, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        self::assertFalse($payload['success']);
        self::assertSame(2, $payload['severity'], 'a stripped change must carry the ERROR severity');
        self::assertArrayNotHasKey('record', $payload, 'no summary on a change that did not happen');
        self::assertNull($this->statusInDatabase(3));
    }

    #[Test]
    public function rejectsAnIncompletePayload(): void
    {
        self::assertSame(400, $this->subject->statusAction($this->bodyRequest(['uid' => 3, 'status' => 2]))->getStatusCode());
        self::assertSame(400, $this->subject->statusAction($this->bodyRequest(['table' => 'pages', 'status' => 2]))->getStatusCode());
        // A missing "status" key is not the same as an explicit null.
        self::assertSame(400, $this->subject->statusAction($this->bodyRequest(['table' => 'pages', 'uid' => 3]))->getStatusCode());
    }

    /**
     * @param mixed $status the value sent as "status"
     */
    #[Test]
    #[DataProvider('unusableStatusValues')]
    public function rejectsAStatusThatIsNotAPositiveIntegerWithoutWriting(mixed $status): void
    {
        $before = $this->statusInDatabase(3);

        $response = $this->subject->statusAction($this->bodyRequest(['table' => 'pages', 'uid' => 3, 'status' => $status]));

        self::assertSame(400, $response->getStatusCode());
        // The status code alone would not prove much: casting turns "abc" into 0, which the
        // DataHandler applies as a reset before the endpoint reports a failure. What matters
        // is that no write happened at all.
        self::assertSame($before, $this->statusInDatabase(3), 'a rejected payload must not change the record');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableStatusValues(): array
    {
        return [
            // Cast to 0, i.e. an implicit reset the caller never asked for.
            'zero' => [0],
            'non-numeric string' => ['abc'],
            'false' => [false],
            // Cast to a valid status uid, i.e. a silent change from a malformed payload.
            'numeric prefix' => ['2abc'],
            'true' => [true],
            'negative' => [-1],
            'float' => [2.5],
            'array' => [[2]],
        ];
    }

    #[Test]
    public function stillAcceptsADigitStringSoFormEncodedBodiesKeepWorking(): void
    {
        // readBody() also serves form-encoded bodies, where every value is a string.
        $response = $this->subject->statusAction($this->bodyRequest(['table' => 'pages', 'uid' => '3', 'status' => '2']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $this->statusInDatabase(3));
    }

    #[Test]
    public function rejectsAUidThatIsNotAPositiveIntegerWithoutWriting(): void
    {
        // Same defect class as the status above: a cast would target record 3 here.
        $before = $this->statusInDatabase(3);

        $response = $this->subject->statusAction($this->bodyRequest(['table' => 'pages', 'uid' => '3abc', 'status' => 2]));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame($before, $this->statusInDatabase(3));
    }

    #[Test]
    public function returnsNotFoundForAnUnknownRecordAndAnUnregisteredTable(): void
    {
        self::assertSame(404, $this->subject->statusAction($this->bodyRequest(['table' => 'pages', 'uid' => 9999, 'status' => 2]))->getStatusCode());
        self::assertSame(404, $this->subject->statusAction($this->bodyRequest(['table' => 'be_users', 'uid' => 1, 'status' => 2]))->getStatusCode());
    }

    private function statusInDatabase(int $uid): ?int
    {
        $value = $this->getConnectionPool()->getConnectionForTable('pages')
            ->select([Configuration::FIELD_STATUS], 'pages', ['uid' => $uid])
            ->fetchOne();

        return null === $value || 0 === (int) $value ? null : (int) $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function bodyRequest(array $body): ServerRequest
    {
        return (new ServerRequest('https://example.com/typo3/ajax/content-planner/api/status', 'POST'))
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
