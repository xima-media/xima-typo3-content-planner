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
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3ContentPlanner\Controller\Backend\StatusModuleController;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * StatusModuleControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusModuleControllerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function indexActionReturns403ForNonAdminUser(): void
    {
        // Editor (uid 2) is a non-admin, the same fixture user used for the equivalent denial
        // in RecordModuleControllerTest and RecordControllerTest.
        $this->loginBackendUser(2);
        $this->importSharedDataSet('status.csv');

        $response = $this->createController()->indexAction($this->createRequest('content_planner_status', []));

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function indexActionListsStatusesInSortingOrderIncludingHidden(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/status_with_hidden.csv');

        $response = $this->createController()->indexAction($this->createRequest('content_planner_status', []));
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        // Sorting order: Draft (10), Hidden Status (20), Done (30).
        self::assertTrue(
            strpos($body, 'Draft') < strpos($body, 'Hidden Status')
            && strpos($body, 'Hidden Status') < strpos($body, 'Done'),
            'Statuses must appear in sorting order',
        );
        self::assertStringContainsString('Hidden Status', $body);
    }

    #[Test]
    public function moveActionReturns403ForNonAdminUser(): void
    {
        $this->loginBackendUser(2);
        $this->importSharedDataSet('status.csv');

        $response = $this->createController()->moveAction($this->createRequest('ajax_ximatypo3contentplanner_statusmove', ['uid' => 1, 'direction' => 'down']));

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function moveActionSwapsSortingWithTheFollowingStatus(): void
    {
        $this->loginBackendUser(1);
        $this->importSharedDataSet('status.csv');

        $this->createController()->moveAction($this->createRequest('ajax_ximatypo3contentplanner_statusmove', ['uid' => 1, 'direction' => 'down']));

        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_ximatypo3contentplanner_domain_model_status');
        $rows = $connection->executeQuery(
            'SELECT uid, title FROM tx_ximatypo3contentplanner_domain_model_status ORDER BY sorting ASC',
        )->fetchAllAssociative();

        self::assertSame('In Progress', $rows[0]['title']);
        self::assertSame('Draft', $rows[1]['title']);
        self::assertSame('Done', $rows[2]['title']);
    }

    #[Test]
    public function moveActionDoesNothingWhenAlreadyFirst(): void
    {
        $this->loginBackendUser(1);
        $this->importSharedDataSet('status.csv');

        $this->createController()->moveAction($this->createRequest('ajax_ximatypo3contentplanner_statusmove', ['uid' => 1, 'direction' => 'up']));

        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_ximatypo3contentplanner_domain_model_status');
        $rows = $connection->executeQuery(
            'SELECT title FROM tx_ximatypo3contentplanner_domain_model_status ORDER BY sorting ASC',
        )->fetchAllAssociative();

        self::assertSame('Draft', $rows[0]['title']);
    }

    private function createController(): StatusModuleController
    {
        return new StatusModuleController(
            $this->get(ModuleTemplateFactory::class),
            $this->get(UriBuilder::class),
            $this->get(ConnectionPool::class),
        );
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function createRequest(string $routeIdentifier, array $queryParams): ServerRequestInterface
    {
        $moduleProvider = $this->get(ModuleProvider::class);
        $module = $moduleProvider->getModule('content_planner_status');
        self::assertNotNull($module, 'content_planner_status module must be registered');

        $route = new Route('/module/content-planner/status', [
            '_identifier' => $routeIdentifier,
            'packageName' => 'xima/xima-typo3-content-planner',
        ]);

        $request = $this->setUpBackendRequest($routeIdentifier, $queryParams)
            ->withAttribute('module', $module)
            ->withAttribute('route', $route);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $request;
    }
}
