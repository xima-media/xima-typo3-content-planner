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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Backend\ContextMenu\ItemProviders;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Backend\ContextMenu\ItemProviders\StatusItemProvider;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * StatusItemProviderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusItemProviderTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport'] = 1;
        $this->importSharedDataSet('status.csv');
        $this->importSharedDataSet('folders.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/sys_file_metadata.csv');
    }

    #[Test]
    public function canHandleReturnsFalseForEmptyIdentifier(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('pages', '');

        self::assertFalse($provider->canHandle());
    }

    #[Test]
    public function canHandleReturnsTrueForRegisteredTableWithIdentifier(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('pages', '1');

        self::assertTrue($provider->canHandle());
    }

    #[Test]
    public function canHandleReturnsFalseForUnregisteredTable(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('tt_content', '5');

        self::assertFalse($provider->canHandle());
    }

    #[Test]
    public function canHandleReturnsFalseForSysFileWhenFilelistSupportIsDisabled(): void
    {
        $this->loginBackendUser(1);
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport']);

        $provider = $this->createProvider('sys_file', '1:/user_upload/');

        self::assertFalse($provider->canHandle());
    }

    #[Test]
    public function canHandleReturnsTrueForSysFileWhenFilelistSupportIsEnabled(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('sys_file', '1:/user_upload/');

        self::assertTrue($provider->canHandle());
    }

    #[Test]
    public function getPriorityReturnsSeventeen(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('pages', '1');

        self::assertSame(17, $provider->getPriority());
    }

    #[Test]
    public function addItemsReturnsItemsUnchangedWhenUserLacksVisibilityPermission(): void
    {
        $this->loginBackendUser(2);

        $provider = $this->createProvider('pages', '1');
        $items = ['existing' => ['type' => 'item']];

        self::assertSame($items, $provider->addItems($items));
    }

    #[Test]
    public function addItemsReturnsItemsUnchangedWhenEffectiveRecordCannotBeResolved(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('sys_file', '999');
        $items = ['existing' => ['type' => 'item']];

        self::assertSame($items, $provider->addItems($items));
    }

    #[Test]
    public function addItemsAddsStatusSubmenuForPageWithStatus(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('pages', '1');
        $result = $provider->addItems([]);

        self::assertArrayHasKey('wrap', $result);
        $childItems = $result['wrap']['childItems'];
        self::assertArrayHasKey('reset', $childItems);
        self::assertArrayHasKey('assignee', $childItems);
        self::assertArrayHasKey('comments', $childItems);
        // Status 2 (current) is excluded from the selection, 1 and 3 remain selectable.
        self::assertArrayHasKey('1', $childItems);
        self::assertArrayHasKey('3', $childItems);
        self::assertArrayNotHasKey('2', $childItems);

        $attributes = $childItems['1']['additionalAttributes'];
        self::assertSame('pages', $attributes['data-effective-table']);
        self::assertSame(1, $attributes['data-effective-uid']);
        self::assertNotSame('', $attributes['data-uri']);
        self::assertNotSame('', $attributes['data-edit-uri']);

        self::assertSame(1, $childItems['assignee']['additionalAttributes']['data-current-assignee']);
    }

    #[Test]
    public function addItemsInsertsAfterInfoItemWhenPresent(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('pages', '1');
        $items = ['info' => ['type' => 'item'], 'trailing' => ['type' => 'item']];
        $result = $provider->addItems($items);

        $keys = array_keys($result);
        self::assertSame('info', $keys[0]);
        self::assertSame('wrap', $keys[1]);
        self::assertSame('trailing', $keys[array_key_last($keys)]);
    }

    #[Test]
    public function addItemsAddsFolderSpecificAttributesForExistingFolder(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('sys_file', '1:/user_upload/');
        $result = $provider->addItems([]);

        $childItems = $result['wrap']['childItems'];
        $attributes = $childItems['assignee']['additionalAttributes'];

        self::assertSame('1:/user_upload/', $attributes['data-folder-identifier']);
        self::assertNotSame('', $attributes['data-folder-status-url']);
        self::assertNotSame('', $attributes['data-uri']);
        self::assertNotSame('', $attributes['data-edit-uri']);
    }

    #[Test]
    public function addItemsOmitsRecordUrlsForFolderWithoutExistingStatusRecord(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('sys_file', '1:/brand_new_folder/');
        $result = $provider->addItems([]);

        // A folder without an existing status record has no folder record and no current
        // status, so ContextMenuSelectionService only offers the plain status-change entries
        // (no reset/assignee/comments section, since those require an existing record).
        $childItems = $result['wrap']['childItems'];
        self::assertArrayNotHasKey('assignee', $childItems);
        self::assertArrayNotHasKey('reset', $childItems);

        $attributes = $childItems[2]['additionalAttributes'];
        self::assertSame('1:/brand_new_folder/', $attributes['data-folder-identifier']);
        self::assertArrayNotHasKey('data-uri', $attributes);
        self::assertArrayNotHasKey('data-edit-uri', $attributes);
    }

    #[Test]
    public function addItemsResolvesFileByNumericIdentifier(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('sys_file', '1');
        $result = $provider->addItems([]);

        $attributes = $result['wrap']['childItems']['assignee']['additionalAttributes'];
        self::assertSame('sys_file_metadata', $attributes['data-effective-table']);
        self::assertSame(1, $attributes['data-effective-uid']);
    }

    #[Test]
    public function addItemsResolvesFileByCombinedIdentifier(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('sys_file', '1:/user_upload/example.pdf');
        $result = $provider->addItems([]);

        $attributes = $result['wrap']['childItems']['assignee']['additionalAttributes'];
        self::assertSame('sys_file_metadata', $attributes['data-effective-table']);
        self::assertSame(1, $attributes['data-effective-uid']);
    }

    #[Test]
    public function addItemsResolvesFileByPathIdentifier(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('sys_file', '/user_upload/example.pdf');
        $result = $provider->addItems([]);

        $attributes = $result['wrap']['childItems']['assignee']['additionalAttributes'];
        self::assertSame('sys_file_metadata', $attributes['data-effective-table']);
        self::assertSame(1, $attributes['data-effective-uid']);
    }

    #[Test]
    public function addItemsReturnsItemsUnchangedWhenCombinedIdentifierHasEmptyPath(): void
    {
        $this->loginBackendUser(1);

        $provider = $this->createProvider('sys_file', '1:');
        $items = ['existing' => ['type' => 'item']];

        self::assertSame($items, $provider->addItems($items));
    }

    #[Test]
    public function addItemsRespectsDisabledItemsFromBackendUserTsConfig(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups.csv');
        $this->loginBackendUser(21);

        $provider = $this->createProvider('pages', '1');
        $result = $provider->addItems([]);

        $childItems = $result['wrap']['childItems'];
        self::assertArrayNotHasKey('reset', $childItems);
        self::assertArrayHasKey('1', $childItems);
        self::assertArrayHasKey('assignee', $childItems);
    }

    private function createProvider(string $table, string $identifier, string $context = ''): StatusItemProvider
    {
        $this->setUpBackendRequest('record_edit', ['id' => 1]);
        $provider = $this->get(StatusItemProvider::class);
        $provider->setContext($table, $identifier, $context);

        return $provider;
    }
}
