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
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\RecordEditModifier;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * RecordEditModifierTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordEditModifierTest extends AbstractFunctionalTestCase
{
    private const ORIGINAL_BODY = '<html><body><div class="typo3-TCEforms">form</div></body></html>';

    private RecordEditModifier $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('record_edit', ['edit' => ['pages' => ['1' => 'edit']]]);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_RECORD_EDIT_HEADER_INFO] = 1;
        $this->subject = $this->get(RecordEditModifier::class);
    }

    #[Test]
    public function isRelevantReturnsFalseWhenNotBackendRequest(): void
    {
        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withQueryParams(['edit' => ['pages' => ['1' => 'edit']]]);

        self::assertFalse($this->subject->isRelevant($request));
    }

    #[Test]
    public function isRelevantReturnsFalseWhenFeatureDisabled(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_RECORD_EDIT_HEADER_INFO]);

        self::assertFalse($this->subject->isRelevant($this->buildEditRequest('pages', 1)));
    }

    #[Test]
    public function isRelevantReturnsFalseWhenEditQueryParamMissing(): void
    {
        $request = (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withQueryParams([]);

        self::assertFalse($this->subject->isRelevant($request));
    }

    #[Test]
    public function isRelevantReturnsFalseForUnregisteredTable(): void
    {
        self::assertFalse($this->subject->isRelevant($this->buildEditRequest('be_users', 1)));
    }

    #[Test]
    public function isRelevantReturnsTrueForRegisteredTable(): void
    {
        self::assertTrue($this->subject->isRelevant($this->buildEditRequest('pages', 1)));
    }

    #[Test]
    public function modifyReturnsResponseUnchangedWhenBodyIsEmpty(): void
    {
        $response = $this->subject->modify($this->buildEditRequest('pages', 1), $this->buildResponseHandler(''));

        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function modifyReturnsResponseUnchangedWhenRecordHasNoStatus(): void
    {
        $handler = $this->buildResponseHandler(self::ORIGINAL_BODY);
        $response = $this->subject->modify($this->buildEditRequest('pages', 2), $handler);

        self::assertSame(self::ORIGINAL_BODY, (string) $response->getBody());
    }

    #[Test]
    public function modifyInjectsStatusHeaderBeforeTceformsForRecordWithStatus(): void
    {
        $handler = $this->buildResponseHandler(self::ORIGINAL_BODY);
        $response = $this->subject->modify($this->buildEditRequest('pages', 1), $handler);
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['kept'], $response->getHeader('X-Custom-Header'));
        self::assertNotSame(self::ORIGINAL_BODY, $body);
        self::assertStringContainsString('<div class="typo3-TCEforms">form</div>', $body);
        // The original markup places the TCEforms div right at offset 12 ("<html><body>");
        // a greater offset proves the status header was actually inserted before it.
        self::assertGreaterThan(12, strpos($body, '<div class="typo3-TCEforms">'));
    }

    #[Test]
    public function modifyResolvesUidFromArrayShapedEditParameter(): void
    {
        // Some backend links use edit[table][uid][uid]=edit instead of edit[table][uid]=edit.
        $request = (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withQueryParams(['edit' => ['pages' => ['1' => ['1' => 'edit']]]]);

        $response = $this->subject->modify($request, $this->buildResponseHandler(self::ORIGINAL_BODY));
        $body = (string) $response->getBody();

        self::assertStringContainsString('<div class="typo3-TCEforms">form</div>', $body);
        self::assertNotSame(self::ORIGINAL_BODY, $body);
    }

    private function buildEditRequest(string $table, int $uid): ServerRequest
    {
        return (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withQueryParams(['edit' => [$table => [(string) $uid => 'edit']]]);
    }
}
