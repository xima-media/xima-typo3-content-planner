<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\ContentModifier;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\Response;
use Xima\XimaTypo3ContentPlanner\Configuration;

class WebLayout extends AbstractModifier implements ModifierInterface
{
    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        $pid = $request->getQueryParams()['id'] ?? 0;
        if (!$pid) {
            return $response;
        }

        $newResponse = new Response();
        $newResponse->getBody()->write($content . $this->addStatusHintToContentElement((int)$pid));

        return $newResponse;
    }

    public function isRelevant(ServerRequestInterface $request): bool
    {
        return $request->getAttribute('applicationType') === SystemEnvironmentBuilder::REQUESTTYPE_BE
            && $request->getAttribute('module') !== null
            && $request->getAttribute('module')->getIdentifier() === 'web_layout'
            && in_array('tt_content', $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables']);
    }

    private function addStatusHintToContentElement(int $pid): string
    {
        $styling = [];
        $records = $this->getRecordRepository()->findByPid('tt_content', (int)$pid);

        foreach ($records as $record) {
            $status = $this->getStatusRepository()->findByUid($record['tx_ximatypo3contentplanner_status']);
            if (!$status) {
                continue;
            }
            $statusColor = Configuration\Colors::get($status->getColor());
            $styling[] = '.t3-page-ce[data-uid="' . $record['uid'] . '"]:before { content: "";display:block;box-shadow:var(--pagemodule-element-box-shadow);padding:.5em;border-left: 5px solid ' . $statusColor . ';border-radius: 5px 5px 0 0;background-color:' . $statusColor . '; }';
        }

        return '<style>' . implode(' ', $styling) . '</style>';
    }
}
