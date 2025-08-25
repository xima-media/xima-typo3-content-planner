<?php

namespace Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Backend;

use Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Support\AcceptanceTester;
use Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Support\Enums\Selectors;
use Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Support\Helper\PageTree;
use Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Support\Helper\ShadowDom;

class ElementCest
{
    public function _before(AcceptanceTester $I): void
    {
        $I->loginAs('admin');
    }

    public function createElementAndSave(
        AcceptanceTester $I,
        PageTree $pageTree,
    ): void
    {
        $I->click('Page');
        $I->waitForElementVisible(PageTree::$treeSelector);
        $I->wait(2);
        $pageTree->openPath(['Home', 'Projects']);
    }
}
