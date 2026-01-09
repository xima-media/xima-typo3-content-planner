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

namespace Xima\XimaTypo3ContentPlanner\Service\ContentModifier;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\Response;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\Header\{HeaderMode, InfoGenerator};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function array_key_exists;
use function array_key_first;
use function is_array;

/**
 * RecordEditModifier.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class RecordEditModifier extends AbstractModifier implements ModifierInterface
{
    public function __construct(
        StatusRepository $statusRepository,
        RecordRepository $recordRepository,
        private readonly InfoGenerator $infoGenerator,
    ) {
        parent::__construct($statusRepository, $recordRepository);
    }

    public function isRelevant(ServerRequestInterface $request): bool
    {
        if (SystemEnvironmentBuilder::REQUESTTYPE_BE !== $request->getAttribute('applicationType')
            || !ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RECORD_EDIT_HEADER_INFO)
            || !array_key_exists('edit', $request->getQueryParams())
        ) {
            return false;
        }

        $table = array_key_first($request->getQueryParams()['edit']);

        return ExtensionUtility::isRegisteredRecordTable($table);
    }

    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        if ('' === $content) {
            return $response;
        }

        $table = array_key_first($request->getQueryParams()['edit']);
        $uid = $request->getQueryParams()['edit'][$table] ?? 0;
        $uid = is_array($uid) ? (int) array_key_first($uid) : (int) $uid;

        $additionalContent = $this->infoGenerator->generateStatusHeader(HeaderMode::EDIT, table: $table, uid: $uid);
        if (!$additionalContent) {
            return $response;
        }

        $newContent = preg_replace(
            '/(<div\s+class="typo3-TCEforms")/',
            $additionalContent.'$1',
            $content,
        );

        $newResponse = new Response();
        $newResponse->getBody()->write($newContent);

        return $newResponse;
    }
}
