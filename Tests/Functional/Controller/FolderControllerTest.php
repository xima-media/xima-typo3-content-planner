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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\FolderController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\FolderStatusRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * FolderControllerTest.
 *
 * Redirect handling relies on GeneralUtility::sanitizeLocalUrl(), which needs an initialized
 * Environment — only available in the functional bootstrap, hence these cases live here.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class FolderControllerTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport'] = 1;
        $this->loginBackendUser();
    }

    #[Test]
    public function updateStatusActionIgnoresExternalRedirect(): void
    {
        $response = $this->createController()->updateStatusAction(
            $this->createRequest(['identifier' => '1:/user_upload/', 'status' => '3', 'redirect' => 'https://evil.example/']),
        );

        self::assertNotInstanceOf(RedirectResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    private function createController(): FolderController
    {
        return new FolderController($this->get(FolderStatusRepository::class));
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function createRequest(array $queryParams): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn([]);
        $request->method('getQueryParams')->willReturn($queryParams);

        return $request;
    }
}
