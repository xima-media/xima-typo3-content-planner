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
                'iconIdentifier' => 'flag-gray',
                'height' => 'large',
                'width' => 'medium',
            ]);
    }
};
