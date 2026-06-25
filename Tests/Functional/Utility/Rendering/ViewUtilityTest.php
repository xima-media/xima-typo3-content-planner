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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Utility\Rendering;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\ViewUtility;

/**
 * ViewUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ViewUtilityTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->setUpBackendRequest();
    }

    #[Test]
    public function renderReturnsStringForExistingTemplate(): void
    {
        $result = ViewUtility::render('Backend/Header/HeaderInfo', [
            'mode' => 'web_layout',
            'data' => ['uid' => 1],
            'table' => 'pages',
            'pid' => 1,
            'status' => ['title' => 'Draft', 'color' => 'blue', 'icon' => 'flag-blue'],
            'assignee' => ['username' => '', 'assignedToCurrentUser' => false, 'assignToCurrentUser' => false, 'unassign' => null],
            'comments' => ['items' => [], 'count' => 0, 'newCommentUri' => '', 'editUri' => '', 'todoResolved' => 0, 'todoTotal' => 0],
            'contentElements' => null,
            'userid' => 1,
        ]);

        self::assertIsString($result);
    }
}
