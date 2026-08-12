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
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Http\ServerRequest;
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
    public function isRelevantReturnsFalseWhenFilelistSupportDisabled(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport']);

        self::assertFalse($this->subject->isRelevant($this->buildRequest('ajax_filestorage_tree_data')));
    }

    #[Test]
    public function isRelevantReturnsFalseWhenRouteAttributeIsMissing(): void
    {
        self::assertFalse($this->subject->isRelevant($this->buildRequest(null)));
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

    #[Test]
    public function isRelevantReturnsTrueForTreeFilterRoute(): void
    {
        if (VersionUtility::is14OrHigher()) {
            self::markTestSkipped('FileStorageTreeModifier is v13-only; v14 uses the native event instead.');
        }

        self::assertTrue($this->subject->isRelevant($this->buildRequest('ajax_filestorage_tree_filter')));
    }

    #[Test]
    public function modifyReturnsResponseUnchangedWhenBodyIsEmpty(): void
    {
        $response = $this->subject->modify($this->buildRequest('ajax_filestorage_tree_data'), $this->buildResponseHandler('', status: 201, reasonPhrase: 'Created'));

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function modifyReturnsResponseUnchangedWhenBodyIsNotAJsonArray(): void
    {
        $response = $this->subject->modify($this->buildRequest('ajax_filestorage_tree_data'), $this->buildResponseHandler('"just a string"', status: 201, reasonPhrase: 'Created'));

        self::assertSame('"just a string"', (string) $response->getBody());
    }

    #[Test]
    public function modifyPreservesTheInnerResponseStatusReasonPhraseAndHeaders(): void
    {
        $items = [
            ['resourceType' => 'folder', 'storage' => 1, 'pathIdentifier' => '/user_upload/'],
        ];

        $response = $this->subject->modify(
            $this->buildRequest('ajax_filestorage_tree_data'),
            $this->buildResponseHandler(json_encode($items, \JSON_THROW_ON_ERROR), status: 201, reasonPhrase: 'Created'),
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Created', $response->getReasonPhrase());
        self::assertSame(['kept'], $response->getHeader('X-Custom-Header'));
    }

    #[Test]
    public function modifySkipsNonFolderAndIncompleteItems(): void
    {
        $items = [
            ['resourceType' => 'file', 'storage' => 1, 'pathIdentifier' => '/user_upload/example.pdf'],
            ['resourceType' => 'folder'],
            'not-an-array',
        ];

        $response = $this->subject->modify($this->buildRequest('ajax_filestorage_tree_data'), $this->buildResponseHandler(json_encode($items, \JSON_THROW_ON_ERROR), status: 201, reasonPhrase: 'Created'));
        $decoded = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('labels', $decoded[0]);
        self::assertArrayNotHasKey('labels', $decoded[1]);
        self::assertSame('not-an-array', $decoded[2]);
    }

    #[Test]
    public function modifyAddsEmptyLabelForFolderWithoutStatus(): void
    {
        $items = [
            ['resourceType' => 'folder', 'storage' => 1, 'pathIdentifier' => '/no_status/'],
        ];

        $response = $this->subject->modify($this->buildRequest('ajax_filestorage_tree_data'), $this->buildResponseHandler(json_encode($items, \JSON_THROW_ON_ERROR), status: 201, reasonPhrase: 'Created'));
        $decoded = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame([
            ['label' => '', 'color' => 'inherit', 'priority' => 0],
        ], $decoded[0]['labels']);
    }

    #[Test]
    public function modifyAddsEmptyLabelForUnknownFolder(): void
    {
        $items = [
            ['resourceType' => 'folder', 'storage' => 1, 'pathIdentifier' => '/does_not_exist/'],
        ];

        $response = $this->subject->modify($this->buildRequest('ajax_filestorage_tree_data'), $this->buildResponseHandler(json_encode($items, \JSON_THROW_ON_ERROR), status: 201, reasonPhrase: 'Created'));
        $decoded = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('', $decoded[0]['labels'][0]['label']);
        self::assertSame('inherit', $decoded[0]['labels'][0]['color']);
    }

    #[Test]
    public function modifyAddsStatusLabelForFolderWithAssignedStatus(): void
    {
        $items = [
            ['resourceType' => 'folder', 'storage' => 1, 'pathIdentifier' => '/user_upload/', 'labels' => []],
        ];

        $response = $this->subject->modify($this->buildRequest('ajax_filestorage_tree_data'), $this->buildResponseHandler(json_encode($items, \JSON_THROW_ON_ERROR), status: 201, reasonPhrase: 'Created'));
        $decoded = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertCount(1, $decoded[0]['labels']);
        self::assertSame('In Progress', $decoded[0]['labels'][0]['label']);
        self::assertSame(0, $decoded[0]['labels'][0]['priority']);
        self::assertNotSame('inherit', $decoded[0]['labels'][0]['color']);
    }

    #[Test]
    public function modifyDecodesUrlEncodedPathIdentifiers(): void
    {
        $items = [
            ['resourceType' => 'folder', 'storage' => 1, 'pathIdentifier' => urlencode('/user_upload/')],
        ];

        $response = $this->subject->modify($this->buildRequest('ajax_filestorage_tree_data'), $this->buildResponseHandler(json_encode($items, \JSON_THROW_ON_ERROR), status: 201, reasonPhrase: 'Created'));
        $decoded = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('In Progress', $decoded[0]['labels'][0]['label']);
    }

    private function buildRequest(?string $routeIdentifier): ServerRequest
    {
        $request = new ServerRequest('https://example.com/typo3/ajax/filestorage/tree/data', 'GET');

        if (null !== $routeIdentifier) {
            $request = $request->withAttribute('route', new Route('/ajax/filestorage/tree/data', ['_identifier' => $routeIdentifier]));
        }

        return $request;
    }
}
