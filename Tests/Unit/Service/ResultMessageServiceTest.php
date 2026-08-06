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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Xima\XimaTypo3ContentPlanner\Service\ResultMessageService;

/**
 * ResultMessageServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ResultMessageServiceTest extends TestCase
{
    private ResultMessageService $subject;

    protected function setUp(): void
    {
        // sL() echoes the key back, so the assertions can pin down which label was picked
        // without depending on the shipped translations.
        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('sL')->willReturnArgument(0);
        $GLOBALS['LANG'] = $languageService;

        $this->subject = new ResultMessageService();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ContextualFeedbackSeverity}>
     */
    public static function severityProvider(): array
    {
        return [
            'status changed success' => ['status.changed', 'success', ContextualFeedbackSeverity::OK],
            'status changed failure' => ['status.changed', 'failure', ContextualFeedbackSeverity::ERROR],
            'status reset success' => ['status.reset', 'success', ContextualFeedbackSeverity::NOTICE],
            'status reset failure' => ['status.reset', 'failure', ContextualFeedbackSeverity::ERROR],
            'assignee changed success' => ['assignee.changed', 'success', ContextualFeedbackSeverity::OK],
            'assignee reset success' => ['assignee.reset', 'success', ContextualFeedbackSeverity::NOTICE],
            'comment create success' => ['comment.create', 'success', ContextualFeedbackSeverity::OK],
            'comment edit success' => ['comment.edit', 'success', ContextualFeedbackSeverity::OK],
            'comment resolve success' => ['comment.resolve', 'success', ContextualFeedbackSeverity::OK],
            'comment delete success' => ['comment.delete', 'success', ContextualFeedbackSeverity::WARNING],
            'comment delete failure' => ['comment.delete', 'failure', ContextualFeedbackSeverity::ERROR],
        ];
    }

    #[Test]
    #[DataProvider('severityProvider')]
    public function resolveKeepsTheSeverityOfEveryCatalogueEntry(
        string $path,
        string $resultStatus,
        ContextualFeedbackSeverity $expected,
    ): void {
        $result = $this->subject->resolve($path, $resultStatus);

        self::assertNotNull($result);
        self::assertSame($expected, $result->severity);
    }

    #[Test]
    public function resolveMarksSuccessResultsAsSuccessful(): void
    {
        $result = $this->subject->resolve('status.changed');

        self::assertNotNull($result);
        self::assertTrue($result->success);
    }

    #[Test]
    public function resolveMarksFailureResultsAsUnsuccessful(): void
    {
        $result = $this->subject->resolve('status.changed', 'failure');

        self::assertNotNull($result);
        self::assertFalse($result->success);
    }

    #[Test]
    public function resolveDefaultsToTheSuccessVariant(): void
    {
        self::assertEquals($this->subject->resolve('status.changed', 'success'), $this->subject->resolve('status.changed'));
    }

    #[Test]
    public function resolveLocalizesTitleAndMessageSeparately(): void
    {
        $result = $this->subject->resolve('status.changed');

        self::assertNotNull($result);
        self::assertStringEndsWith(':message.status.changed.success.title', $result->title);
        self::assertStringEndsWith(':message.status.changed.success.message', $result->message);
    }

    #[Test]
    public function resolveReturnsNullForAnUnknownPath(): void
    {
        self::assertNull($this->subject->resolve('does.not.exist'));
    }

    #[Test]
    public function resolveReturnsNullForAnUnknownResultStatus(): void
    {
        self::assertNull($this->subject->resolve('status.changed', 'sideways'));
    }

    #[Test]
    public function resolveReturnsNullForAPartialPath(): void
    {
        self::assertNull($this->subject->resolve('status'));
    }

    #[Test]
    public function resolveReturnsNullWhenThePathLandsOnABranchInsteadOfALeaf(): void
    {
        // Every key resolves here — 'changed' is a valid branch of 'status' — but the
        // result is the success/failure branch rather than a message.
        self::assertNull($this->subject->resolve('status', 'changed'));
    }

    #[Test]
    public function toArraySerializesSeverityAsInteger(): void
    {
        $result = $this->subject->resolve('status.changed');

        self::assertNotNull($result);
        // notification.js switches on the numeric severity, so this has to stay an int.
        self::assertSame(ContextualFeedbackSeverity::OK->value, $result->toArray()['severity']);
    }

    #[Test]
    public function toArrayCarriesTheFullEnvelope(): void
    {
        $result = $this->subject->resolve('comment.delete');

        self::assertNotNull($result);
        self::assertSame(
            ['success', 'title', 'message', 'severity'],
            array_keys($result->toArray()),
        );
    }
}
