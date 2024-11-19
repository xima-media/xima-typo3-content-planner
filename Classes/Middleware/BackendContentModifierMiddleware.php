<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\Response;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

class BackendContentModifierMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute('applicationType') === SystemEnvironmentBuilder::REQUESTTYPE_BE
            && $request->getAttribute('module') !== null
            && $request->getAttribute('module')->getIdentifier() === 'web_layout'
            && in_array('tt_content', $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'])) {
            $response = $handler->handle($request);
            $content = $response->getBody()->__toString();

            $pid = $request->getQueryParams()['id'] ?? 0;
            if (!$pid) {
                return $handler->handle($request);
            }

            $styling = [];
            $records = ContentUtility::getExtensionRecords('tt_content', (int)$pid);

            foreach ($records as $record) {
                $status = ContentUtility::getStatus($record['tx_ximatypo3contentplanner_status']);
                if (!$status) {
                    continue;
                }
                $statusColor = Configuration::STATUS_COLOR_CODES[$status->getColor()];
                $styling[] = '.t3-page-ce[data-uid="' . $record['uid'] . '"]:before { content: "";display:block;box-shadow:var(--pagemodule-element-box-shadow);padding:.5em;border-left: 5px solid ' . $statusColor . ';border-radius: 5px 5px 0 0;background-color:' . $statusColor . '; }';
            }

            $content .= '<style>' . implode(' ', $styling) . '</style>';

            $newResponse = new Response();
            $newResponse->getBody()->write($content);

            return $newResponse;
        }

        return $handler->handle($request);
    }
}
