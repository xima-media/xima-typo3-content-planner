<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Utility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\{GeneralUtility, PathUtility};
use Xima\XimaTypo3ContentPlanner\Configuration;

/**
 * ViewFactoryHelper.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ViewFactoryHelper
{
    /**
     * @param array<string, mixed> $values
     */
    public static function renderView(string $template, array $values, ?ServerRequestInterface $request = null): string
    {
        $typo3Version = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();

        if ($typo3Version >= 13) {
            return self::renderView13($template, $values, $request);
        }

        return self::renderView12($template, $values);
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function renderView12(string $template, array $values): string
    {
        /** @var \TYPO3\CMS\Fluid\View\StandaloneView $view */
        $view = GeneralUtility::makeInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class); // @phpstan-ignore classConstant.deprecatedClass
        $view->setFormat('html'); // @phpstan-ignore method.deprecatedClass
        $view->setTemplateRootPaths(['EXT:'.Configuration::EXT_KEY.'/Resources/Private/Templates/']); // @phpstan-ignore method.deprecatedClass
        $view->setPartialRootPaths(['EXT:'.Configuration::EXT_KEY.'/Resources/Private/Partials/']); // @phpstan-ignore method.deprecatedClass
        if (PathUtility::isExtensionPath($template)) {
            $template = GeneralUtility::getFileAbsFileName($template);
            $view->setTemplatePathAndFilename($template); // @phpstan-ignore method.deprecatedClass
        } else {
            $view->setTemplate($template); // @phpstan-ignore method.deprecatedClass
        }
        $view->assignMultiple($values);

        return $view->render();
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function renderView13(string $template, array $values, ?ServerRequestInterface $request = null): string
    {
        $viewFactoryData = new \TYPO3\CMS\Core\View\ViewFactoryData(
            templateRootPaths: ['EXT:'.Configuration::EXT_KEY.'/Resources/Private/Templates/'],
            partialRootPaths: ['EXT:'.Configuration::EXT_KEY.'/Resources/Private/Partials/'],
            layoutRootPaths: ['EXT:'.Configuration::EXT_KEY.'/Resources/Private/Layouts/'],
            request: $request,
        );

        $viewFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Core\View\ViewFactoryInterface::class);
        $view = $viewFactory->create($viewFactoryData);
        $view->assignMultiple($values);

        if (PathUtility::isExtensionPath($template)) {
            $template = GeneralUtility::getFileAbsFileName($template);
        }

        return $view->render($template);
    }
}
