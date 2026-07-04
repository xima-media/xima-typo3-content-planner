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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\FolderController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\FolderStatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * FolderControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class FolderControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        PermissionUtility::resetCache();
        GeneralUtility::purgeInstances();
    }

    public function testFolderStatusUpdateRouteIsNotPublic(): void
    {
        $routes = require __DIR__.'/../../../Configuration/Backend/Routes.php';

        self::assertArrayHasKey('ximatypo3contentplanner_folder_status_update', $routes);
        self::assertNotSame(
            'public',
            $routes['ximatypo3contentplanner_folder_status_update']['access'] ?? null,
            'The folder status update route must not be publicly accessible (auth + CSRF token required).',
        );
    }

    public function testUpdateStatusActionDeniesUserWithoutPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createBackendUser(isAdmin: false, permitted: false);
        $this->enableFilelistSupport();

        $repository = $this->createMock(FolderStatusRepository::class);
        $repository->expects(self::never())->method('createOrUpdate');

        $response = (new FolderController($repository))->updateStatusAction(
            $this->createRequest(['identifier' => '1:/user_upload/', 'status' => '3']),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUpdateStatusActionPersistsForPermittedUser(): void
    {
        $GLOBALS['BE_USER'] = $this->createBackendUser(isAdmin: true, permitted: true);
        $this->enableFilelistSupport();

        $repository = $this->createMock(FolderStatusRepository::class);
        $repository->expects(self::once())
            ->method('createOrUpdate')
            ->with('1:/user_upload/', 3, null)
            ->willReturn(42);

        $response = (new FolderController($repository))->updateStatusAction(
            $this->createRequest(['identifier' => '1:/user_upload/', 'status' => '3']),
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        self::assertTrue($payload['success']);
        self::assertSame(42, $payload['uid']);
    }

    public function testUpdateStatusActionIgnoresExternalRedirect(): void
    {
        $GLOBALS['BE_USER'] = $this->createBackendUser(isAdmin: true, permitted: true);
        $this->enableFilelistSupport();

        $repository = $this->createMock(FolderStatusRepository::class);
        $repository->method('createOrUpdate')->willReturn(1);

        $response = (new FolderController($repository))->updateStatusAction(
            $this->createRequest(
                ['identifier' => '1:/user_upload/', 'status' => '3', 'redirect' => 'https://evil.example/'],
            ),
        );

        self::assertNotInstanceOf(RedirectResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    private function enableFilelistSupport(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn(['enableFilelistSupport' => true]);
        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfiguration);
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function createRequest(array $queryParams): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn([]);
        $request->method('getQueryParams')->willReturn($queryParams);

        return $request;
    }

    private function createBackendUser(bool $isAdmin, bool $permitted): object
    {
        return new class($isAdmin, $permitted) {
            /** @var array<string, mixed> */
            public array $user = ['uid' => 1, 'tx_ximatypo3contentplanner_hide' => 0];

            public function __construct(private readonly bool $isAdmin, private readonly bool $permitted) {}

            public function isAdmin(): bool
            {
                return $this->isAdmin;
            }

            public function check(string $type, string $value): bool
            {
                return $this->permitted;
            }
        };
    }
}
