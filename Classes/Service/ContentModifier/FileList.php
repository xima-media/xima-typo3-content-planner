<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\ContentModifier;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\SysFileMetadataRepository;

class FileList extends AbstractModifier implements ModifierInterface
{
    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        if ($content === '') {
            return $response;
        }

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
                $additionalCss .= 'tr[data-filelist-meta-uid="' . $record['uid'] . '"] > td { background-color: ' . Configuration\Colors::get($status->getColor(), true) . '; } ';
            }
        }

        $newContent = preg_replace(
            '/(<table\b[^>]*id="typo3-filelist"[^>]*>.*?<\/table>)/is',
            '$1' . "<style>$additionalCss</style>",
            $content
        );

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
}
