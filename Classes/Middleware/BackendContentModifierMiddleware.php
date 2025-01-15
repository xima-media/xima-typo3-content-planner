<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

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
        if (!$pid) {
            return $response;
        }

        $newResponse = new Response();
        $newResponse->getBody()->write($content . $this->addStatusHintToContentElement((int)$pid));

        return $newResponse;
    }

    private function handleRecordEditRequest(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        if ($content === '') {
            return $response;
        }

        $table = array_key_first($request->getQueryParams()['edit']);
        $uid = $request->getQueryParams()['edit'][$table] ?? 0;
        $uid = is_array($uid) ? (int)array_key_first($uid) : (int)$uid;

        $additionalContent = $this->generateStatusHeader($table, $uid);
        if (!$additionalContent) {
            return $response;
        }

        /*
        * This is a workaround to add the header content to the top of the record edit form.
        */
        $newContent = preg_replace(
            '/(<div\s+class="typo3-TCEforms")/',
            $additionalContent . '$1',
            $content
        );

        $newResponse = new Response();
        $newResponse->getBody()->write($newContent);

        return $newResponse;
    }

    private function handleListLayoutRequest(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        if ($content === '') {
            return $response;
        }

        $uid = (int)$request->getQueryParams()['id'];

        $additionalContent = $this->generateStatusHeader('pages', $uid);

        if (!$additionalContent) {
            return $response;
        }

        /*
        * This is a workaround to add the header content to the top of the list module.
        */
        $newContent = preg_replace(
            '/(<typo3-backend-editable-page-title\b[^>]*>.*?<\/typo3-backend-editable-page-title>)/is',
            '$1' . $additionalContent,
            $content
        );

        $newResponse = new Response();
        $newResponse->getBody()->write($newContent);

        return $newResponse;
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

    private function generateStatusHeader(string $table, int $uid): string|bool
    {
        $record = $this->recordRepository->findByUid($table, $uid);

        if (!$record) {
            return false;
        }

        $status = $this->statusRepository->findByUid($record['tx_ximatypo3contentplanner_status']);

        if (!$status) {
            return false;
        }

        $additionalContent = $this->renderStatusHeaderContentView(
            $record,
            $status,
            $record['tx_ximatypo3contentplanner_comments'] ? $this->commentRepository->findAllByRecord($uid, $table) : []
        );

        return $additionalContent;
    }

    private function renderStatusHeaderContentView(array $record, Status $status, array $comments): string
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates/Backend/Header/HeaderInfo.html');

        $view->assignMultiple([
            'mode' => 'edit',
            'data' => $record,
            'assignee' => $this->backendUserRepository->getUsernameByUid((int)$record['tx_ximatypo3contentplanner_assignee']),
            'icon' => $status->getColoredIcon(),
            'status' => $status,
            'comments' => $comments,
            'pid' => array_key_exists('pid', $record) ? $record['pid'] : null,
            'userid' => $GLOBALS['BE_USER']->user['uid'],
        ]);

        $content = $view->render();
        $content .= ExtensionUtility::getCssTag('EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Header.css', ['nonce' => $this->requestId->nonce]);

        return $content;
    }

    private function isRelevantWebLayoutRequest(ServerRequestInterface $request): bool
    {
        return $request->getAttribute('applicationType') === SystemEnvironmentBuilder::REQUESTTYPE_BE
            && $request->getAttribute('module') !== null
            && $request->getAttribute('module')->getIdentifier() === 'web_layout'
            && in_array('tt_content', $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables']);
    }

    private function isRelevantRecordEditRequest(ServerRequestInterface $request): bool
    {
        return $request->getAttribute('applicationType') === SystemEnvironmentBuilder::REQUESTTYPE_BE
            && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RECORD_EDIT_HEADER_INFO)
            && array_key_exists('edit', $request->getQueryParams())
            && (array_key_first($request->getQueryParams()['edit']) === 'pages' || in_array(array_key_first($request->getQueryParams()['edit']), $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables']));
    }

    private function isRelevantWebListRequest(ServerRequestInterface $request): bool
    {
        return $request->getAttribute('applicationType') === SystemEnvironmentBuilder::REQUESTTYPE_BE
            && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RECORD_EDIT_HEADER_INFO)
            && $request->getAttribute('module') !== null
            && $request->getAttribute('module')->getIdentifier() === 'web_list';
    }
}
