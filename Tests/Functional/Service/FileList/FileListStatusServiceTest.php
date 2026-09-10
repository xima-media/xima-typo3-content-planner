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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\FileList;

use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Service\FileList\FileListStatusService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * FileListStatusServiceTest.
 *
 * Repository calls behind this service (SysFileMetadataRepository::findByFolderWithStatus(),
 * FolderStatusRepository::findSubfoldersWithStatus()) resolve real FAL folder objects via
 * ResourceFactory and therefore require a physical local storage on disk. The existing
 * SysFileMetadataRepositoryTest/FolderStatusRepositoryTest suites sidestep this by only
 * covering the "unknown storage" empty-result branch; this test follows the same
 * convention for generateStatusStyles() and additionally exercises the private CSS-building
 * helpers directly via reflection to cover their formatting/escaping logic.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class FileListStatusServiceTest extends AbstractFunctionalTestCase
{
    private FileListStatusService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(FileListStatusService::class);
    }

    #[Test]
    public function generateStatusStylesReturnsEmptyStringForUnknownStorage(): void
    {
        self::assertSame('', $this->subject->generateStatusStyles('99:/missing/', false));
    }

    #[Test]
    public function generateStatusStylesReturnsEmptyStringForUnknownStorageInTilesView(): void
    {
        self::assertSame('', $this->subject->generateStatusStyles('99:/missing/', true));
    }

    #[Test]
    public function buildFileCssRuleGeneratesTableRowSelectorForTableView(): void
    {
        $status = $this->createStatus('blue');

        $css = $this->invokeBuildFileCssRule(42, $status, false);

        self::assertSame('tr[data-filelist-meta-uid="42"] > td { background-color: rgba(100,187,200, 0.5); }', $css);
    }

    #[Test]
    public function buildFileCssRuleGeneratesTileSelectorForTilesView(): void
    {
        $status = $this->createStatus('green');

        $css = $this->invokeBuildFileCssRule(7, $status, true);

        self::assertSame('.resource-tile[data-filelist-meta-uid="7"] { background-color: rgba(106,158,113, 0.5); }', $css);
    }

    #[Test]
    public function buildFolderCssRuleGeneratesTableRowSelectorForTableView(): void
    {
        $status = $this->createStatus('yellow');

        $css = $this->invokeBuildFolderCssRule('1:/user_upload/sub/', $status, false);

        self::assertSame('tr[data-filelist-identifier="1:/user_upload/sub/"] > td { background-color: rgba(255,205,117, 0.5); }', $css);
    }

    #[Test]
    public function buildFolderCssRuleGeneratesTileSelectorForTilesView(): void
    {
        $status = $this->createStatus('red');

        $css = $this->invokeBuildFolderCssRule('1:/user_upload/sub/', $status, true);

        self::assertSame('.resource-tile[data-filelist-identifier="1:/user_upload/sub/"] { background-color: rgba(250,136,147, 0.5); }', $css);
    }

    #[Test]
    public function buildFolderCssRuleEscapesFolderIdentifier(): void
    {
        $status = $this->createStatus('purple');

        $css = $this->invokeBuildFolderCssRule('1:/"quoted"/', $status, false);

        self::assertStringContainsString('data-filelist-identifier="1:/&quot;quoted&quot;/"', $css);
        self::assertStringNotContainsString('data-filelist-identifier="1:/"quoted"/"', $css);
    }

    private function createStatus(string $color): Status
    {
        return new Status(uid: 0, title: '', icon: '', color: $color, isDefault: false);
    }

    private function invokeBuildFileCssRule(int $metaUid, Status $status, bool $isTilesView): string
    {
        $method = new ReflectionMethod($this->subject, 'buildFileCssRule');
        $method->setAccessible(true);

        return $method->invoke($this->subject, $metaUid, $status, $isTilesView);
    }

    private function invokeBuildFolderCssRule(string $combinedIdentifier, Status $status, bool $isTilesView): string
    {
        $method = new ReflectionMethod($this->subject, 'buildFolderCssRule');
        $method->setAccessible(true);

        return $method->invoke($this->subject, $combinedIdentifier, $status, $isTilesView);
    }
}
