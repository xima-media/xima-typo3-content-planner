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

namespace Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets;

use KonradMichalik\PagetreeFacets\Event\RegisterFacetsEvent;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

/**
 * RegisterContentPlannerFacetListener.
 *
 * Registered manually in Configuration/Services.php behind class_exists(),
 * NOT via #[AsEventListener]: RegisterFacetsEvent may not be autoloadable at
 * all when konradmichalik/typo3-pagetree-facets isn't installed (this is
 * suggest-only, see Global Constraints), which is a stronger guarantee than
 * the "wrong TYPO3 version" case the existing v14-only listener pattern
 * handles - an attribute referencing an unloadable class would break
 * container compilation regardless of TYPO3 version.
 *
 * @phpstan-ignore class.notFound
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class RegisterContentPlannerFacetListener
{
    public function __construct(private ContentPlannerFacet $facet) {}

    public function __invoke(RegisterFacetsEvent $event): void
    {
        if (!ExtensionUtility::isPagetreeFacetsIntegrationEnabled()) {
            return;
        }

        $event->addFacet($this->facet);
    }
}
