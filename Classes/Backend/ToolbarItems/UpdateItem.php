<?php

namespace Xima\XimaTypo3ContentPlanner\Backend\ToolbarItems;

use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentUpdateDataProvider;

class UpdateItem implements ToolbarItemInterface
{
    public function __construct(private readonly ContentUpdateDataProvider $contentUpdateDataProvider, private readonly FrontendInterface $cache)
    {
    }

    /**
    * Checks whether the user has access to this toolbar item
    *
    * @return bool TRUE if user has access, FALSE if not
    */
    public function checkAccess(): bool
    {
        return VisibilityUtility::checkContentStatusVisibility() && $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['features']['relevantUpdates'];
    }

    /**
    * Render "item" part of this toolbar
    *
    * @return string Toolbar item HTML
    */
    public function getItem(): string
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName('EXT:' . Configuration::EXT_KEY
            . '/Resources/Private/Templates/Backend/ToolbarItems/UpdateItem.html'));
        return $view->assignMultiple([
            'count' => count($this->getRelevantUpdates()),
        ])->render();
    }

    /**
    * TRUE if this toolbar item has a collapsible drop down
    *
    * @return bool
    */
    public function hasDropDown(): bool
    {
        return true;
    }

    /**
    * Render "drop down" part of this toolbar
    *
    * @return string Drop down HTML
    */
    public function getDropDown(): string
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName('EXT:' . Configuration::EXT_KEY
            . '/Resources/Private/Templates/Backend/ToolbarItems/UpdateItemDropDown.html'));
        $view->setPartialRootPaths([
            GeneralUtility::getFileAbsFileName('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Partials/'),
        ]);
        return $view->assignMultiple([
            'data' => $this->getRelevantUpdates(),
        ])->render();
    }

    /**
    * Returns an array with additional attributes added to containing <li> tag of the item.
    *
    * Typical usages are additional css classes and data-* attributes, classes may be merged
    * with other classes needed by the framework. Do NOT set an id attribute here.
    *
    * array(
    *     'class' => 'my-class',
    *     'data-foo' => '42',
    * )
    *
    * @return array List item HTML attributes
    */
    public function getAdditionalAttributes(): array
    {
        return [];
    }

    /**
    * Returns an integer between 0 and 100 to determine
    * the position of this item relative to others
    *
    * By default, extensions should return 50 to be sorted between main core
    * items and other items that should be on the very right.
    *
    * @return int 0 .. 100
    */
    public function getIndex(): int
    {
        return 50;
    }

    private function getRelevantUpdates(): array
    {
        $cacheIdentifier = sha1('contentplanner_toolbarcache' . $GLOBALS['BE_USER']->user['uid']);
        $data = $this->cache->get($cacheIdentifier);
        if ($data === false) {
            // Store the data in cache
            $data = $this->contentUpdateDataProvider->fetchUpdateData(true, maxItems: 5);
            $this->cache->set($cacheIdentifier, $data, ['ximatypo3contentplanner_toolbarcache'], 300);
        }

        return $data ?: [];
    }
}
