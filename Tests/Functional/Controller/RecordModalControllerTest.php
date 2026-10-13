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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3ContentPlanner\Controller\RecordModalController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\WatcherPresentationService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * RecordModalControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordModalControllerTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
    }

    #[Test]
    public function contextActionReturnsBadRequestWhenParametersMissing(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->contextAction($this->createRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function contextActionDeniesUserWithoutContentPlannerAccess(): void
    {
        $this->loginBackendUser(2);

        $response = $this->createController()->contextAction($this->createRequest(['table' => 'pages', 'uid' => 1]));

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function contextActionReturnsNotFoundForUnknownRecord(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->contextAction($this->createRequest(['table' => 'pages', 'uid' => 9999]));

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function contextActionDescribesTheRecordItsStatusAndTheStatusesTheUserMaySet(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();

        $response = $this->createController()->contextAction($this->createRequest(['table' => 'pages', 'uid' => 1]));

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Home', $payload['title']);
        self::assertSame('pages:1', $payload['reference']);
        self::assertNotSame('', $payload['typeLabel']);
        self::assertSame(2, $payload['status']['uid']);
        self::assertTrue($payload['canChangeStatus']);
        self::assertNotEmpty($payload['statuses']);
        self::assertSame([2], array_column(array_filter($payload['statuses'], static fn (array $status): bool => $status['current']), 'uid'));
        self::assertIsInt($payload['counts']['comments']);
        self::assertNotSame('', $payload['counts']['assignee']);
        self::assertIsInt($payload['counts']['watchers']);
    }

    #[Test]
    public function contextActionTellsWhetherAWorkspaceIsOpen(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();

        self::assertFalse(json_decode((string) $this->createController()->contextAction($this->createRequest(['table' => 'pages', 'uid' => 1]))->getBody(), true)['workspace']);

        $GLOBALS['BE_USER']->workspace = 3;

        self::assertTrue(json_decode((string) $this->createController()->contextAction($this->createRequest(['table' => 'pages', 'uid' => 1]))->getBody(), true)['workspace']);
    }

    #[Test]
    public function contextActionNamesTheStylesheetOfTheModalFrame(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();

        $payload = json_decode((string) $this->createController()->contextAction($this->createRequest(['table' => 'pages', 'uid' => 1]))->getBody(), true);

        self::assertCount(1, $payload['stylesheets']);
        self::assertMatchesRegularExpression('#/Css/RecordModal\.css(\?\d+)?$#', $payload['stylesheets'][0]);
    }

    #[Test]
    public function statusActionChangesTheStatusThroughTheDataHandler(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();

        $response = $this->createController()->statusAction($this->createRequest([], ['table' => 'pages', 'uid' => 1, 'status' => 1]));

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $payload['status']['uid']);
        self::assertSame(1, $this->readStatus(1));
    }

    #[Test]
    public function statusActionRemovesTheStatusWhenZeroIsGiven(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();

        $response = $this->createController()->statusAction($this->createRequest([], ['table' => 'pages', 'uid' => 1, 'status' => 0]));

        self::assertSame(200, $response->getStatusCode());
        self::assertNull(json_decode((string) $response->getBody(), true)['status']);
        self::assertSame(0, $this->readStatus(1));
    }

    #[Test]
    public function statusActionRejectsAStatusThatDoesNotExist(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();

        $response = $this->createController()->statusAction($this->createRequest([], ['table' => 'pages', 'uid' => 1, 'status' => 9999]));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(2, $this->readStatus(1));
    }

    #[Test]
    public function statusActionDeniesUserWithoutContentPlannerAccess(): void
    {
        $this->loginBackendUser(2);

        $response = $this->createController()->statusAction($this->createRequest([], ['table' => 'pages', 'uid' => 1, 'status' => 1]));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(2, $this->readStatus(1));
    }

    private function readStatus(int $uid): int
    {
        return (int) $this->get(ConnectionPool::class)->getConnectionForTable('pages')
            ->select(['tx_ximatypo3contentplanner_status'], 'pages', ['uid' => $uid])->fetchOne();
    }

    private function createController(): RecordModalController
    {
        return new RecordModalController(
            $this->get(RecordRepository::class),
            $this->get(StatusRepository::class),
            $this->get(CommentRepository::class),
            $this->get(WatcherPresentationService::class),
        );
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $body
     */
    private function createRequest(array $queryParams, array $body = []): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);
        $request->method('getParsedBody')->willReturn($body);

        return $request;
    }
}
