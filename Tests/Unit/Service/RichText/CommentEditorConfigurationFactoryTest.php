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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\RichText;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\Localization\{LanguageService, Locales};
use Xima\XimaTypo3ContentPlanner\Service\RichText\CommentEditorConfigurationFactory;

/**
 * CommentEditorConfigurationFactoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentEditorConfigurationFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->user = ['lang' => 'default'];
        $GLOBALS['BE_USER'] = $backendUser;

        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('sL')->willReturnCallback(
            static fn (string $input): string => str_starts_with($input, 'LLL:') ? 'Translated label' : $input,
        );
        $GLOBALS['LANG'] = $languageService;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER'], $GLOBALS['LANG'], $GLOBALS['TCA']);
    }

    #[Test]
    public function buildEnsuresCustomConfigStaysEmptyToBlockCkeditorFromLoadingAnExternalConfigFile(): void
    {
        $factory = $this->createFactory(['toolbar' => ['items' => ['bold', 'italic']]]);

        $configuration = $factory->build(1);

        self::assertSame('', $configuration['customConfig']);
        self::assertSame(['items' => ['bold', 'italic']], $configuration['toolbar']);
    }

    #[Test]
    public function buildResolvesUiLanguageFromTheCurrentBackendUser(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->user = ['lang' => 'de'];
        $GLOBALS['BE_USER'] = $backendUser;

        $configuration = $this->createFactory([])->build(1);

        self::assertSame('de', $configuration['language']['ui']);
    }

    #[Test]
    public function buildFallsBackToEnglishUiLanguageWhenBackendUserHasNoExplicitLanguage(): void
    {
        $configuration = $this->createFactory([])->build(1);

        self::assertSame('en', $configuration['language']['ui']);
    }

    #[Test]
    public function buildDefaultsContentLanguageToEnglishWhenPresetDoesNotOverrideIt(): void
    {
        $configuration = $this->createFactory([])->build(1);

        self::assertSame('en', $configuration['language']['content']);
    }

    #[Test]
    public function buildReplacesLllLabelReferencesRecursively(): void
    {
        $configuration = $this->createFactory([
            'placeholder' => 'LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang.xlf:comment.placeholder',
            'nested' => ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:some.key'],
        ])->build(1);

        self::assertSame('Translated label', $configuration['placeholder']);
        self::assertSame('Translated label', $configuration['nested']['label']);
    }

    #[Test]
    public function buildEditorHtmlEscapesContentAndEncodesOptionsAsJson(): void
    {
        $factory = $this->createFactory([]);

        $html = $factory->buildEditorHtml('field-1', ['customConfig' => '', 'toolbar' => ['items' => ['bold']]], '<script>alert(1)</script>');

        self::assertStringContainsString('<typo3-rte-ckeditor-ckeditor5', $html);
        self::assertStringContainsString('id="field-1-ckeditor5"', $html);
        self::assertStringContainsString('slot="textarea"', $html);
        self::assertStringContainsString('id="field-1"', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    /**
     * @param array<string, mixed> $editorConfig
     */
    private function createFactory(array $editorConfig): CommentEditorConfigurationFactory
    {
        $richtext = $this->createMock(Richtext::class);
        $richtext->method('getConfiguration')->willReturn(['editor' => ['config' => $editorConfig]]);

        // Mirrors real Locales behaviour closely enough for these tests: "en-us" (the
        // unsplit default before normalization) is not a valid key, forcing the same
        // "en" fallback core's own getLanguageIsoCodeOfContent() relies on; other keys used
        // in these tests are accepted as-is.
        $locales = $this->createMock(Locales::class);
        $locales->method('isValidLanguageKey')->willReturnCallback(static fn (string $key): bool => 'en-us' !== $key);

        return new CommentEditorConfigurationFactory($richtext, $locales);
    }
}
