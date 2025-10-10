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

namespace Xima\XimaTypo3ContentPlanner\Middleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\Header\{HeaderMode, InfoGenerator};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function array_key_exists;
use function in_array;
use function is_array;

/**
 * BackendContentModifierMiddleware.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class BackendContentModifierMiddleware implements MiddlewareInterface
{
    private ?StatusRepository $statusRepository = null;
    private ?RecordRepository $recordRepository = null;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isRelevantRecordEditRequest($request)) {
            return $this->handleRecordEditRequest($request, $handler);
        }

        if ($this->isRelevantWebListRequest($request)) {
            return $this->handleListLayoutRequest($request, $handler);
        }

        if ($this->isRelevantWebLayoutRequest($request)) {
            return $this->handleWebLayoutRequest($request, $handler);
        }

        return $handler->handle($request);
    }

    private function handleWebLayoutRequest(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        $pid = $request->getQueryParams()['id'] ?? 0;
        if (0 === $pid) {
            return $response;
        }

        $newResponse = new Response();
        $newResponse->getBody()->write($content.$this->addStatusHintToContentElement((int) $pid));

        return $newResponse;
    }

    private function handleRecordEditRequest(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        if ('' === $content) {
            return $response;
        }

        $table = array_key_first($request->getQueryParams()['edit']);
        $uid = $request->getQueryParams()['edit'][$table] ?? 0;
        $uid = is_array($uid) ? (int) array_key_first($uid) : (int) $uid;

        $additionalContent = GeneralUtility::makeInstance(InfoGenerator::class)->generateStatusHeader(HeaderMode::EDIT, table: $table, uid: $uid);
        if (!$additionalContent) {
            return $response;
        }

        /*
        * This is a workaround to add the header content to the top of the record edit form.
        */
        $newContent = preg_replace(
            '/(<div\s+class="typo3-TCEforms")/',
            $additionalContent.'$1',
            $content,
        );

        $newResponse = new Response();
        $newResponse->getBody()->write($newContent);

        return $newResponse;
    }

    private function handleListLayoutRequest(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        if ('' === $content) {
            return $response;
        }

        if (!array_key_exists('id', $request->getQueryParams())) {
            return $response;
        }

        $uid = (int) $request->getQueryParams()['id'];

        $additionalContent = GeneralUtility::makeInstance(InfoGenerator::class)->generateStatusHeader(HeaderMode::WEB_LIST, table: 'pages', uid: $uid);

        if (!$additionalContent) {
            return $response;
        }

        /*
        * This is a workaround to add the header content to the top of the list module.
        */
        $newContent = preg_replace(
            '/(<typo3-backend-editable-page-title\b[^>]*>.*?<\/typo3-backend-editable-page-title>)/is',
            '$1'.$additionalContent,
            $content,
        );

        $newResponse = new Response();
        $newResponse->getBody()->write($newContent);

        return $newResponse;
    }

    private function addStatusHintToContentElement(int $pid): string
    {
        $styling = [];
        $records = $this->getRecordRepository()->findByPid('tt_content', $pid);

        foreach ($records as $record) {
            $status = $this->getStatusRepository()->findByUid($record['tx_ximatypo3contentplanner_status']);
            if (!$status instanceof Status) {
                continue;
            }
            $statusColor = Configuration\Colors::get($status->getColor());
            $styling[] = '.t3-page-ce[data-uid="'.$record['uid'].'"]:before { content: "";display:block;box-shadow:var(--pagemodule-element-box-shadow);padding:.5em;border-left: 5px solid '.$statusColor.';border-radius: 5px 5px 0 0;background-color:'.$statusColor.'; }';
        }

        return '<style>'.implode(' ', $styling).'</style>';
    }

    private function isRelevantWebLayoutRequest(ServerRequestInterface $request): bool
    {
        return SystemEnvironmentBuilder::REQUESTTYPE_BE === $request->getAttribute('applicationType')
            && null !== $request->getAttribute('module')
            && 'web_layout' === $request->getAttribute('module')->getIdentifier()
            && in_array('tt_content', $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'], true);
    }

    private function isRelevantRecordEditRequest(ServerRequestInterface $request): bool
    {
        return SystemEnvironmentBuilder::REQUESTTYPE_BE === $request->getAttribute('applicationType')
            && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RECORD_EDIT_HEADER_INFO)
            && array_key_exists('edit', $request->getQueryParams())
            && ('pages' === array_key_first($request->getQueryParams()['edit']) || in_array(array_key_first($request->getQueryParams()['edit']), $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'], true));
    }

    private function isRelevantWebListRequest(ServerRequestInterface $request): bool
    {
        return SystemEnvironmentBuilder::REQUESTTYPE_BE === $request->getAttribute('applicationType')
            && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RECORD_EDIT_HEADER_INFO)
            && null !== $request->getAttribute('module')
            && 'web_list' === $request->getAttribute('module')->getIdentifier();
    }

    private function getStatusRepository(): StatusRepository
    {
        if (null === $this->statusRepository) {
            $this->statusRepository = GeneralUtility::makeInstance(StatusRepository::class);
        }

        return $this->statusRepository;
    }

    private function getRecordRepository(): RecordRepository
    {
        if (null === $this->recordRepository) {
            $this->recordRepository = GeneralUtility::makeInstance(RecordRepository::class);
        }

        return $this->recordRepository;
    }
}
