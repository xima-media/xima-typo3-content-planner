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
use Xima\XimaTypo3ContentPlanner\Controller\MentionController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * MentionControllerTest.
 *
 * Covers the permission-filtered @-mention endpoints (issue #305): the suggestion feed and the
 * profile card behind a rendered mention. The recurring theme is the same for both: a backend
 * user outside the CP-permitted pool must never appear in - or be retrievable through - either
 * of them, and the card must not become the one place a non-admin can read e-mail addresses.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class MentionControllerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function suggestActionReturnsBadRequestWhenParametersMissing(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->suggestAction($this->createRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function suggestActionDeniesUserWithoutContentPlannerAccess(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission and must not be
        // able to probe record existence or retrieve the backend user list.
        $this->loginBackendUser(2);

        $response = $this->createController()->suggestAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function suggestActionReturnsNotFoundForUnknownRecord(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->suggestAction(
            $this->createRequest(['table' => 'pages', 'uid' => 99999]),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function suggestActionReturnsForbiddenWhenRecordAccessIsDenied(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');

        // Page uid 4 has a pid pointing at a non-existent parent page, so
        // BackendUtility::readPageAccess() cannot resolve it - even for an admin.
        $response = $this->createController()->suggestAction(
            $this->createRequest(['table' => 'pages', 'uid' => 4]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function suggestActionReturnsOnlyContentPlannerPermittedUsers(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_mention.csv');

        $response = $this->createController()->suggestAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $usernames = array_column($payload['result'], 'id');

        // Permitted, directly or via a group's subgroup: admin (always), "member" (direct
        // group membership) and "parentmember" (inherited via the parent group).
        self::assertContains('@admin', $usernames);
        self::assertContains('@member', $usernames);
        self::assertContains('@parentmember', $usernames);

        // Must never leak a user outside the CP-permitted pool: no group at all, disabled, or
        // (soft-)deleted.
        self::assertNotContains('@nogroup', $usernames);
        self::assertNotContains('@disabled', $usernames);
        self::assertNotContains('@deleted', $usernames);
    }

    #[Test]
    public function suggestActionFiltersSuggestionsByTermCaseInsensitively(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_mention.csv');

        $response = $this->createController()->suggestAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1, 'term' => 'MEMBER']),
        );

        $payload = json_decode((string) $response->getBody(), true);
        $usernames = array_column($payload['result'], 'id');

        self::assertContains('@member', $usernames);
        self::assertContains('@parentmember', $usernames);
        self::assertNotContains('@admin', $usernames);
    }

    #[Test]
    public function profileActionReturnsBadRequestWithoutAUid(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->profileAction($this->createRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function profileActionDeniesUserWithoutContentPlannerAccess(): void
    {
        $this->loginBackendUser(2);

        $response = $this->createController()->profileAction($this->createRequest(['uid' => 1]));

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function profileActionRefusesAUidOutsideTheMentionablePool(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_mention.csv');

        // "nogroup" (uid 11) exists but has no content planner permission, so the card must not
        // turn into a be_users lookup for arbitrary uids.
        $response = $this->createController()->profileAction($this->createRequest(['uid' => 11]));

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function profileActionRendersTheCardForAMentionableUser(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_mention.csv');

        $response = $this->createController()->profileAction($this->createRequest(['uid' => 10]));

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        self::assertStringContainsString('Member User', $payload['result']);
        self::assertStringContainsString('member', $payload['result']);
    }

    #[Test]
    public function profileActionShowsTheEmailToAnAdmin(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_mention.csv');

        $response = $this->createController()->profileAction($this->createRequest(['uid' => 10]));

        $payload = json_decode((string) $response->getBody(), true);
        self::assertStringContainsString('member@example.com', $payload['result']);
    }

    #[Test]
    public function profileActionHidesTheEmailFromANonAdmin(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_mention.csv');
        // "member" (uid 10) may use the content planner, so the card itself is allowed - but
        // TYPO3 shows a non-admin another user's e-mail address nowhere else either.
        $this->loginBackendUser(10);
        $this->setUpBackendRequest();

        $response = $this->createController()->profileAction($this->createRequest(['uid' => 14]));

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        self::assertStringContainsString('Parent Group Member', $payload['result']);
        self::assertStringNotContainsString('parentmember@example.com', $payload['result']);
    }

    private function createController(): MentionController
    {
        return new MentionController(
            $this->get(RecordRepository::class),
            $this->get(BackendUserRepository::class),
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
