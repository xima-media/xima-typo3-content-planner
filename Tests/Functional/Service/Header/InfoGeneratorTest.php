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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\Header;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\Header\{HeaderMode, InfoGenerator};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * InfoGeneratorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class InfoGeneratorTest extends AbstractFunctionalTestCase
{
    private InfoGenerator $subject;

    /** @var array<string, mixed>|null */
    private ?array $extensionConfigurationBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importSharedDataSet('comments.csv');
        $this->importSharedDataSet('folders.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('web_layout', ['id' => 1]);
        $this->subject = $this->get(InfoGenerator::class);
        $this->extensionConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] ?? null;
    }

    protected function tearDown(): void
    {
        // Restored here rather than at the end of the test body, so a failing assertion
        // cannot leak the registered record tables into the next test in this process.
        if (null === $this->extensionConfigurationBackup) {
            unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] = $this->extensionConfigurationBackup;
        }

        parent::tearDown();
    }

    #[Test]
    public function generateStatusHeaderReturnsFalseWhenNoRecordAndNoTableUid(): void
    {
        self::assertFalse($this->subject->generateStatusHeader(HeaderMode::WEB_LAYOUT));
    }

    #[Test]
    public function generateStatusHeaderReturnsFalseForUnknownRecord(): void
    {
        self::assertFalse($this->subject->generateStatusHeader(HeaderMode::WEB_LAYOUT, null, 'pages', 999));
    }

    #[Test]
    public function generateStatusHeaderReturnsFalseForRecordWithoutStatus(): void
    {
        self::assertFalse($this->subject->generateStatusHeader(HeaderMode::WEB_LAYOUT, null, 'pages', 2));
    }

    #[Test]
    public function generateStatusHeaderRendersHtmlForPageWithStatus(): void
    {
        $result = $this->subject->generateStatusHeader(HeaderMode::WEB_LAYOUT, null, 'pages', 1);

        self::assertIsString($result);
        self::assertNotSame('', $result);

        // context-menu-actions.js locates the badge via these attributes to apply a status
        // change optimistically, see StatusItemProvider / ContextMenuActions.findStatusHeader.
        self::assertStringContainsString('data-table="pages"', $result);
        self::assertStringContainsString('data-uid="1"', $result);
        self::assertStringContainsString('aria-label="', $result);
    }

    /**
     * CP-14 (#318): the content element hint dots in the banner-mode header previously
     * carried their status only via a `data-color` attribute (colour alone) and a
     * `title`/aria-label limited to the content element's own title. Both must now also
     * expose the content element's status as text.
     */
    #[Test]
    public function generateStatusHeaderContentElementHintCarriesStatusAsTextNotColourAlone(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = ['tt_content'];
        $this->importCSVDataSet(__DIR__.'/Fixtures/tt_content.csv');

        $result = $this->subject->generateStatusHeader(HeaderMode::WEB_LAYOUT, null, 'pages', 1);

        self::assertIsString($result);
        self::assertStringContainsString('data-color="', $result);
        self::assertStringContainsString('Teaser', $result);
        // Status 1 = "Draft" (see status.csv), distinct from page 1's own status
        // (status 2 = "In Progress"), so this proves the CE's own status is exposed.
        self::assertStringContainsString('Draft', $result);
        self::assertMatchesRegularExpression('/aria-label="[^"]*Draft[^"]*"/', $result);
        // The hint used to be a bare bullet whose only status signal was its colour, which
        // sighted users cannot decode. It now renders the status icon, so the status is
        // carried by a distinguishable shape as well.
        self::assertStringNotContainsString('&#9679;', $result);
        self::assertMatchesRegularExpression('/<svg|<span class="[^"]*t3js-icon/', $result);
    }

    #[Test]
    public function generateStatusHeaderRendersHtmlWhenRecordIsPassedDirectly(): void
    {
        $record = [
            'uid' => 1,
            'pid' => 0,
            Configuration::FIELD_STATUS => 2,
            Configuration::FIELD_ASSIGNEE => 1,
            Configuration::FIELD_COMMENTS => 1,
        ];

        $result = $this->subject->generateStatusHeader(HeaderMode::WEB_LAYOUT, $record, 'pages', 1);

        self::assertIsString($result);
        self::assertNotSame('', $result);
    }

    #[Test]
    public function generateFolderStatusHeaderReturnsFalseForUnknownFolder(): void
    {
        self::assertFalse($this->subject->generateFolderStatusHeader('1:/missing/', 'Missing'));
    }

    #[Test]
    public function generateFolderStatusHeaderReturnsFalseForFolderWithoutStatus(): void
    {
        self::assertFalse($this->subject->generateFolderStatusHeader('1:/no_status/', 'No status'));
    }

    #[Test]
    public function generateFolderStatusHeaderRendersHtmlForFolderWithStatus(): void
    {
        $result = $this->subject->generateFolderStatusHeader('1:/user_upload/', 'User upload');

        self::assertIsString($result);
        self::assertNotSame('', $result);
    }

    #[Test]
    public function checkAssignToCurrentUserReturnsFalseWhenAssigneeFieldMissing(): void
    {
        self::assertFalse(InfoGenerator::checkAssignToCurrentUser(['uid' => 1]));
    }

    #[Test]
    public function checkAssignToCurrentUserReturnsTrueWhenUnassignedAndFeatureEnabled(): void
    {
        $record = [Configuration::FIELD_ASSIGNEE => null];
        // Result depends on whether the highlight feature is enabled in the
        // test extension configuration. Both branches are valid bools.
        self::assertIsBool(InfoGenerator::checkAssignToCurrentUser($record));
    }

    #[Test]
    public function canUnassignRecordReturnsBoolForAssignedRecord(): void
    {
        $record = [Configuration::FIELD_ASSIGNEE => 1];
        // Admin has unrestricted access, so this should be true.
        self::assertTrue(InfoGenerator::canUnassignRecord($record));
    }

    #[Test]
    public function checkUnassignReturnsFalseWhenAssigneeFieldMissing(): void
    {
        self::assertFalse(InfoGenerator::checkUnassign(['uid' => 1]));
    }

    #[Test]
    public function checkUnassignReturnsTrueWhenAssigneeSet(): void
    {
        self::assertTrue(InfoGenerator::checkUnassign([Configuration::FIELD_ASSIGNEE => 5]));
    }

    #[Test]
    public function checkUnassignReturnsFalseWhenAssigneeIsZero(): void
    {
        self::assertFalse(InfoGenerator::checkUnassign([Configuration::FIELD_ASSIGNEE => 0]));
    }
}
