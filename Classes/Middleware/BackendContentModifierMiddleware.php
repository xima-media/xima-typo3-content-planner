<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;

class BackendContentModifierMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly CommentRepository $commentRepository,
        private readonly RequestId $requestId
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (UrlHelper::isRelevantRecordEditRequest($request)) {
            $response = $handler->handle($request);
            $content = $response->getBody()->__toString();

            if ($content !== '') {
                $table = array_key_first($request->getQueryParams()['edit']);
                $uid = $request->getQueryParams()['edit'][$table] ?? 0;
                $uid = is_array($uid) ? (int)array_key_first($uid) : (int)$uid;

                $newContent = $this->addRecordEditHeader($table, $uid, $content);
                if (!$newContent) {
                    return $response;
                }

                $newResponse = new Response();
                $newResponse->getBody()->write($newContent);

                return $newResponse;
            }
            return $response;
        }

        if (UrlHelper::isRelevantWebLayoutRequest($request)) {
            $response = $handler->handle($request);
            $content = $response->getBody()->__toString();

            $pid = $request->getQueryParams()['id'] ?? 0;
            if (!$pid) {
                return $handler->handle($request);
            }

            $newResponse = new Response();
            $newResponse->getBody()->write($content . $this->addStatusHintToContentElement((int)$pid));

            return $newResponse;
        }

        return $handler->handle($request);
    }

    private function addRecordEditHeader(string $table, int $uid, string $content): string|bool
    {
        $record = $this->recordRepository->findByUid($table, $uid);

        if ($record) {
            $status = $this->statusRepository->findByUid($record['tx_ximatypo3contentplanner_status']);

            if (!$status) {
                return false;
            }

            $view = GeneralUtility::makeInstance(StandaloneView::class);
            $view->setTemplatePathAndFilename('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates/Backend/Header/HeaderInfo.html');

            $view->assignMultiple([
                'mode' => 'edit',
                'data' => $record,
                'assignee' => $this->backendUserRepository->getUsernameByUid((int)$record['tx_ximatypo3contentplanner_assignee']),
                'icon' => $status->getColoredIcon(),
                'status' => $status,
                'comments' => $record['tx_ximatypo3contentplanner_comments'] ? $this->commentRepository->findAllByRecord($uid, $table) : [],
                'pid' => $record['pid'],
                'userid' => $GLOBALS['BE_USER']->user['uid'],
            ]);

            $additionalContent = $view->render();
            $additionalContent .= ExtensionUtility::getCssTag('EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Header.css', ['nonce' => $this->requestId->nonce]);

            /*
            * This is a workaround to add the header content to the top of the record edit form.
            */
            return preg_replace('/(<div\s+class="typo3-TCEforms")/', $additionalContent . '$1', $content);
        }
        return false;
    }

    private function addStatusHintToContentElement(int $pid): string
    {
        $styling = [];
        $records = $this->recordRepository->findByPid('tt_content', (int)$pid);

        foreach ($records as $record) {
            $status = $this->statusRepository->findByUid($record['tx_ximatypo3contentplanner_status']);
            if (!$status) {
                continue;
            }
            $statusColor = Configuration\Colors::get($status->getColor());
            $styling[] = '.t3-page-ce[data-uid="' . $record['uid'] . '"]:before { content: "";display:block;box-shadow:var(--pagemodule-element-box-shadow);padding:.5em;border-left: 5px solid ' . $statusColor . ';border-radius: 5px 5px 0 0;background-color:' . $statusColor . '; }';
        }

        return '<style>' . implode(' ', $styling) . '</style>';
    }
}
