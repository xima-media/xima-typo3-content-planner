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
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\WebListModifier;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

use function strlen;

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
        $this->setUpBackendRequest('web_list', ['id' => '1']);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_RECORD_EDIT_HEADER_INFO] = 1;
        $this->subject = $this->get(WebListModifier::class);
    }

    #[Test]
    public function isRelevantReturnsFalseWhenNotBackendRequest(): void
    {
        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('module', Module::createFromConfiguration('web_list', ['path' => '/module/web_list']))
            ->withQueryParams(['id' => '1']);

        self::assertFalse($this->subject->isRelevant($request));
    }

    #[Test]
    public function isRelevantReturnsFalseWhenFeatureDisabled(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_RECORD_EDIT_HEADER_INFO]);

        self::assertFalse($this->subject->isRelevant($this->buildRequest('web_list', 1)));
    }

    #[Test]
    public function isRelevantReturnsFalseWhenModuleAttributeMissing(): void
    {
        self::assertFalse($this->subject->isRelevant($this->buildRequest(null, 1)));
    }

    #[Test]
    public function isRelevantReturnsFalseForNonRecordListRoute(): void
    {
        self::assertFalse($this->subject->isRelevant($this->buildRequest('web_info', 1)));
    }

    #[Test]
    public function isRelevantReturnsTrueForRecordListRoute(): void
    {
        self::assertTrue($this->subject->isRelevant($this->buildRequest('web_list', 1)));
    }

    #[Test]
    public function modifyReturnsResponseUnchangedWhenBodyIsEmpty(): void
    {
        $response = $this->subject->modify($this->buildRequest('web_list', 1), $this->buildResponseHandler(''));

        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function modifyReturnsResponseUnchangedWhenIdQueryParamMissing(): void
    {
        $response = $this->subject->modify($this->buildRequest('web_list', null), $this->buildResponseHandler(self::ORIGINAL_BODY));

        self::assertSame(self::ORIGINAL_BODY, (string) $response->getBody());
    }

    #[Test]
    public function modifyReturnsResponseUnchangedWhenPageHasNoStatus(): void
    {
        $response = $this->subject->modify($this->buildRequest('web_list', 2), $this->buildResponseHandler(self::ORIGINAL_BODY));

        self::assertSame(self::ORIGINAL_BODY, (string) $response->getBody());
    }

    #[Test]
    public function modifyAppendsStatusHeaderAfterPageTitleForPageWithStatus(): void
    {
        $response = $this->subject->modify($this->buildRequest('web_list', 1), $this->buildResponseHandler(self::ORIGINAL_BODY));
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['kept'], $response->getHeader('X-Custom-Header'));
        self::assertNotSame(self::ORIGINAL_BODY, $body);
        self::assertStringContainsString('<typo3-backend-editable-page-title>Home</typo3-backend-editable-page-title>', $body);
        // The additional markup is appended right after the closing tag, so the closing
        // tag's position must be unchanged while the body grew.
        self::assertSame(
            strpos(self::ORIGINAL_BODY, '</typo3-backend-editable-page-title>'),
            strpos($body, '</typo3-backend-editable-page-title>'),
        );
        self::assertGreaterThan(strlen(self::ORIGINAL_BODY), strlen($body));
    }

    private function buildRequest(?string $moduleIdentifier, ?int $pageId): ServerRequest
    {
        $request = (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withQueryParams(null === $pageId ? [] : ['id' => (string) $pageId]);

        if (null !== $moduleIdentifier) {
            $request = $request->withAttribute('module', Module::createFromConfiguration($moduleIdentifier, ['path' => '/module/'.$moduleIdentifier]));
        }

        return $request;
    }
}
