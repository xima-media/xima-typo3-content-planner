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
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Http\RedirectResponse;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\RecordController;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * RecordControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordControllerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function commentsActionReturnsBadRequestWhenParametersMissing(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->commentsAction($this->createRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function commentsActionReturnsNotFoundForUnknownRecord(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->commentsAction(
            $this->createRequest(['table' => 'pages', 'uid' => 99999]),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function commentsActionDeniesUserWithoutContentPlannerAccess(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission.
        $this->loginBackendUser(2);

        $response = $this->createController()->commentsAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function commentsActionReturnsForbiddenWhenRecordAccessIsDenied(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        // Page uid 4 has a pid pointing at a non-existent parent page, so
        // BackendUtility::readPageAccess() cannot resolve it - even for an admin.
        $response = $this->createController()->commentsAction(
            $this->createRequest(['table' => 'pages', 'uid' => 4]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function commentsActionRendersOpenCommentsForAuthorizedUser(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->commentsAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Open comment', $payload['result']);
        self::assertStringNotContainsString('Resolved comment', $payload['result']);
    }

    #[Test]
    public function commentsActionIncludesResolvedCommentsWhenRequested(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->commentsAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1, 'showResolvedComments' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Resolved comment', $payload['result']);
    }

    #[Test]
    public function shareActionReturnsBadRequestWhenParametersMissing(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->shareAction($this->createRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function shareActionDeniesUserWithoutContentPlannerAccess(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission.
        $this->loginBackendUser(2);

        $response = $this->createController()->shareAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function shareActionReturnsNotFoundForUnknownRecord(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->shareAction(
            $this->createRequest(['table' => 'pages', 'uid' => 99999]),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function shareActionReturnsForbiddenWhenRecordAccessIsDenied(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        // Page uid 4 has a pid pointing at a non-existent parent page, so
        // BackendUtility::readPageAccess() cannot resolve it - even for an admin.
        $response = $this->createController()->shareAction(
            $this->createRequest(['table' => 'pages', 'uid' => 4]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function shareActionRedirectsToRecordLinkForAuthorizedRecord(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $response = $this->createController()->shareAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('id=1', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function shareActionIncludesUnresolvedCommentParamWhenCommentMatchesRecord(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->shareAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1, 'comment' => 1]),
        );

        $location = $response->getHeaderLine('Location');
        self::assertStringContainsString('tx_contentplanner_comment=1', $location);
        self::assertStringNotContainsString('tx_contentplanner_comment_resolved', $location);
    }

    #[Test]
    public function shareActionIncludesResolvedCommentParamWhenCommentIsResolved(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->shareAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1, 'comment' => 2]),
        );

        self::assertStringContainsString('tx_contentplanner_comment_resolved=1', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function shareActionIgnoresCommentParamWhenCommentDoesNotMatchRecord(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->shareAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1, 'comment' => 999]),
        );

        self::assertStringNotContainsString('tx_contentplanner_comment=', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function filterActionReturnsMappedResultsFromRepository(): void
    {
        $this->loginBackendUser(1);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->expects(self::once())
            ->method('findAllByFilter')
            ->with('term', 2, 1, 'pages', true, 20, true)
            ->willReturn(new PaginatedResult([
                [
                    'uid' => 1,
                    'pid' => 0,
                    'tstamp' => 1000,
                    'tablename' => 'pages',
                    'title' => 'Home',
                    Configuration::FIELD_STATUS => 2,
                    Configuration::FIELD_ASSIGNEE => 1,
                    Configuration::FIELD_COMMENTS => 0,
                ],
            ], false));

        $response = $this->createController($recordRepository)->filterAction(
            $this->createRequest([
                'search' => 'term',
                'status' => 2,
                'assignee' => 1,
                'todo' => 1,
                'type' => 'pages',
                'openComments' => 1,
            ]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($payload['hasMore']);
        self::assertCount(1, $payload['items']);
        self::assertSame('Home', $payload['items'][0]['title']);
    }

    #[Test]
    public function filterActionSurfacesHasMoreFromRepository(): void
    {
        $this->loginBackendUser(1);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->expects(self::once())
            ->method('findAllByFilter')
            ->willReturn(new PaginatedResult([
                [
                    'uid' => 1,
                    'pid' => 0,
                    'tstamp' => 1000,
                    'tablename' => 'pages',
                    'title' => 'Home',
                    Configuration::FIELD_STATUS => 2,
                    Configuration::FIELD_ASSIGNEE => 1,
                    Configuration::FIELD_COMMENTS => 0,
                ],
            ], true));

        $response = $this->createController($recordRepository)->filterAction($this->createRequest([]));

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['hasMore']);
    }

    #[Test]
    public function filterActionReturnsEmptyResultWhenNoParametersProvided(): void
    {
        $this->loginBackendUser(1);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->expects(self::once())
            ->method('findAllByFilter')
            ->with(null, null, null, null, false, 20, false)
            ->willReturn(new PaginatedResult([], false));

        $response = $this->createController($recordRepository)->filterAction($this->createRequest([]));

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($payload['hasMore']);
        self::assertSame([], $payload['items']);
    }

    #[Test]
    public function userSettingActionReturnsBadRequestForDisallowedKey(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->userSettingAction(
            $this->createRequest(['key' => 'notAllowed', 'value' => '1']),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function userSettingActionReturnsBadRequestForInvalidValue(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->userSettingAction(
            $this->createRequest(['key' => 'repliesExpanded', 'value' => 'yes']),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function userSettingActionPersistsValidSetting(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->userSettingAction(
            $this->createRequest(['key' => 'repliesExpanded', 'value' => '1']),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('repliesExpanded', $payload['key']);
        self::assertTrue($payload['value']);

        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        self::assertTrue($backendUser->uc['contentPlanner']['repliesExpanded']);
    }

    private function createController(?RecordRepository $recordRepository = null): RecordController
    {
        return new RecordController(
            $recordRepository ?? $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(BackendUserRepository::class),
            $this->get(RequestId::class),
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
