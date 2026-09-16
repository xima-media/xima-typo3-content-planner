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
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Xima\XimaTypo3ContentPlanner\Controller\RecordController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\RichText\CommentEditorConfigurationFactory;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

use function count;

/**
 * RecordControllerAssigneeTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordControllerAssigneeTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function assigneeSelectionActionReturnsBadRequestWhenParametersMissing(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->assigneeSelectionAction($this->createRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function assigneeSelectionActionDeniesUserWithoutContentPlannerAccess(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission and must not
        // be able to probe record existence or retrieve the backend user list.
        $this->loginBackendUser(2);

        $response = $this->createController()->assigneeSelectionAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function assigneeSelectionActionReturnsNotFoundForUnknownRecord(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->assigneeSelectionAction(
            $this->createRequest(['table' => 'pages', 'uid' => 99999]),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function assigneeSelectionActionReturnsForbiddenWhenRecordAccessIsDenied(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        // Page uid 4 has a pid pointing at a non-existent parent page, so
        // BackendUtility::readPageAccess() cannot resolve it - even for an admin.
        $response = $this->createController()->assigneeSelectionAction(
            $this->createRequest(['table' => 'pages', 'uid' => 4]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function assigneeSelectionActionRendersAssigneesForAuthorizedUser(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $response = $this->createController()->assigneeSelectionAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1, 'currentAssignee' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('assignee-listbox', $payload['result']);
        self::assertStringContainsString('admin', $payload['result']);
    }

    #[Test]
    public function assigneeSelectionActionRendersListboxWithoutActionUrlsAsOptionValues(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $response = $this->createController()->assigneeSelectionAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1, 'currentAssignee' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        $result = $payload['result'];

        // CP-26 (#325): the assignee picker is a role="option"/role="listbox" markup, never a
        // native <select>/<option> whose value is an action URL.
        self::assertStringNotContainsString('<select', $result);
        self::assertStringNotContainsString('<option', $result);
        self::assertStringContainsString('role="listbox"', $result);
        self::assertStringContainsString('role="option"', $result);
    }

    #[Test]
    public function assigneeSelectionActionMarksCurrentAssigneeStructurally(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        $response = $this->createController()->assigneeSelectionAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1, 'currentAssignee' => 1]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        $result = $payload['result'];

        // The current assignee is marked via aria-current, not by label text (e.g. "--").
        self::assertStringContainsString('data-assignee-uid="1"', $result);
        self::assertMatchesRegularExpression('/data-assignee-uid="1"[^>]*aria-current="true"/', $result);
    }

    #[Test]
    public function assigneeSelectionActionRestrictsAssignSelfOnlyUserToOwnAssignment(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_assign_self.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $backendUser = $this->setUpBackendUser(5);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $this->setUpBackendRequest();

        // Page uid 2 is a child of the mounted page 1 - a non-admin backend user needs an
        // accessible pid (pid 0 is only ever readable by admins) to pass checkAccessForRecord().
        // currentAssignee matches the logged-in user (5): the "assign to self" scenario
        // for a user that only has assign-self permission, not assign-others.
        $response = $this->createController()->assigneeSelectionAction(
            $this->createRequest(['table' => 'pages', 'uid' => 2, 'currentAssignee' => 5]),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('assignee-listbox', $payload['result']);

        // The restriction is the point of this test, so assert it rather than just that a
        // listbox came back: the user may pick themselves, but every other entry must be
        // inert - no action URL to follow and marked disabled for assistive technology.
        self::assertMatchesRegularExpression(
            '/data-assignee-uid="5"[^>]*data-assignee-url="[^"]+"/',
            $payload['result'],
        );
        preg_match_all('/data-assignee-uid="(\d+)"[^>]*>/', $payload['result'], $matches, \PREG_SET_ORDER);
        self::assertGreaterThan(1, count($matches), 'Expected other backend users to be listed as well.');

        foreach ($matches as $match) {
            // uid 5 is the user themselves; uid 0 is the "not assigned" entry, which stays
            // actionable because clearing your own assignment is still an assign-self action.
            if ('5' === $match[1] || '0' === $match[1]) {
                continue;
            }

            self::assertStringNotContainsString('data-assignee-url="', $match[0]);
            self::assertStringContainsString('aria-disabled="true"', $match[0]);
        }
    }

    private function createController(): RecordController
    {
        return new RecordController(
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(BackendUserRepository::class),
            $this->get(RequestId::class),
            $this->get(CommentEditorConfigurationFactory::class),
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
