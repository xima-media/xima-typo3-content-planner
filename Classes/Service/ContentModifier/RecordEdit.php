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
use Xima\XimaTypo3ContentPlanner\Service\Header\HeaderMode;
use Xima\XimaTypo3ContentPlanner\Service\Header\InfoGenerator;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

class RecordEdit extends AbstractModifier implements ModifierInterface
{
    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        if ($content === '') {
            return $response;
        }

        $table = array_key_first($request->getQueryParams()['edit']);
        $uid = $request->getQueryParams()['edit'][$table] ?? 0;
        $uid = is_array($uid) ? (int)array_key_first($uid) : (int)$uid;

        $additionalContent = GeneralUtility::makeInstance(InfoGenerator::class)->generateStatusHeader(HeaderMode::EDIT, table: $table, uid: $uid);
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

    public function isRelevant(ServerRequestInterface $request): bool
    {
        return $request->getAttribute('applicationType') === SystemEnvironmentBuilder::REQUESTTYPE_BE
            && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RECORD_EDIT_HEADER_INFO)
            && array_key_exists('edit', $request->getQueryParams())
            && in_array(array_key_first($request->getQueryParams()['edit']), ExtensionUtility::getRecordTables());
    }
}
