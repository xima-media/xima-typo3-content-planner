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

namespace Xima\XimaTypo3ContentPlanner\Utility\Rendering;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileException;
use TYPO3\CMS\Core\SystemResource\Publishing\{SystemResourcePublisherInterface, UriGenerationOptions};
use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;
use TYPO3\CMS\Core\Utility\{GeneralUtility, PathUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\VersionUtility;

use function sprintf;

/**
 * AssetUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class AssetUtility
{
    /**
     * @param array<string, mixed> $attributes
     *
     * @throws InvalidFileException
     */
    public static function getCssTag(
        string $cssFileLocation,
        array $attributes,
    ): string {
        return sprintf(
            '<link %s />',
            GeneralUtility::implodeAttributes([
                ...$attributes,
                'rel' => 'stylesheet',
                'media' => 'all',
                'href' => self::getPublicResourcePath($cssFileLocation),
            ], true),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @throws InvalidFileException
     */
    public static function getJsTag(
        string $jsFileLocation,
        array $attributes,
    ): string {
        return sprintf(
            '<script type="module" %s></script>',
            GeneralUtility::implodeAttributes([
                ...$attributes,
                'src' => self::getPublicResourcePath($jsFileLocation),
            ], true),
        );
    }

    /**
     * Get public resource path with TYPO3 13/14 compatibility.
     * Uses SystemResourceFactory for TYPO3 14+, PathUtility for TYPO3 13.
     */
    public static function getPublicResourcePath(string $resourcePath): string
    {
        if (VersionUtility::is14OrHigher()) {
            /** @var SystemResourceFactory $resourceFactory */
            $resourceFactory = GeneralUtility::makeInstance(SystemResourceFactory::class);
            /** @var SystemResourcePublisherInterface $resourcePublisher */
            $resourcePublisher = GeneralUtility::makeInstance(SystemResourcePublisherInterface::class);
            $resource = $resourceFactory->createPublicResource($resourcePath);
            /** @var ServerRequestInterface|null $request */
            $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

            return (string) $resourcePublisher->generateUri(
                $resource,
                $request,
                new UriGenerationOptions(absoluteUri: false),
            );
        }

        // TYPO3 13 fallback - deprecated in v14, will be removed in v15
        // @phpstan-ignore staticMethod.deprecated
        return PathUtility::getPublicResourceWebPath($resourcePath);
    }
}
