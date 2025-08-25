<?php

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Backend;

use Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Support\AcceptanceTester;
use Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Support\Helper\PageTree;

class ElementCest
{
    public function _before(AcceptanceTester $I): void
    {
        $I->loginAs('admin');
    }

    public function createElementAndSave(
        AcceptanceTester $I,
        PageTree $pageTree,
    ): void {
        $I->click('Page');
        $I->waitForElementVisible(PageTree::$treeSelector, 10);
        $pageTree->openPath(['Home', 'Projects']);
    }
}
