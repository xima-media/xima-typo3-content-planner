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
use TYPO3\CMS\Backend\Module\Module;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\SysFileMetadataRepository;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\FileListModifier;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * FileListModifierTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class FileListModifierTest extends AbstractFunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['filelist'];

    private FileListModifier $subject;

    /**
     * sys_file_metadata.uid of fileadmin/user_upload/example.pdf, populated once TYPO3's FAL
     * auto-indexing assigns it during setUp() — the actual value depends on auto-increment
     * state, so it cannot be hardcoded in fixtures/HTML.
     */
    private int $exampleMetaUid;

    /**
     * sys_file_metadata.uid of fileadmin/user_upload/image.jpg (left without a status).
     */
    private int $imageMetaUid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/filelist_folders.csv');
        $this->loginBackendUser();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport'] = 1;

        $this->createLocalStorage();
        $this->createFilelistFixtureFilesystem();
        $this->seedFileMetadataStatuses();

        $request = $this->setUpBackendRequest('media_management', ['id' => '1:/user_upload/'])
            ->withAttribute('module', Module::createFromConfiguration('media_management', ['path' => '/module/file/list']));
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $this->subject = $this->get(FileListModifier::class);
    }

    #[Test]
    public function isRelevantReturnsFalseWhenNotBackendRequest(): void
    {
        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('module', Module::createFromConfiguration('media_management', ['path' => '/module/file/list']))
            ->withQueryParams(['id' => '1:/user_upload/']);

        self::assertFalse($this->subject->isRelevant($request));
    }

    #[Test]
    public function isRelevantReturnsFalseWhenFilelistSupportDisabled(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport']);

        self::assertFalse($this->subject->isRelevant($this->buildRequest('media_management', '1:/user_upload/')));
    }

    #[Test]
    public function isRelevantReturnsFalseWhenModuleAttributeMissing(): void
    {
        self::assertFalse($this->subject->isRelevant($this->buildRequest(null, '1:/user_upload/')));
    }

    #[Test]
    public function isRelevantReturnsFalseForNonFileListRoute(): void
    {
        self::assertFalse($this->subject->isRelevant($this->buildRequest('web_list', '1:/user_upload/')));
    }

    #[Test]
    public function isRelevantReturnsTrueForFileListRoute(): void
    {
        self::assertTrue($this->subject->isRelevant($this->buildRequest('media_management', '1:/user_upload/')));
    }

    #[Test]
    public function modifyReturnsResponseUnchangedWhenBodyIsEmpty(): void
    {
        $response = $this->subject->modify($this->buildRequest('media_management', '1:/user_upload/'), $this->buildResponseHandler(''));

        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function modifyReturnsResponseUnchangedWhenIdQueryParamMissing(): void
    {
        $originalBody = $this->buildListViewBody();
        $response = $this->subject->modify($this->buildRequest('media_management', null), $this->buildResponseHandler($originalBody));

        self::assertSame($originalBody, (string) $response->getBody());
    }

    #[Test]
    public function modifyInjectsFolderHeaderDropdownsAndStylesForListView(): void
    {
        $response = $this->subject->modify($this->buildRequest('media_management', '1:/user_upload/'), $this->buildResponseHandler($this->buildListViewBody()));
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['kept'], $response->getHeader('X-Custom-Header'));

        // Folder status header for the current folder (status 2 = "In Progress") was injected
        // right after the container div, before the table.
        self::assertGreaterThan(
            strpos($body, '<div class="t3-filelist-container">'),
            strpos($body, 'In Progress'),
        );
        self::assertLessThan(strpos($body, '<table id="typo3-filelist">'), strpos($body, 'In Progress'));

        // File with a status ("In Progress") gets a titled dropdown.
        self::assertMatchesRegularExpression(
            '/data-filelist-meta-uid="'.$this->exampleMetaUid.'".*?<div class="btn-group dropdown">.*?title="In Progress"/s',
            $body,
        );
        // File without a status falls back to the generic "Status" title.
        self::assertMatchesRegularExpression(
            '/data-filelist-meta-uid="'.$this->imageMetaUid.'".*?<div class="btn-group dropdown">.*?title="Content Status"/s',
            $body,
        );
        // Subfolder with a status (status 3 = "Done") gets a titled dropdown.
        self::assertMatchesRegularExpression(
            '/data-filelist-identifier="1:\/user_upload\/sub\/".*?<div class="btn-group dropdown">.*?title="Done"/s',
            $body,
        );

        // CP-14 (#318): the icon-only dropdown toggle also carries an explicit aria-label,
        // not just a `title` attribute.
        self::assertMatchesRegularExpression(
            '/data-filelist-meta-uid="'.$this->exampleMetaUid.'".*?<div class="btn-group dropdown">.*?aria-label="In Progress"/s',
            $body,
        );

        // CSS for the statused file and subfolder was injected as a <style> block before the table.
        self::assertStringContainsString('<style>', $body);
        self::assertStringContainsString('tr[data-filelist-meta-uid="'.$this->exampleMetaUid.'"] > td', $body);
        self::assertStringContainsString('tr[data-filelist-identifier="1:/user_upload/sub/"] > td', $body);
        self::assertLessThan(strpos($body, '<table id="typo3-filelist">'), strpos($body, '<style>'));
    }

    #[Test]
    public function modifyInjectsHeaderIntoInfoContainerWhenFilelistContainerIsHidden(): void
    {
        $body = <<<'HTML'
            <html><body>
            <div class="t3-filelist-info-container"></div>
            <div class="t3-filelist-container hidden">
            <table id="typo3-filelist"><tbody></tbody></table>
            </div>
            </body></html>
            HTML;

        $response = $this->subject->modify($this->buildRequest('media_management', '1:/user_upload/'), $this->buildResponseHandler($body));
        $result = (string) $response->getBody();

        self::assertGreaterThan(
            strpos($result, '<div class="t3-filelist-info-container">'),
            strpos($result, 'In Progress'),
        );
    }

    #[Test]
    public function modifySkipsDropdownAndListStylingForTilesView(): void
    {
        $body = <<<HTML
            <html><body>
            <div class="t3-filelist-container">
            <div class="resource-tiles">
            <div class="resource-tile" data-filelist-meta-uid="{$this->exampleMetaUid}"></div>
            </div>
            </div>
            </body></html>
            HTML;

        $response = $this->subject->modify($this->buildRequest('media_management', '1:/user_upload/'), $this->buildResponseHandler($body));
        $result = (string) $response->getBody();

        // Tiles view CSS uses .resource-tile selectors and is injected before the tiles container.
        self::assertStringContainsString('<style>', $result);
        self::assertStringContainsString('.resource-tile[data-filelist-meta-uid="'.$this->exampleMetaUid.'"]', $result);
        self::assertLessThan(strpos($result, '<div class="resource-tiles">'), strpos($result, '<style>'));
        // No btn-group dropdown markup was injected since tiles view skips row dropdowns entirely.
        self::assertStringNotContainsString('dropdown-menu', $result);
    }

    #[Test]
    public function modifyLeavesListStylingUntouchedForFolderWithoutAnyStatusedChildren(): void
    {
        $body = <<<'HTML'
            <html><body>
            <div class="t3-filelist-container">
            <table id="typo3-filelist">
            <tbody>
            <tr data-filelist-identifier="1:/user_upload/sub/empty/"><td>empty</td><td><div class="btn-group"></div></td></tr>
            </tbody>
            </table>
            </div>
            </body></html>
            HTML;

        if (!is_dir($this->instancePath.'/fileadmin/user_upload/sub/empty')) {
            mkdir($this->instancePath.'/fileadmin/user_upload/sub/empty', 0777, true);
        }

        $response = $this->subject->modify($this->buildRequest('media_management', '1:/user_upload/sub/'), $this->buildResponseHandler($body));
        $result = (string) $response->getBody();

        // No <style> block, since neither the "empty" child folder nor any file has a status
        // (the "sub" folder itself does, but that only affects its own header/dropdown, not
        // the CSS generated for its children).
        self::assertStringNotContainsString('<style>', $result);
        // The dropdown still gets the generic fallback title/icon. The fallback is the
        // translated "status" label, the same one the record list actions use.
        self::assertStringContainsString('title="Content Status"', $result);
    }

    private function createLocalStorage(): void
    {
        $configuration = "<?xml version='1.0' encoding='UTF-8' standalone='yes' ?>"
            .'<T3FlexForms><data><sheet index=\'sDEF\'><language index=\'lDEF\'>'
            .'<field index=\'basePath\'><value index=\'vDEF\'>fileadmin/</value></field>'
            .'<field index=\'pathType\'><value index=\'vDEF\'>relative</value></field>'
            .'<field index=\'caseSensitive\'><value index=\'vDEF\'>1</value></field>'
            .'</language></sheet></data></T3FlexForms>';

        $this->getConnectionPool()->getConnectionForTable('sys_file_storage')->insert('sys_file_storage', [
            'uid' => 1,
            'pid' => 0,
            'name' => 'fileadmin/ (test)',
            'driver' => 'Local',
            'configuration' => $configuration,
            'is_default' => 1,
            'is_browsable' => 1,
            'is_public' => 1,
            'is_writable' => 1,
            'is_online' => 1,
        ]);
    }

    private function createFilelistFixtureFilesystem(): void
    {
        $userUploadPath = $this->instancePath.'/fileadmin/user_upload';
        if (!is_dir($userUploadPath.'/sub')) {
            mkdir($userUploadPath.'/sub', 0777, true);
        }
        file_put_contents($userUploadPath.'/example.pdf', 'dummy pdf content');
        file_put_contents($userUploadPath.'/image.jpg', 'dummy image content');
    }

    /**
     * Physically present files are not present in sys_file/sys_file_metadata until FAL indexes
     * them (matched by identifier_hash, which a hand-written CSV fixture cannot pre-compute
     * reliably). Trigger indexing by listing the folder, then assign statuses by UID.
     */
    private function seedFileMetadataStatuses(): void
    {
        $folder = $this->get(ResourceFactory::class)->getFolderObjectFromCombinedIdentifier('1:/user_upload/');
        $metadataRepository = $this->get(SysFileMetadataRepository::class);

        foreach ($folder->getFiles() as $file) {
            $metadata = $metadataRepository->findByIdentifier($file->getIdentifier());
            self::assertIsArray($metadata);
            $metaUid = (int) $metadata['uid'];

            if ('/user_upload/example.pdf' === $file->getIdentifier()) {
                $metadataRepository->updateStatus($metaUid, 2); // "In Progress"
                $this->exampleMetaUid = $metaUid;
            } elseif ('/user_upload/image.jpg' === $file->getIdentifier()) {
                $this->imageMetaUid = $metaUid;
            }
        }
    }

    private function buildListViewBody(): string
    {
        return <<<HTML
            <html><body>
            <div class="t3-filelist-container">
            <table id="typo3-filelist">
            <tbody>
            <tr data-filelist-meta-uid="{$this->exampleMetaUid}"><td>example.pdf</td><td><div class="btn-group"></div></td></tr>
            <tr data-filelist-meta-uid="{$this->imageMetaUid}"><td>image.jpg</td><td><div class="btn-group"></div></td></tr>
            <tr data-filelist-identifier="1:/user_upload/sub/"><td>sub</td><td><div class="btn-group"></div></td></tr>
            </tbody>
            </table>
            </div>
            </body></html>
            HTML;
    }

    private function buildRequest(?string $moduleIdentifier, ?string $folderIdentifier): ServerRequest
    {
        $request = (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withQueryParams(null === $folderIdentifier ? [] : ['id' => $folderIdentifier]);

        if (null !== $moduleIdentifier) {
            $request = $request->withAttribute('module', Module::createFromConfiguration($moduleIdentifier, ['path' => '/module/'.$moduleIdentifier]));
        }

        return $request;
    }
}
