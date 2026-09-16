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
use Xima\XimaTypo3ContentPlanner\Controller\CommentEditorController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\RichText\CommentEditorConfigurationFactory;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * CommentEditorControllerTest.
 *
 * Covers the CP-28 (#327) inline comment composer: rendering the composer fragment
 * (commentEditorAction) and persisting comments through the DataHandler (commentSaveAction),
 * including the to-do checkbox round-trip regression required by the issue's acceptance
 * criteria. Also covers CP-30 (#389), toggling a single to-do checkbox directly from the
 * comment display state (commentToggleTodoAction).
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

    // ==================== commentToggleTodoAction ====================

    #[Test]
    public function commentToggleTodoActionRejectsMissingParameters(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->commentToggleTodoAction($this->createPostRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function commentToggleTodoActionReturnsNotFoundForUnknownComment(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->commentToggleTodoAction(
            $this->createPostRequest(['commentUid' => 99999, 'todoIndex' => 0, 'checked' => 1]),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function commentToggleTodoActionDeniesUserWithoutEditPermission(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission, and did not
        // author comment uid 1 - both canEditComment() branches (own/foreign) fail.
        $this->loginBackendUser(2);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->commentToggleTodoAction(
            $this->createPostRequest(['commentUid' => 1, 'todoIndex' => 0, 'checked' => 1]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function commentToggleTodoActionReturnsBadRequestForAnIndexBeyondTheAvailableCheckboxes(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $created = $this->createController()->commentSaveAction($this->createPostRequest([
            'table' => 'pages',
            'uid' => 1,
            'content' => '<ul class="todo-list"><li><input type="checkbox">Only item</li></ul>',
        ]));
        $commentUid = (int) json_decode((string) $created->getBody(), true)['commentUid'];

        $response = $this->createController()->commentToggleTodoAction(
            $this->createPostRequest(['commentUid' => $commentUid, 'todoIndex' => 1, 'checked' => 1]),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * Toggling one checkbox persists through the DataHandler (todo_total/todo_resolved
     * recalculated by DataHandlerHook::updateCommentTodo(), same as any other content edit)
     * while leaving the rest of the comment's rich-text content untouched.
     */
    #[Test]
    public function commentToggleTodoActionPersistsTheToggleWithoutTouchingOtherContent(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $content = '<p>Before the list</p><ul class="todo-list">'
            .'<li><input type="checkbox">Open item</li>'
            .'<li><input type="checkbox" checked>Done item</li>'
            .'</ul>';

        $created = $this->createController()->commentSaveAction($this->createPostRequest([
            'table' => 'pages',
            'uid' => 1,
            'content' => $content,
        ]));
        $commentUid = (int) json_decode((string) $created->getBody(), true)['commentUid'];

        $response = $this->createController()->commentToggleTodoAction(
            $this->createPostRequest(['commentUid' => $commentUid, 'todoIndex' => 0, 'checked' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $payload['todoTotal']);
        self::assertSame(2, $payload['todoResolved']);

        $comment = $this->get(CommentRepository::class)->findByUid($commentUid);
        self::assertIsArray($comment);
        self::assertStringContainsString('<p>Before the list</p>', $comment['content']);
        self::assertSame(2, (int) $comment['todo_total']);
        self::assertSame(2, (int) $comment['todo_resolved']);
        // Unlike a real content edit (see commentSaveActionUpdatesContentOfAnExistingComment
        // above), a to-do toggle must not trip the "edited" flag or badge - it changes a
        // checkbox state, not the comment text.
        self::assertSame(0, (int) $comment['edited']);
    }

    /**
     * Unchecking is the same code path with the inverse `checked` argument - covered
     * separately since toggleTodoCheckbox() branches on it explicitly.
     */
    #[Test]
    public function commentToggleTodoActionCanUncheckAPreviouslyCheckedItem(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $created = $this->createController()->commentSaveAction($this->createPostRequest([
            'table' => 'pages',
            'uid' => 1,
            'content' => '<ul class="todo-list"><li><input type="checkbox" checked>Done item</li></ul>',
        ]));
        $commentUid = (int) json_decode((string) $created->getBody(), true)['commentUid'];

        $response = $this->createController()->commentToggleTodoAction(
            $this->createPostRequest(['commentUid' => $commentUid, 'todoIndex' => 0, 'checked' => 0]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $payload['todoResolved']);

        $comment = $this->get(CommentRepository::class)->findByUid($commentUid);
        self::assertIsArray($comment);
        self::assertSame(0, (int) $comment['todo_resolved']);
    }

    private function createController(): CommentEditorController
    {
        return new CommentEditorController(
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(CommentEditorConfigurationFactory::class),
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
