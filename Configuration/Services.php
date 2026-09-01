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

use Symfony\Component\DependencyInjection\{ContainerBuilder, Reference};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Dashboard\WidgetRegistry;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository, StatusRepository};

return static function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    $services = $configurator->services();
    $typo3Version = new Typo3Version();

    /*
     * Register ConfigurableContentStatusWidget only for TYPO3 v14+
     * This widget implements WidgetRendererInterface which is only available in v14+
     * and allows users to configure widget settings (status filter, assignee, etc.)
     */
    if ($typo3Version->getMajorVersion() >= 14 && $containerBuilder->hasDefinition(WidgetRegistry::class)) {
        $services->set('dashboard.widget.contentPlanner-configurable')
            // Widget class is excluded from PHPStan analysis (see phpstan.neon)
            // because it implements v14-only interfaces.
            ->class(Xima\XimaTypo3ContentPlanner\Widgets\ConfigurableContentStatusWidget::class)
            ->arg('$configuration', new Reference(WidgetConfigurationInterface::class))
            ->arg('$statusRepository', new Reference(StatusRepository::class))
            ->arg('$backendUserRepository', new Reference(BackendUserRepository::class))
            ->arg('$recordRepository', new Reference(RecordRepository::class))
            ->arg('$pageRenderer', new Reference(PageRenderer::class))
            ->tag('dashboard.widget', [
                'identifier' => 'contentPlanner-configurable',
                'groupNames' => 'contentPlanner',
                'title' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.contentPlanner.configurable.title',
                'description' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:widgets.contentPlanner.configurable.description',
                'iconIdentifier' => 'dashboard-custom',
                'height' => 'large',
                'width' => 'medium',
            ]);
    }

    /*
     * Register the Content Planner facet in EXT:typo3_pagetree_facets only when
     * that suggest-only package is installed (RegisterFacetsEvent autoloadable)
     * and the feature flag is on. No #[AsEventListener] here: the event class
     * may not exist at all when the package isn't installed, which an attribute
     * cannot tolerate regardless of TYPO3 version - see the class docblock.
     */
    if (class_exists(KonradMichalik\PagetreeFacets\Event\RegisterFacetsEvent::class)) {
        $services->set(Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets\ContentPlannerFacet::class)
            ->arg('$query', new Reference(Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets\ContentPlannerFacetQuery::class))
            ->arg('$stateMapper', new Reference(Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets\ContentPlannerFacetStateMapper::class))
            ->arg('$statusRepository', new Reference(StatusRepository::class));

        $services->set(Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets\RegisterContentPlannerFacetListener::class)
            ->arg('$facet', new Reference(Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets\ContentPlannerFacet::class))
            ->tag('event.listener', [
                'identifier' => 'xima-typo3-content-planner/pagetree-facets/register-facet',
                'event' => KonradMichalik\PagetreeFacets\Event\RegisterFacetsEvent::class,
            ]);
    }
};
