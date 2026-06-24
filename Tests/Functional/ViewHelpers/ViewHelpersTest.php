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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\ViewHelpers;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\View\{ViewFactoryData, ViewFactoryInterface};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ViewHelpersTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ViewHelpersTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest();
    }

    #[Test]
    public function randomNumberReturnsValueWithinFixedRange(): void
    {
        self::assertSame('5', $this->render('RandomNumber.html'));
    }

    #[Test]
    public function randomNumberWithDefaultArgumentsStaysWithinBounds(): void
    {
        $result = (int) $this->render('RandomNumberDefault.html');

        self::assertGreaterThanOrEqual(1, $result);
        self::assertLessThanOrEqual(10, $result);
    }

    #[Test]
    public function statusColorReturnsRgbColorCode(): void
    {
        self::assertSame('rgb(100,187,200)', $this->render('StatusColorCode.html', ['statusId' => 1]));
    }

    #[Test]
    public function statusColorReturnsColorName(): void
    {
        self::assertSame('blue', $this->render('StatusColorName.html', ['statusId' => 1]));
    }

    #[Test]
    public function statusColorReturnsEmptyStringForUnknownStatus(): void
    {
        self::assertSame('', $this->render('StatusColorName.html', ['statusId' => 999]));
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function render(string $template, array $variables = []): string
    {
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            templatePathAndFilename: __DIR__.'/Fixtures/'.$template,
            request: $GLOBALS['TYPO3_REQUEST'],
        ));
        $view->assignMultiple($variables);

        return trim($view->render());
    }
}
