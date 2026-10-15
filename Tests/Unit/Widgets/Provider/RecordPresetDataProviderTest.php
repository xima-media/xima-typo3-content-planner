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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Widgets\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use Xima\XimaTypo3ContentPlanner\Domain\Model\RecordPreset;
use Xima\XimaTypo3ContentPlanner\Service\RecordPresetService;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\RecordPresetDataProvider;

/**
 * RecordPresetDataProviderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordPresetDataProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
    }

    #[Test]
    public function getItemsCountsEveryPresetForTheCurrentBackendUser(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('getUserId')->willReturn(7);
        $GLOBALS['BE_USER'] = $backendUser;

        $service = $this->createMock(RecordPresetService::class);
        $service->method('count')->willReturnCallback(static fn (RecordPreset $preset, int $userId): array => match ($preset) {
            RecordPreset::Assignee => ['count' => $userId, 'countLabel' => (string) $userId],
            RecordPreset::Todo => ['count' => 2, 'countLabel' => '2'],
            RecordPreset::OpenComments => ['count' => 1, 'countLabel' => '1'],
        });

        $items = (new RecordPresetDataProvider($service))->getItems();

        self::assertSame(['assignee', 'todo', 'openComments'], array_keys($items));
        self::assertSame(['count' => 7, 'countLabel' => '7'], $items['assignee']);
        self::assertSame(['count' => 2, 'countLabel' => '2'], $items['todo']);
        self::assertSame(['count' => 1, 'countLabel' => '1'], $items['openComments']);
    }
}
