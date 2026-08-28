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

namespace Xima\XimaTypo3ContentPlanner\Service\RichText;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\Localization\{LanguageService, Locales};
use TYPO3\CMS\Core\Utility\{GeneralUtility, PathUtility};
use Xima\XimaTypo3ContentPlanner\Configuration;

use function is_array;
use function is_string;

/**
 * CommentEditorConfigurationFactory.
 *
 * Compiles the "comments" RTE preset (Configuration/RTE/Comments.yaml) into the CKEditor5
 * `options` payload consumed by the `<typo3-rte-ckeditor-ckeditor5>` web component
 * (@typo3/rte-ckeditor/ckeditor5.js), for use outside of FormEngine.
 *
 * This replicates the relevant parts of TYPO3\CMS\RteCKEditor\Form\Element\RichTextElement,
 * which is @internal and FormEngine-specific (it renders field information, field wizards and
 * a "null" checkbox alongside the editor, none of which apply to a standalone composer). Only
 * the configuration-compilation half is reproduced here: preset resolution via the core
 * TYPO3\CMS\Core\Configuration\Richtext class (itself @internal, "may change / vanish any
 * time" per its own docblock), then the label/EXT:/LLL: normalization RichTextElement applies
 * before handing the result to the editor. See CP-327 for the risk assessment; if a future
 * core minor breaks this, the fallback is a deliberately leaner comment-only toolbar, not a
 * deeper replication.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class CommentEditorConfigurationFactory
{
    private const TABLE = Configuration::TABLE_COMMENT;
    private const FIELD = 'content';

    public function __construct(
        private Richtext $richtext,
        private Locales $locales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $pid): array
    {
        $tcaFieldConf = $GLOBALS['TCA'][self::TABLE]['columns'][self::FIELD]['config'] ?? [];
        $configuration = $this->richtext->getConfiguration(self::TABLE, self::FIELD, $pid, '0', $tcaFieldConf);
        $editorConfig = is_array($configuration['editor']['config'] ?? null) ? $configuration['editor']['config'] : [];

        $ckeditorConfiguration = [
            'customConfig' => '',
            ...$editorConfig,
        ];

        $ckeditorConfiguration['language'] = $this->resolveLanguageConfiguration($ckeditorConfiguration);
        $ckeditorConfiguration = $this->replaceLanguageFileReferences($ckeditorConfiguration);
        $ckeditorConfiguration = $this->replaceAbsolutePathsToRelativeResourcesPath($ckeditorConfiguration);

        return $ckeditorConfiguration;
    }

    /**
     * Builds the `<typo3-rte-ckeditor-ckeditor5>` web component markup, mirroring the relevant
     * fragment of RichTextElement::render() (the "options" attribute must be exactly the JSON
     * encoding CKEditor5 expects, and the textarea must carry slot="textarea").
     *
     * @param array<string, mixed> $ckeditorConfiguration
     */
    public function buildEditorHtml(string $fieldId, array $ckeditorConfiguration, string $content): string
    {
        $ckeditorAttributes = GeneralUtility::implodeAttributes([
            'id' => $fieldId.'-ckeditor5',
            'options' => GeneralUtility::jsonEncodeForHtmlAttribute($ckeditorConfiguration, false),
        ], true);

        $textareaAttributes = GeneralUtility::implodeAttributes([
            'slot' => 'textarea',
            'id' => $fieldId,
            'name' => $fieldId,
            'rows' => '4',
            'class' => 'form-control',
        ], true);

        return '<typo3-rte-ckeditor-ckeditor5 '.$ckeditorAttributes.'>'
            .'<textarea '.$textareaAttributes.'>'.htmlspecialchars($content, \ENT_QUOTES).'</textarea>'
            .'</typo3-rte-ckeditor-ckeditor5>';
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array{ui: string, content: string}
     */
    private function resolveLanguageConfiguration(array $configuration): array
    {
        $userLanguage = (string) ($this->getBackendUser()->user['lang'] ?? '');
        $uiLanguage = '' === $userLanguage || 'default' === $userLanguage ? 'en' : $userLanguage;

        $contentLanguage = 'en-US';
        if (is_array($configuration['language'] ?? null) && isset($configuration['language']['content'])) {
            $contentLanguage = (string) $configuration['language']['content'];
        }
        $parts = explode('_', $contentLanguage);
        $contentLanguage = strtolower($parts[0]).('' !== ($parts[1] ?? '') ? '_'.strtoupper($parts[1]) : '');
        if ('default' === $contentLanguage || !$this->locales->isValidLanguageKey($contentLanguage)) {
            $contentLanguage = 'en';
        }

        return ['ui' => $uiLanguage, 'content' => $contentLanguage];
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    private function replaceLanguageFileReferences(array $configuration): array
    {
        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                $configuration[$key] = $this->replaceLanguageFileReferences($value);
            } elseif (is_string($value)) {
                $configuration[$key] = $this->getLanguageService()->sL($value);
            }
        }

        return $configuration;
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    private function replaceAbsolutePathsToRelativeResourcesPath(array $configuration): array
    {
        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                $configuration[$key] = $this->replaceAbsolutePathsToRelativeResourcesPath($value);
            } elseif (is_string($value) && PathUtility::isExtensionPath(strtoupper($value))) {
                $configuration[$key] = $this->resolveUrlPath($value);
            }
        }

        return $configuration;
    }

    private function resolveUrlPath(string $value): string
    {
        if (str_contains($value, '?')) {
            return PathUtility::getPublicResourceWebPath($value);
        }
        $value = GeneralUtility::getFileAbsFileName($value);
        $value = GeneralUtility::createVersionNumberedFilename($value);

        return PathUtility::getAbsoluteWebPath($value);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
