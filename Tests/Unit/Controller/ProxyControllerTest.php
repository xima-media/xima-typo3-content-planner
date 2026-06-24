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
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\ProxyController;

/**
 * ProxyControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ProxyControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
    }

    public function testCloseDocumentActionRemovesCommentEntriesAndKeepsForeignTables(): void
    {
        $backendUser = $this->createMockBackendUser([
            'FormEngine' => [
                [
                    'md5comment' => ['Title', [], 'storeUrl', ['table' => Configuration::TABLE_COMMENT, 'uid' => 42], 'returnUrl'],
                    'md5page' => ['Page', [], 'storeUrl', ['table' => 'pages', 'uid' => 7], 'returnUrl'],
                ],
                'md5comment',
            ],
        ]);
        $GLOBALS['BE_USER'] = $backendUser;

        $response = $this->createController()->closeDocumentAction($this->createRequest());

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(1, $payload['removed']);
        self::assertSame(1, $payload['openDocuments']);

        // The comment entry was removed, the foreign (pages) entry persisted
        self::assertNotNull($backendUser->pushed);
        self::assertSame('FormEngine', $backendUser->pushed[0]);
        self::assertArrayNotHasKey('md5comment', $backendUser->pushed[1][0]);
        self::assertArrayHasKey('md5page', $backendUser->pushed[1][0]);
    }

    public function testCloseDocumentActionDoesNotPersistWhenNoCommentEntries(): void
    {
        $backendUser = $this->createMockBackendUser([
            'FormEngine' => [
                ['md5page' => ['Page', [], 'storeUrl', ['table' => 'pages', 'uid' => 7], 'returnUrl']],
                'md5page',
            ],
        ]);
        $GLOBALS['BE_USER'] = $backendUser;

        $response = $this->createController()->closeDocumentAction($this->createRequest());

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(0, $payload['removed']);
        self::assertSame(1, $payload['openDocuments']);
        self::assertNull($backendUser->pushed);
    }

    public function testCloseDocumentActionHandlesEmptyModuleData(): void
    {
        $backendUser = $this->createMockBackendUser([]);
        $GLOBALS['BE_USER'] = $backendUser;

        $response = $this->createController()->closeDocumentAction($this->createRequest());

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(0, $payload['removed']);
        self::assertSame(0, $payload['openDocuments']);
        self::assertNull($backendUser->pushed);
    }

    private function createController(): ProxyController
    {
        return new ProxyController($this->createMock(FlashMessageService::class));
    }

    private function createRequest(): ServerRequestInterface
    {
        return $this->createMock(ServerRequestInterface::class);
    }

    /**
     * @param array<string, mixed> $moduleData
     */
    private function createMockBackendUser(array $moduleData): object
    {
        return new class($moduleData) {
            /** @var array{0: string, 1: mixed}|null */
            public ?array $pushed = null;

            /**
             * @param array<string, mixed> $stored
             */
            public function __construct(public array $stored) {}

            public function getModuleData(string $key): mixed
            {
                return $this->stored[$key] ?? null;
            }

            public function pushModuleData(string $key, mixed $data): void
            {
                $this->pushed = [$key, $data];
                $this->stored[$key] = $data;
            }
        };
    }
}
