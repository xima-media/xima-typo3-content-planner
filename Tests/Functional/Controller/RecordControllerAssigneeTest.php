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
use Xima\XimaTypo3ContentPlanner\Controller\RecordController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

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

    private function createController(): RecordController
    {
        return new RecordController(
            $this->get(RecordRepository::class),
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
