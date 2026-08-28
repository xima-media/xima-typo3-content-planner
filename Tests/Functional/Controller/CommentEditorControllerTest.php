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
use Xima\XimaTypo3ContentPlanner\Controller\CommentEditorController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Manager\CommentFirstFlowManager;
use Xima\XimaTypo3ContentPlanner\Service\RichText\CommentEditorConfigurationFactory;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * CommentEditorControllerTest.
 *
 * Covers the CP-28 (#327) inline comment composer: rendering the composer fragment
 * (commentEditorAction) and persisting comments through the DataHandler (commentSaveAction),
 * including the to-do checkbox round-trip regression required by the issue's acceptance
 * criteria.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentEditorControllerTest extends AbstractFunctionalTestCase
{
    // ==================== commentEditorAction ====================

    #[Test]
    public function commentEditorActionReturnsBadRequestWhenReplyParametersAreMissing(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->commentEditorAction($this->createGetRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function commentEditorActionReturnsNotFoundForUnknownComment(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->commentEditorAction($this->createGetRequest(['commentUid' => 99999]));

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function commentEditorActionDeniesUserWithoutContentPlannerAccess(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission.
        $this->loginBackendUser(2);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->commentEditorAction($this->createGetRequest(['table' => 'pages', 'uid' => 1, 'parentUid' => 1]));

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function commentEditorActionRejectsParentCommentBelongingToAnotherRecord(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->commentEditorAction($this->createGetRequest(['table' => 'pages', 'uid' => 2, 'parentUid' => 1]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function commentEditorActionRendersReplyComposerWithCkeditorWebComponent(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->commentEditorAction($this->createGetRequest(['table' => 'pages', 'uid' => 1, 'parentUid' => 1]));

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<typo3-rte-ckeditor-ckeditor5', $payload['result']);
        self::assertStringContainsString('data-mode="reply"', $payload['result']);
        self::assertStringContainsString('data-parent-uid="1"', $payload['result']);
    }

    #[Test]
    public function commentEditorActionRendersEditComposerPrefilledWithExistingContent(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->commentEditorAction($this->createGetRequest(['commentUid' => 1]));

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-mode="edit"', $payload['result']);
        self::assertStringContainsString('data-comment-uid="1"', $payload['result']);
        self::assertStringContainsString('Open comment', $payload['result']);
    }

    // ==================== commentSaveAction ====================

    #[Test]
    public function commentSaveActionRejectsEmptyContent(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->commentSaveAction($this->createPostRequest(['table' => 'pages', 'uid' => 1, 'content' => '   ']));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function commentSaveActionDeniesUserWithoutContentPlannerAccess(): void
    {
        $this->loginBackendUser(2);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $response = $this->createController()->commentSaveAction($this->createPostRequest(['table' => 'pages', 'uid' => 1, 'content' => 'Hello']));

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function commentSaveActionCreatesRootCommentThroughTheDataHandler(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $response = $this->createController()->commentSaveAction(
            $this->createPostRequest(['table' => 'pages', 'uid' => 1, 'content' => 'A brand new comment']),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $payload['parentUid']);
        self::assertStringContainsString('A brand new comment', $payload['result']);

        $comment = $this->get(CommentRepository::class)->findByUid((int) $payload['commentUid']);
        self::assertIsArray($comment);
        self::assertSame('pages', $comment['foreign_table']);
        self::assertSame(1, (int) $comment['foreign_uid']);
        // The form never submits an author - DataHandlerHook::fixNewCommentEntry() must
        // attribute it to the currently logged-in backend user.
        self::assertSame(1, (int) $comment['author']);
    }

    // ==================== commentSaveAction: comment-first flow (CP-27, #326) ====================

    #[Test]
    public function commentSaveActionSetsStatusAndSavesCommentAtomicallyOnAStatusLessRecord(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->enableExtensionFeature(Configuration::FEATURE_AUTO_ASSIGN);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages_status_less.csv');
        $this->importSharedDataSet('status.csv');

        $response = $this->createController()->commentSaveAction(
            $this->createPostRequest(['table' => 'pages', 'uid' => 20, 'content' => 'Starts the planning', 'statusUid' => 2]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());

        $comment = $this->get(CommentRepository::class)->findByUid((int) $payload['commentUid']);
        self::assertIsArray($comment);
        self::assertSame(20, (int) $comment['foreign_uid']);

        $page = $this->get(RecordRepository::class)->findByUid('pages', 20, ignoreVisibilityRestriction: true);
        self::assertIsArray($page);
        self::assertSame(2, (int) $page[Configuration::FIELD_STATUS]);
        // Auto-assignment only ever runs inside StatusChangeManager::handleAutoAssignment(),
        // reached exclusively through the same processContentPlannerFields() call a normal
        // FormEngine status change goes through - this is concrete evidence the comment-first
        // write used that identical path rather than a bypass.
        self::assertSame(1, (int) $page[Configuration::FIELD_ASSIGNEE]);
    }

    #[Test]
    public function commentSaveActionRejectsAStatusUidTheUserIsNotAllowedToSet(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission, so
        // canChangeStatus() is false for every status - a statusUid in the request is
        // therefore something the UI would never send, and is rejected outright.
        $this->loginBackendUser(2);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages_status_less.csv');
        $this->importSharedDataSet('status.csv');

        $response = $this->createController()->commentSaveAction(
            $this->createPostRequest(['table' => 'pages', 'uid' => 20, 'content' => 'Hello', 'statusUid' => 2]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function commentSaveActionIgnoresAStatusUidWhenTheRecordAlreadyHasAStatus(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importSharedDataSet('status.csv');

        // Page 1 already has status 2 (see pages.csv) - a comment-first statusUid must be
        // ignored rather than overwriting it.
        $response = $this->createController()->commentSaveAction(
            $this->createPostRequest(['table' => 'pages', 'uid' => 1, 'content' => 'Just a comment', 'statusUid' => 1]),
        );

        self::assertSame(200, $response->getStatusCode());

        $page = $this->get(RecordRepository::class)->findByUid('pages', 1, ignoreVisibilityRestriction: true);
        self::assertIsArray($page);
        self::assertSame(2, (int) $page[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function commentSaveActionCreatesReplyLinkedToItsParent(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->commentSaveAction(
            $this->createPostRequest(['table' => 'pages', 'uid' => 1, 'content' => 'A reply', 'parentUid' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $payload['parentUid']);

        $comment = $this->get(CommentRepository::class)->findByUid((int) $payload['commentUid']);
        self::assertIsArray($comment);
        self::assertSame(1, (int) $comment['parent_uid']);
    }

    #[Test]
    public function commentSaveActionRejectsParentCommentBelongingToAnotherRecord(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->commentSaveAction(
            $this->createPostRequest(['table' => 'pages', 'uid' => 2, 'content' => 'Reply to the wrong record', 'parentUid' => 1]),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function commentSaveActionUpdatesContentOfAnExistingComment(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->commentSaveAction(
            $this->createPostRequest(['content' => 'Edited content', 'commentUid' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $payload['commentUid']);

        $comment = $this->get(CommentRepository::class)->findByUid(1);
        self::assertIsArray($comment);
        self::assertSame('Edited content', $comment['content']);
        self::assertSame(1, (int) $comment['edited']);
    }

    #[Test]
    public function commentSaveActionReturnsNotFoundWhenEditingAMissingComment(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->commentSaveAction(
            $this->createPostRequest(['content' => 'Edited content', 'commentUid' => 99999]),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * Acceptance criterion: "To-do checkboxes round-trip identically (parsing regression
     * test on saved content)". This exercises the full write path - through
     * commentSaveAction, through the DataHandler, through DataHandlerHook::updateCommentTodo()
     * - and asserts the persisted todo_total/todo_resolved columns, not just the in-memory
     * datamap DataHandlerHookTest already covers.
     */
    #[Test]
    public function commentSaveActionRoundTripsTodoCheckboxesToTheSavedRecord(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $content = '<ul class="todo-list">'
            .'<li><input type="checkbox" checked>Done item</li>'
            .'<li><input type="checkbox">Open item</li>'
            .'<li><input type="checkbox" checked>Another done item</li>'
            .'</ul>';

        $response = $this->createController()->commentSaveAction(
            $this->createPostRequest(['table' => 'pages', 'uid' => 1, 'content' => $content]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());

        $comment = $this->get(CommentRepository::class)->findByUid((int) $payload['commentUid']);
        self::assertIsArray($comment);
        self::assertSame(3, (int) $comment['todo_total']);
        self::assertSame(2, (int) $comment['todo_resolved']);
    }

    private function createController(): CommentEditorController
    {
        return new CommentEditorController(
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(CommentEditorConfigurationFactory::class),
            $this->get(CommentFirstFlowManager::class),
        );
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function createGetRequest(array $queryParams): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);

        return $request;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createPostRequest(array $body): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn($body);

        return $request;
    }
}
