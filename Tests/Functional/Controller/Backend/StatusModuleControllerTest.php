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
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
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
    public function indexActionPreviewsEachStatusAsABadgeInItsOwnColour(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/status_with_hidden.csv');

        $body = (string) $this->createController()->indexAction($this->createRequest('content_planner_status', []))->getBody();

        self::assertStringContainsString('content-planner-status-badge content-planner-status content-planner-status--blue', $body);
        self::assertStringContainsString('content-planner-status--green', $body);
        self::assertSame(3, substr_count($body, 'content-planner-status-badge '));
    }

    #[Test]
    public function indexActionExplainsWhatStatusesAreInAShortIntro(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/status_with_hidden.csv');

        $body = (string) $this->createController()->indexAction($this->createRequest('content_planner_status', []))->getBody();

        self::assertStringContainsString('<h1>Statuses</h1>', $body);
        self::assertStringContainsString('The order here is the order in every status menu', $body);
        self::assertStringContainsString('The default status is used by the comment-first one-click flow', $body);
    }

    #[Test]
    public function indexActionShowsDefaultAndHiddenAsValuesInsteadOfEmptyColumns(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/status_with_hidden.csv');

        $body = (string) $this->createController()->indexAction($this->createRequest('content_planner_status', []))->getBody();

        // status_with_hidden.csv: Draft is visible, Hidden Status is hidden, Done is the default.
        self::assertSame(1, substr_count($body, '>Default</span>'));
        self::assertSame(1, substr_count($body, '>Hidden</span>'));
        self::assertSame(2, substr_count($body, '>Visible</span>'));
        self::assertStringContainsString('>Visibility</th>', $body);
    }

    #[Test]
    public function indexActionNamesTheEditActionAsText(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/status_with_hidden.csv');

        $body = (string) $this->createController()->indexAction($this->createRequest('content_planner_status', []))->getBody();

        self::assertMatchesRegularExpression('#<a href="[^"]*" class="btn btn-default btn-sm"[^>]*>\s*(?:<span[^>]*>.*?</span>\s*)?<span class="content-planner-module__action-label">Edit</span>#s', $body);
    }

    #[Test]
    public function indexActionAnswersAnEmptyListWithACalloutAndOneAction(): void
    {
        $this->loginBackendUser(1);

        $body = (string) $this->createController()->indexAction($this->createRequest('content_planner_status', []))->getBody();

        self::assertStringContainsString('callout', $body);
        self::assertStringContainsString('No statuses yet', $body);
        self::assertMatchesRegularExpression('#<a href="[^"]*new[^"]*" class="btn btn-default">New status</a>#', $body);
        self::assertStringNotContainsString('<table', $body);
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
            $this->get(IconFactory::class),
            $this->get(PageRenderer::class),
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
