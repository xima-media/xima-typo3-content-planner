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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\ContentModifier;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\FileStorageTreeModifier;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\VersionUtility;

/**
 * FileStorageTreeModifierTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class FileStorageTreeModifierTest extends AbstractFunctionalTestCase
{
    private FileStorageTreeModifier $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importSharedDataSet('folders.csv');
        $this->loginBackendUser();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport'] = 1;
        $this->subject = $this->get(FileStorageTreeModifier::class);
    }

    #[Test]
    public function modifyPreservesTheInnerResponseStatusReasonPhraseAndHeaders(): void
    {
        $items = [
            ['resourceType' => 'folder', 'storage' => 1, 'pathIdentifier' => '/user_upload/'],
        ];

        $response = $this->subject->modify(
            $this->buildRequest('ajax_filestorage_tree_data'),
            $this->buildHandler(json_encode($items, \JSON_THROW_ON_ERROR)),
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Created', $response->getReasonPhrase());
        self::assertSame(['kept'], $response->getHeader('X-Custom-Header'));
    }

    #[Test]
    public function modifyAddsStatusLabelForFolderWithAssignedStatus(): void
    {
        $items = [
            ['resourceType' => 'folder', 'storage' => 1, 'pathIdentifier' => '/user_upload/', 'labels' => []],
        ];

        $response = $this->subject->modify(
            $this->buildRequest('ajax_filestorage_tree_data'),
            $this->buildHandler(json_encode($items, \JSON_THROW_ON_ERROR)),
        );
        $decoded = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertCount(1, $decoded[0]['labels']);
        self::assertSame('In Progress', $decoded[0]['labels'][0]['label']);
    }

    #[Test]
    public function isRelevantReturnsFalseForDisallowedRoute(): void
    {
        self::assertFalse($this->subject->isRelevant($this->buildRequest('ajax_filestorage_tree_rootline')));
    }

    #[Test]
    public function isRelevantReturnsTrueForTreeDataRoute(): void
    {
        if (VersionUtility::is14OrHigher()) {
            self::markTestSkipped('FileStorageTreeModifier is v13-only; v14 uses the native event instead.');
        }

        self::assertTrue($this->subject->isRelevant($this->buildRequest('ajax_filestorage_tree_data')));
    }

    private function buildRequest(string $routeIdentifier): ServerRequest
    {
        return (new ServerRequest('https://example.com/typo3/ajax/filestorage/tree/data', 'GET'))
            ->withAttribute('route', new Route('/ajax/filestorage/tree/data', ['_identifier' => $routeIdentifier]));
    }

    private function buildHandler(string $jsonBody): RequestHandlerInterface
    {
        return new class($jsonBody) implements RequestHandlerInterface {
            public function __construct(private readonly string $jsonBody) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = new Response('php://temp', 201, ['X-Custom-Header' => ['kept']], 'Created');
                $response->getBody()->write($this->jsonBody);

                return $response;
            }
        };
    }
}
