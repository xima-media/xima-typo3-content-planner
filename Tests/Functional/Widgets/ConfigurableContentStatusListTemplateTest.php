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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Widgets;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\ViewUtility;

/**
 * ConfigurableContentStatusListTemplateTest.
 *
 * The widget class itself is v14-only and cannot run against the v13 test instance, so its
 * template contract is covered here directly.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ConfigurableContentStatusListTemplateTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->setUpBackendRequest('dashboard', []);
    }

    #[Test]
    public function todoModeRendersResolvedAndTotalTodoCounts(): void
    {
        $content = $this->renderTemplate([
            'mode' => 'todo',
            'todo' => ['resolved' => 1, 'total' => 3],
        ]);

        self::assertStringNotContainsString('could not translate', $content);
        self::assertStringContainsString('1 of 3', $content);
    }

    #[Test]
    public function currentUserAssigneeFilterRendersAssignedCount(): void
    {
        $content = $this->renderTemplate(['assignedToMeCount' => 4]);

        self::assertStringNotContainsString('%1$d', $content);
        self::assertStringContainsString('4 content planner records', $content);
    }

    #[Test]
    public function commentLinksCarryTheClassTheCommentsModalBindsTo(): void
    {
        $content = $this->renderTemplate([
            'items' => [[
                'link' => '/typo3/record/edit',
                'comments' => '2',
                'data' => ['tablename' => 'pages', 'uid' => 1, 'tx_ximatypo3contentplanner_comments' => 2],
            ]],
        ]);

        self::assertStringContainsString('class="content-planner-link--comments"', $content);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function renderTemplate(array $arguments): string
    {
        return ViewUtility::render('Backend/Widgets/ConfigurableContentStatusList', [
            'items' => [['uid' => 1, 'table' => 'pages', 'title' => 'Home', 'site' => '']],
            'mode' => 'status',
            'todo' => false,
            'assignedToMeCount' => null,
            'hasSite' => false,
            ...$arguments,
        ]);
    }
}
