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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\ContentModifier;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Module\Module;
use TYPO3\CMS\Core\Http\ServerRequest;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\DockedHeaderModifier;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * DockedHeaderModifierTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DockedHeaderModifierTest extends AbstractFunctionalTestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $extensionConfigurationBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extensionConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] ?? null;
        $this->loginBackendUser();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_HEADER_DISPLAY_MODE] = Configuration::HEADER_DISPLAY_MODE_DOCKED;
        // InfoGenerator's URL generation reads $GLOBALS['TYPO3_REQUEST'], not the request
        // instance passed to modify() below.
        $this->setUpBackendRequest('web_layout', ['id' => 1]);
    }

    protected function tearDown(): void
    {
        if (null === $this->extensionConfigurationBackup) {
            unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] = $this->extensionConfigurationBackup;
        }

        parent::tearDown();
    }

    #[Test]
    public function modifyInsertsHeaderBeforeV14StyleButtonRow(): void
    {
        // v14: the button row itself also carries the `.module-docheader` class (see the
        // "v13/v14 doc header markup differs" project note).
        $body = '<div class="module-docheader module-docheader-navigation t3js-module-docheader-navigation">nav</div>'
            .'<div class="module-docheader module-docheader-buttons t3js-module-docheader-buttons">buttons</div>';
        $response = $this->modify($body, 1);
        $content = (string) $response->getBody();

        self::assertStringContainsString('content-planner-header--compact', $content);

        $headerPosition = strpos($content, 'content-planner-header--compact');
        $buttonRowPosition = strpos($content, 'module-docheader-buttons');
        self::assertNotFalse($headerPosition);
        self::assertNotFalse($buttonRowPosition);
        self::assertLessThan($buttonRowPosition, $headerPosition);
    }

    #[Test]
    public function modifyInsertsHeaderBeforeV13StyleButtonRow(): void
    {
        // v13: a single outer `.module-docheader` wraps two `.module-docheader-bar` rows.
        $body = '<div class="module-docheader t3js-module-docheader">'
            .'<div class="module-docheader-bar module-docheader-bar-navigation">nav</div>'
            .'<div class="module-docheader-bar module-docheader-bar-buttons t3js-module-docheader-bar-buttons">buttons</div>'
            .'</div>';
        $response = $this->modify($body, 1);
        $content = (string) $response->getBody();

        self::assertStringContainsString('content-planner-header--compact', $content);

        $headerPosition = strpos($content, 'content-planner-header--compact');
        $buttonRowPosition = strpos($content, 'module-docheader-bar-buttons');
        self::assertNotFalse($headerPosition);
        self::assertNotFalse($buttonRowPosition);
        self::assertLessThan($buttonRowPosition, $headerPosition);
    }

    #[Test]
    public function modifyLeavesResponseUntouchedWhenPageHasNoStatus(): void
    {
        $body = '<div class="module-docheader module-docheader-buttons">buttons</div>';
        $response = $this->modify($body, 2);

        self::assertSame($body, (string) $response->getBody());
    }

    #[Test]
    public function modifyLeavesResponseUntouchedWhenNoPageIdInRequest(): void
    {
        $modifier = $this->get(DockedHeaderModifier::class);
        $body = '<div class="module-docheader module-docheader-buttons">buttons</div>';
        $request = (new ServerRequest('https://example.com/typo3/index.php', 'GET'))->withQueryParams([]);

        $response = $modifier->modify($request, $this->buildResponseHandler($body));

        self::assertSame($body, (string) $response->getBody());
    }

    #[Test]
    public function isRelevantReturnsTrueInDockedModeForPageLayoutModule(): void
    {
        self::assertTrue($this->get(DockedHeaderModifier::class)->isRelevant($this->buildPageLayoutRequest()));
    }

    #[Test]
    public function isRelevantReturnsFalseInChipDisplayMode(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_HEADER_DISPLAY_MODE] = Configuration::HEADER_DISPLAY_MODE_CHIP;

        self::assertFalse($this->get(DockedHeaderModifier::class)->isRelevant($this->buildPageLayoutRequest()));
    }

    #[Test]
    public function isRelevantReturnsFalseWithoutModuleAttribute(): void
    {
        $request = $this->setUpBackendRequest('web_layout', ['id' => 1]);

        self::assertFalse($this->get(DockedHeaderModifier::class)->isRelevant($request));
    }

    private function modify(string $body, int $pageId): ResponseInterface
    {
        $modifier = $this->get(DockedHeaderModifier::class);
        $request = (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withQueryParams(['id' => (string) $pageId]);

        return $modifier->modify($request, $this->buildResponseHandler($body));
    }

    private function buildPageLayoutRequest(): ServerRequest
    {
        $request = $this->setUpBackendRequest('web_layout', ['id' => 1]);

        return $request->withAttribute('module', Module::createFromConfiguration('web_layout', ['path' => '/module/web_layout']));
    }
}
