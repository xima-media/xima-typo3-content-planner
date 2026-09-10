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
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\WatcherController;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{WatchMode, WatchSource};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, WatcherRepository};
use Xima\XimaTypo3ContentPlanner\Service\{WatcherPresentationService, WatcherService};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * WatcherControllerTest.
 *
 * Covers the watch/unwatch toggle AJAX round trip (issue #303): the toggle direction for every
 * prior {@see WatchMode}, permission gating matching the rest of the extension's AJAX endpoints
 * ({@see MentionControllerTest} covers the same access-control shape for a sibling endpoint), and
 * that the response body's state always matches what actually landed in the database.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WatcherControllerTest extends AbstractFunctionalTestCase
{
    private WatcherRepository $watcherRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->watcherRepository = $this->get(WatcherRepository::class);
    }

    #[Test]
    public function toggleActionReturnsBadRequestWhenParametersMissing(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->toggleAction($this->createRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function toggleActionDeniesUserWithoutContentPlannerAccess(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission.
        $this->loginBackendUser(2);

        $response = $this->createController()->toggleAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function toggleActionRejectsAnUnwatchableTable(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->toggleAction(
            $this->createRequest(['table' => Configuration::TABLE_FOLDER, 'uid' => 1]),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function toggleActionReturnsNotFoundForUnknownRecord(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->toggleAction(
            $this->createRequest(['table' => 'pages', 'uid' => 99999]),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function toggleActionReturnsForbiddenWhenRecordAccessIsDenied(): void
    {
        $this->loginBackendUser(1);

        // Page uid 4 has a pid pointing at a non-existent parent page, so
        // BackendUtility::readPageAccess() cannot resolve it - even for an admin.
        $response = $this->createController()->toggleAction(
            $this->createRequest(['table' => 'pages', 'uid' => 4]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function toggleActionStartsManualWatchWhenNotWatchingBefore(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();

        $response = $this->createController()->toggleAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('manual_watch', $payload['mode']);
        self::assertTrue($payload['watching']);
        self::assertSame(WatchMode::ManualWatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function toggleActionMutesAnAutoWatchedRecord(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::Auto, WatchSource::Assignment);

        $response = $this->createController()->toggleAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame('manual_unwatch', $payload['mode']);
        self::assertFalse($payload['watching']);
        self::assertSame(WatchMode::ManualUnwatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function toggleActionMutesAManuallyWatchedRecord(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::ManualWatch, WatchSource::Manual);

        $response = $this->createController()->toggleAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame('manual_unwatch', $payload['mode']);
        self::assertFalse($payload['watching']);
    }

    #[Test]
    public function toggleActionReactivatesAMutedRecordAsManualWatch(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::ManualUnwatch, WatchSource::Manual);

        $response = $this->createController()->toggleAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame('manual_watch', $payload['mode']);
        self::assertTrue($payload['watching']);
        self::assertSame(WatchMode::ManualWatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function toggleActionResponseCountAndNamesMatchDatabaseState(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_mention.csv');
        // uid 10 ("member") is content-planner-permitted, uid 11 ("nogroup") is not.
        $this->watcherRepository->upsert('pages', 1, 10, WatchMode::ManualWatch, WatchSource::Manual);
        $this->watcherRepository->upsert('pages', 1, 11, WatchMode::ManualWatch, WatchSource::Manual);

        $response = $this->createController()->toggleAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        // Backend user 1 (admin, this request's actor) just started watching too.
        self::assertSame(3, $payload['count']);
        self::assertContains('Member User (member)', $payload['watcherNames']);
        self::assertNotContains('No Group User (nogroup)', $payload['watcherNames']);
    }

    private function createController(): WatcherController
    {
        return new WatcherController(
            $this->get(RecordRepository::class),
            $this->get(WatcherService::class),
            $this->get(WatcherPresentationService::class),
        );
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function createRequest(array $queryParams): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);

        return $request;
    }
}
