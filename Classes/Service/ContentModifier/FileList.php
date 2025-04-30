<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\ContentModifier;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\SysFileMetadataRepository;
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\ListSelectionService;

class FileList extends AbstractModifier implements ModifierInterface
{
    private ?ListSelectionService $selectionBuilder = null;

    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        if ($content === '') {
            return $response;
        }

        $isTilesViewMode = (isset($request->getQueryParams()['viewMode']) && $request->getQueryParams()['viewMode'] === 'tiles');

        if (!array_key_exists('id', $request->getQueryParams())) {
            return $response;
        }
        $identifier = $request->getQueryParams()['id'];
        $additionalCss = '';

        $sysFileMetadataRepository = GeneralUtility::makeInstance(SysFileMetadataRepository::class);
        $files = $sysFileMetadataRepository->findFilesByFolder($identifier);
        if (empty($files)) {
            return $response;
        }

        foreach ($files as $file) {
            $record = $sysFileMetadataRepository->findByIdentifier($file->getIdentifier());

            if (!$record || !array_key_exists('tx_ximatypo3contentplanner_status', $record)) {
                continue;
            }

            $status = $this->getStatusRepository()->findByUid($record['tx_ximatypo3contentplanner_status']);
            if ($status) {
                if ($isTilesViewMode) {
                    $additionalCss .= 'div[data-filelist-meta-uid="' . $record['uid'] . '"] { background-color: ' . Configuration\Colors::get($status->getColor(), true) . '; } ';
                } else {
                    $additionalCss .= 'tr[data-filelist-meta-uid="' . $record['uid'] . '"] > td { background-color: ' . Configuration\Colors::get($status->getColor(), true) . '; } ';
                }
            }

            if (!$isTilesViewMode) {
                $this->generateSelection($status, $record['uid'], $content);
            }
        }

        if ($isTilesViewMode) {
            $newContent = preg_replace(
                '/(<div\b[^>]*class="resource-tiles"[^>]*>)/is',
                '$1' . "<style>$additionalCss</style>",
                $content
            );
        } else {
            $newContent = preg_replace(
                '/(<table\b[^>]*id="typo3-filelist"[^>]*>.*?<\/table>)/is',
                '$1' . "<style>$additionalCss</style>",
                $content
            );
        }
        $newResponse = new Response();
        $newResponse->getBody()->write($newContent);

        return $newResponse;
    }

    public function isRelevant(ServerRequestInterface $request): bool
    {
        return $request->getAttribute('applicationType') === SystemEnvironmentBuilder::REQUESTTYPE_BE
            && $request->getAttribute('module') !== null
            && $request->getAttribute('module')->getIdentifier() === 'media_management';
    }

    private function generateSelection(?Status $status, int $uid, string &$content): void
    {
        $title = htmlspecialchars($status ? $status->getTitle() : 'Status', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $icon = $status ? $status->getColoredIcon() : 'flag-gray';
        $selection = '
                <a href="#" class="btn btn-default btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="' . $title . '">'
            . GeneralUtility::makeInstance(IconFactory::class)->getIcon($icon, Icon::SIZE_SMALL)->render() . '</a><ul class="dropdown-menu">';

        $actionsToAdd = $this->getSelectionBuilder()->generateSelection('sys_file_metadata', $uid);
        foreach ($actionsToAdd as $actionToAdd) {
            $selection .= $actionToAdd;
        }

        $selection .= '</ul>';
        $selection .= '';

        $content = preg_replace(
            '/(<tr\b[^>]*data-filelist-meta-uid="' . $uid . '"[^>]*>.*?<div class="btn-group">)(.*?)(<a[^>]*class="btn btn-sm btn-default dropdown-toggle[^>]*>.*?<\/a>)/is',
            '$1$2' . $selection . '$3',
            $content
        );
    }

    private function getSelectionBuilder(): ListSelectionService
    {
        if ($this->selectionBuilder == null) {
            $this->selectionBuilder = GeneralUtility::makeInstance(
                ListSelectionService::class,
                $this->getStatusRepository(),
                $this->getRecordRepository(),
                GeneralUtility::makeInstance(
                    StatusSelectionManager::class,
                    GeneralUtility::makeInstance(EventDispatcher::class)
                ),
                GeneralUtility::makeInstance(UriBuilder::class),
                GeneralUtility::makeInstance(SysFileMetadataRepository::class),
                GeneralUtility::makeInstance(CommentRepository::class),
                GeneralUtility::makeInstance(IconFactory::class)
            );
        }

        return $this->selectionBuilder;
    }
}
