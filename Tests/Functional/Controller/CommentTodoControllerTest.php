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
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\CommentTodoController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * CommentTodoControllerTest.
 *
 * Covers CP-30 (#389), toggling a single to-do checkbox directly from the comment display
 * state (toggleTodoAction) - the main-targeted counterpart of PR #390, which built the same
 * behaviour on top of the not-yet-merged native comment composer (CommentEditorController).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentTodoControllerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function toggleTodoActionRejectsMissingParameters(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->toggleTodoAction($this->createRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function toggleTodoActionReturnsNotFoundForUnknownComment(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->toggleTodoAction(
            $this->createRequest(['commentUid' => 99999, 'todoIndex' => 0, 'checked' => 1]),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function toggleTodoActionDeniesUserWithoutEditPermission(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission, and did not
        // author comment uid 1 - both canEditComment() branches (own/foreign) fail.
        $this->loginBackendUser(2);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $response = $this->createController()->toggleTodoAction(
            $this->createRequest(['commentUid' => 1, 'todoIndex' => 0, 'checked' => 1]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function toggleTodoActionReturnsBadRequestForAnIndexBeyondTheAvailableCheckboxes(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $commentUid = $this->createComment('<ul class="todo-list"><li><input type="checkbox">Only item</li></ul>');

        $response = $this->createController()->toggleTodoAction(
            $this->createRequest(['commentUid' => $commentUid, 'todoIndex' => 1, 'checked' => 1]),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * Toggling one checkbox persists through the DataHandler (todo_total/todo_resolved
     * recalculated by DataHandlerHook::updateCommentTodo(), same as any other content edit)
     * while leaving the rest of the comment's rich-text content untouched, and - unlike a real
     * content edit - does not set the "edited" flag (DataHandlerHook::checkCommentEdited()'s
     * __todoToggle marker skip).
     */
    #[Test]
    public function toggleTodoActionPersistsTheToggleWithoutMarkingTheCommentEdited(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $content = '<p>Before the list</p><ul class="todo-list">'
            .'<li><input type="checkbox">Open item</li>'
            .'<li><input type="checkbox" checked>Done item</li>'
            .'</ul>';
        $commentUid = $this->createComment($content);

        $response = $this->createController()->toggleTodoAction(
            $this->createRequest(['commentUid' => $commentUid, 'todoIndex' => 0, 'checked' => 1]),
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
        self::assertSame(0, (int) $comment['edited']);
    }

    private function createController(): CommentTodoController
    {
        return new CommentTodoController($this->get(CommentRepository::class));
    }

    /**
     * @param array<string, mixed> $parsedBody
     */
    private function createRequest(array $parsedBody): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn($parsedBody);
        $request->method('getQueryParams')->willReturn([]);

        return $request;
    }

    private function createComment(string $content): int
    {
        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            Configuration::TABLE_COMMENT => [
                'NEW1' => [
                    'pid' => 0,
                    'foreign_uid' => 1,
                    'foreign_table' => 'pages',
                    'content' => $content,
                    'parent_uid' => 0,
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        return (int) $dataHandler->substNEWwithIDs['NEW1'];
    }
}
