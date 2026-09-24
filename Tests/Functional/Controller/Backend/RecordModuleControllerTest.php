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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Controller\Backend;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\{Route, UriBuilder};
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use Xima\XimaTypo3ContentPlanner\Controller\Backend\RecordModuleController;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * RecordModuleControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordModuleControllerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function indexActionComputesOffsetFromThePageQueryParameter(): void
    {
        $this->loginBackendUser(1);

        // CP-32 (#404): page 3 at the default page size of 20 must skip the first 40 visible
        // records - this is the whole point of the offset parameter #404 adds.
        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->expects(self::once())
            ->method('findAllByFilter')
            ->with(null, null, null, null, false, RecordRepository::DEFAULT_PAGE_SIZE, false, null, 40)
            ->willReturn(new PaginatedResult([], false));

        $response = $this->createController($recordRepository)->indexAction($this->createRequest(['page' => '3']));

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function indexActionResolvesWatchedRecordsForTheCurrentUserWhenWatchedFilterIsSet(): void
    {
        $this->loginBackendUser(1);

        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->expects(self::once())
            ->method('getWatchedRecords')
            ->with(1)
            ->willReturn(['pages' => [5]]);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->expects(self::once())
            ->method('findAllByFilter')
            ->with(null, null, null, null, false, RecordRepository::DEFAULT_PAGE_SIZE, false, ['pages' => [5]], 0)
            ->willReturn(new PaginatedResult([], false));

        $response = $this->createController($recordRepository, $watcherService)->indexAction(
            $this->createRequest(['watched' => '1']),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function indexActionReturns403WhenContentStatusVisibilityIsDenied(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission, the same
        // fixture user RecordControllerTest uses for the equivalent AJAX-facing denial.
        $this->loginBackendUser(2);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->expects(self::never())->method('findAllByFilter');

        $response = $this->createController($recordRepository)->indexAction($this->createRequest([]));

        self::assertSame(403, $response->getStatusCode());
    }

    private function createController(?RecordRepository $recordRepository = null, ?WatcherService $watcherService = null): RecordModuleController
    {
        return new RecordModuleController(
            $this->get(ModuleTemplateFactory::class),
            $this->get(UriBuilder::class),
            $recordRepository ?? $this->get(RecordRepository::class),
            $this->get(StatusRepository::class),
            $this->get(BackendUserRepository::class),
            $watcherService ?? $this->get(WatcherService::class),
        );
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function createRequest(array $queryParams): ServerRequestInterface
    {
        $moduleProvider = $this->get(ModuleProvider::class);
        $module = $moduleProvider->getModule('content_planner_records');
        self::assertNotNull($module, 'content_planner_records module must be registered');

        // BackendViewFactory reads the singular 'route' attribute (not the 'routing' RouteResult
        // that setUpBackendRequest() sets) to resolve which extension's own
        // Resources/Private/Templates to search; 'packageName' is normally filled in by TYPO3's
        // own module-to-route compilation, which calling the controller directly bypasses, so it
        // is set explicitly here.
        $route = new Route('/module/content-planner/records', [
            '_identifier' => 'content_planner_records',
            'packageName' => 'xima/xima-typo3-content-planner',
        ]);

        // setUpBackendRequest() also assigns $GLOBALS['TYPO3_REQUEST'], which
        // ModifyButtonBarEventListener (fired while the doc header renders) reads directly.
        $request = $this->setUpBackendRequest('content_planner_records', $queryParams)
            ->withAttribute('module', $module)
            ->withAttribute('route', $route);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $request;
    }
}
