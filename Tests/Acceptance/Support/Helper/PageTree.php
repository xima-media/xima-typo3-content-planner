<?php

declare(strict_types=1);


namespace Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Support\Helper;

use Facebook\WebDriver;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework;
use Xima\XimaTypo3ContentPlanner\Tests\Acceptance\Support\AcceptanceTester;

final class PageTree extends TestingFramework\Core\Acceptance\Helper\AbstractPageTree
{
    /**
     * @var AcceptanceTester
     */
    protected $tester;

    // Selectors
    public static $treeSelector = '#typo3-pagetree-treeContainer';
    public static $treeItemSelector = '.nodes-list > [role="treeitem"]';
    public static $treeItemAnchorSelector = '.node-contentlabel';

    private readonly Typo3Version $typo3Version;

    public function __construct(AcceptanceTester $tester)
    {
        $this->tester = $tester;
        $this->typo3Version = new Typo3Version();
    }

    /**
     * @param non-empty-list<non-empty-string> $path
     */
    public function openContextMenu(array $path): void
    {
        $I = $this->tester;

        $I->waitForElementVisible(self::$pageTreeFrameSelector);

        $context = $this->getPageTreeElement();

        foreach ($path as $pageName) {
            $context = $this->ensureTreeNodeIsOpen($pageName, $context);
        }

        if ($this->typo3Version->getMajorVersion() >= 13) {
            $contextMenu = $context;
        } else {
            // @todo Remove once support for TYPO3 v12 is dropped
            $contextMenu = $context->findElement(WebDriver\WebDriverBy::cssSelector(self::$treeItemAnchorSelector));
        }

        $I->executeInSelenium(function (WebDriver\Remote\RemoteWebDriver $webDriver) use ($contextMenu): void {
            $webDriver->getMouse()->contextClick($contextMenu->getCoordinates());
        });
        $I->waitForElementVisible(Tests\Acceptance\Support\Enums\Selectors::ContextMenuGroup->value);
    }

    /**
     * @param list<non-empty-string> $path
     */
    public function selectInContextMenu(array $path): void
    {
        $I = $this->tester;

        $remaining = \count($path);
        $contextMenuIdentifier = $this->usesNewContextMenuIdentifiers()
            ? '[data-contextmenu-parent="root"]'
            : '#contentMenu0';

        foreach ($path as $depth => $selector) {
            --$remaining;

            $I->waitForElementVisible($contextMenuIdentifier, 5);
            $I->executeInSelenium(
                function (WebDriver\Remote\RemoteWebDriver $webDriver) use (&$contextMenuIdentifier, $remaining, $selector): void {
                    $contextMenu = $webDriver->findElement(WebDriver\WebDriverBy::cssSelector($contextMenuIdentifier));
                    $items = $contextMenu->findElements(WebDriver\WebDriverBy::tagName('li'));

                    foreach ($items as $item) {
                        if ($item->getText() === $selector) {
                            $webDriver->getMouse()->click($item->getCoordinates());

                            if ($this->usesNewContextMenuIdentifiers() && $remaining > 0) {
                                $button = $item->findElement(WebDriver\WebDriverBy::tagName('button'));
                                $contextMenuIdentifier = \sprintf(
                                    '[data-contextmenu-parent="%s"]',
                                    $button->getAttribute('data-contextmenu-id'),
                                );
                            }

                            break;
                        }
                    }
                },
            );

            if (!$this->usesNewContextMenuIdentifiers()) {
                $contextMenuIdentifier = \sprintf('#contentMenu%d', $depth + 1);
            }
        }
    }

    protected function ensureTreeNodeIsOpen(
        string $nodeText,
        WebDriver\Remote\RemoteWebElement $context,
    ): WebDriver\Remote\RemoteWebElement {
        // @todo Remove once support for TYPO3 v12 is dropped
        if ($this->typo3Version->getMajorVersion() < 13) {
            return parent::ensureTreeNodeIsOpen($nodeText, $context);
        }

        // @todo Remove once TF properly handles new page tree rendering
        $I = $this->tester;
        $I->see($nodeText, 'div.nodes-list > .node');

        /** @var WebDriver\Remote\RemoteWebElement $context */
        $context = $I->executeInSelenium(
            static fn() => $context->findElement(
                WebDriver\WebDriverBy::xpath('//*[text()=\'' . $nodeText . '\']/../../..'),
            ),
        );

        if ($context->getAttribute('aria-expanded') === '1') {
            return $context;
        }

        try {
            $context->findElement(WebDriver\WebDriverBy::cssSelector('.node-toggle'))->click();
        } catch (WebDriver\Exception\NoSuchElementException) {
            // element not found so it may be already opened...
        } catch (WebDriver\Exception\ElementNotInteractableException) {
            // another possible exception if the chevron isn't there ... depends on facebook driver version
        }

        return $context;
    }

    /**
     * @see https://review.typo3.org/c/Packages/TYPO3.CMS/+/87887
     */
    public function usesNewContextMenuIdentifiers(): bool
    {
        return \version_compare($this->typo3Version->getVersion(), '13.4.5', '>=');
    }
}
