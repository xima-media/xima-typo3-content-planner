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
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Module\Module;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\WebListModifier;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * WebListModifierTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WebListModifierTest extends AbstractFunctionalTestCase
{
    private const ORIGINAL_BODY = '<html><body><typo3-backend-editable-page-title>Home</typo3-backend-editable-page-title></body></html>';

    private WebListModifier $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(WebListModifier::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_WEB_LIST_HEADER_INFO]);
        parent::tearDown();
    }

    #[Test]
    public function isRelevantReturnsFalseWhenWebListFeatureIsDisabled(): void
    {
        // FEATURE_RECORD_EDIT_HEADER_INFO is a distinct, independently toggleable setting
        // (see ext_conf_template.txt) and must have no bearing on the web list modifier.
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_RECORD_EDIT_HEADER_INFO] = 1;

        self::assertFalse($this->subject->isRelevant($this->buildRequest('web_list', 1)));
    }

    #[Test]
    public function isRelevantReturnsTrueWhenWebListFeatureIsEnabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_WEB_LIST_HEADER_INFO] = 1;

        self::assertTrue($this->subject->isRelevant($this->buildRequest('web_list', 1)));
    }

    #[Test]
    public function modifyAppendsStatusHeaderAfterPageTitleWhenWebListFeatureIsEnabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_WEB_LIST_HEADER_INFO] = 1;
        $this->setUpBackendRequest('web_list', ['id' => '1']);

        $response = $this->subject->modify($this->buildRequest('web_list', 1), $this->buildHandler());
        $body = (string) $response->getBody();

        self::assertNotSame(self::ORIGINAL_BODY, $body);
        self::assertStringContainsString('<typo3-backend-editable-page-title>Home</typo3-backend-editable-page-title>', $body);
    }

    private function buildRequest(string $moduleIdentifier, int $pageId): ServerRequest
    {
        return (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('module', Module::createFromConfiguration($moduleIdentifier, ['path' => '/module/'.$moduleIdentifier]))
            ->withQueryParams(['id' => (string) $pageId]);
    }

    private function buildHandler(): RequestHandlerInterface
    {
        return new class(self::ORIGINAL_BODY) implements RequestHandlerInterface {
            public function __construct(private readonly string $body) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = new Response('php://temp');
                $response->getBody()->write($this->body);

                return $response;
            }
        };
    }
}
