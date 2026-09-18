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
use TYPO3\CMS\Backend\Module\Module;
use TYPO3\CMS\Core\Http\ServerRequest;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\ContentElementHeaderModifier;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ContentElementHeaderModifierTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentElementHeaderModifierTest extends AbstractFunctionalTestCase
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
    public function modifyInsertsHeaderBeforeCoreHeaderRowForMatchingContentElement(): void
    {
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/tt_content.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = ['tt_content'];

        $modifier = $this->get(ContentElementHeaderModifier::class);
        $request = (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withQueryParams(['id' => '1']);

        // A minimal stand-in for core's actual PageLayout/Record.fluid.html markup (see
        // typo3/cms-backend Resources/Private/Partials/PageLayout/Record.html), which is what
        // ContentElementHeaderModifier anchors on via the unique element id.
        $body = '<div class="t3-page-ce" id="element-tt_content-1" data-table="tt_content" data-uid="1">'
            .'<div class="t3-page-ce-element t3-page-ce-dragitem">'
            .'<div class="t3-page-ce-header t3js-page-ce-header">Bullet List</div>'
            .'<div class="t3-page-ce-body">preview content</div>'
            .'</div></div>';
        $handler = $this->buildResponseHandler($body);

        $response = $modifier->modify($request, $handler);
        $content = (string) $response->getBody();

        self::assertStringContainsString('content-planner-header--compact', $content);
        // Status 1 = "Draft" (see status.csv).
        self::assertStringContainsString('Draft', $content);
        self::assertStringContainsString('data-table="tt_content"', $content);

        // The injected header must sit BEFORE core's own title row, not inside the preview body.
        $headerPosition = strpos($content, 'content-planner-header--compact');
        $coreHeaderRowPosition = strpos($content, 't3-page-ce-header t3js-page-ce-header');
        self::assertNotFalse($headerPosition);
        self::assertNotFalse($coreHeaderRowPosition);
        self::assertLessThan($coreHeaderRowPosition, $headerPosition);
    }

    #[Test]
    public function modifyLeavesResponseUntouchedWhenContentElementHasNoStatus(): void
    {
        $this->importSharedDataSet('status.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = ['tt_content'];

        $modifier = $this->get(ContentElementHeaderModifier::class);
        $request = (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withQueryParams(['id' => '1']);

        $body = '<html></html>';
        $handler = $this->buildResponseHandler($body);

        $response = $modifier->modify($request, $handler);

        self::assertSame($body, (string) $response->getBody());
    }

    #[Test]
    public function isRelevantReturnsTrueInChipDisplayModeByDefault(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = ['tt_content'];
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_HEADER_DISPLAY_MODE]);

        $modifier = $this->get(ContentElementHeaderModifier::class);
        $request = $this->buildPageLayoutRequest();

        self::assertTrue($modifier->isRelevant($request));
    }

    #[Test]
    public function isRelevantReturnsFalseInBannerDisplayMode(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = ['tt_content'];
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_HEADER_DISPLAY_MODE] = Configuration::HEADER_DISPLAY_MODE_BANNER;

        $modifier = $this->get(ContentElementHeaderModifier::class);
        $request = $this->buildPageLayoutRequest();

        self::assertFalse($modifier->isRelevant($request));

        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_HEADER_DISPLAY_MODE]);
    }

    #[Test]
    public function isRelevantReturnsFalseWhenContentElementSupportIsNotRegistered(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = [];

        $modifier = $this->get(ContentElementHeaderModifier::class);
        $request = $this->buildPageLayoutRequest();

        self::assertFalse($modifier->isRelevant($request));
    }

    private function buildPageLayoutRequest(): ServerRequest
    {
        $request = $this->setUpBackendRequest('web_layout', ['id' => 1]);

        return $request->withAttribute('module', Module::createFromConfiguration('web_layout', ['path' => '/module/web_layout']));
    }
}
