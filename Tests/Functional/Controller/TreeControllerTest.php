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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionMethod;
use ReflectionProperty;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\TreeController;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * TreeControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class TreeControllerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function initializePageTreeRepositoryAddsStatusAndCommentFieldsWithoutRestrictionsByDefault(): void
    {
        $this->loginBackendUser(1);

        $repository = $this->invokeInitializePageTreeRepository();

        self::assertInstanceOf(PageTreeRepository::class, $repository);
        self::assertContains(Configuration::FIELD_STATUS, $this->getFields($repository));
        self::assertContains(Configuration::FIELD_COMMENTS, $this->getFields($repository));
        self::assertSame([], $this->getAdditionalQueryRestrictions($repository));
        self::assertNotNull($this->getAdditionalWhereClause($repository));
    }

    #[Test]
    public function initializePageTreeRepositoryAddsDocumentTypeExclusionRestrictionWhenConfigured(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups.csv');
        $this->loginBackendUser(20);

        $repository = $this->invokeInitializePageTreeRepository();

        self::assertCount(1, $this->getAdditionalQueryRestrictions($repository));
    }

    private function invokeInitializePageTreeRepository(): PageTreeRepository
    {
        $controller = new TreeController(
            GeneralUtility::makeInstance(IconFactory::class),
            GeneralUtility::makeInstance(UriBuilder::class),
            GeneralUtility::makeInstance(EventDispatcherInterface::class),
            GeneralUtility::makeInstance(SiteFinder::class),
        );

        $method = new ReflectionMethod($controller, 'initializePageTreeRepository');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }

    /**
     * @return string[]
     */
    private function getFields(PageTreeRepository $repository): array
    {
        $property = new ReflectionProperty($repository, 'fields');
        $property->setAccessible(true);

        return $property->getValue($repository);
    }

    /**
     * @return array<int, object>
     */
    private function getAdditionalQueryRestrictions(PageTreeRepository $repository): array
    {
        $property = new ReflectionProperty($repository, 'additionalQueryRestrictions');
        $property->setAccessible(true);

        return $property->getValue($repository);
    }

    private function getAdditionalWhereClause(PageTreeRepository $repository): ?string
    {
        $property = new ReflectionProperty($repository, 'additionalWhereClause');
        $property->setAccessible(true);

        return $property->getValue($repository);
    }
}
