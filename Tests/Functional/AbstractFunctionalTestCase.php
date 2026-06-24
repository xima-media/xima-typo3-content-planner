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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional;

use TYPO3\CMS\Backend\Routing\{Route, RouteResult};
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\{NormalizedParams, ServerRequest};
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * AbstractFunctionalTestCase.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'xima/xima-typo3-content-planner',
    ];

    /**
     * Prevent request/language state set up by individual tests from leaking
     * into the next test (e.g. a stale TYPO3_REQUEST pointing at a fixture page
     * would break Extbase storage-pid/rootline resolution in unrelated tests).
     */
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST'], $GLOBALS['LANG']);
        parent::tearDown();
    }

    /**
     * Import a fixture shared across functional tests from Tests/Functional/Fixtures/.
     */
    protected function importSharedDataSet(string $fileName): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/'.$fileName);
    }

    /**
     * Import the shared backend user fixture and authenticate as the given user.
     */
    protected function loginBackendUser(int $userUid = 1): void
    {
        $this->importSharedDataSet('be_users.csv');
        $backendUser = $this->setUpBackendUser($userUid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * Build and register a backend server request in $GLOBALS['TYPO3_REQUEST']
     * so that URL-building utilities relying on the routing/normalizedParams
     * attributes can resolve.
     *
     * @param array<string, mixed> $queryParams
     */
    protected function setUpBackendRequest(string $routeIdentifier = 'web_layout', array $queryParams = []): ServerRequest
    {
        $request = (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withQueryParams($queryParams)
            ->withAttribute('routing', new RouteResult(
                new Route('/dummy', ['_identifier' => $routeIdentifier]),
                [],
            ))
            ->withAttribute('normalizedParams', new NormalizedParams(
                ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/typo3/index.php'],
                [],
                '',
                '',
            ));

        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $request;
    }
}
