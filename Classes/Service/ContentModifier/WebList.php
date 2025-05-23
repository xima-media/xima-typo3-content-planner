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

class WebList extends AbstractModifier implements ModifierInterface
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

        $uid = (int)$request->getQueryParams()['id'];

        $additionalContent = GeneralUtility::makeInstance(InfoGenerator::class)->generateStatusHeader(HeaderMode::WEB_LIST, table: 'pages', uid: $uid);

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

    public function isRelevant(ServerRequestInterface $request): bool
    {
        return $request->getAttribute('applicationType') === SystemEnvironmentBuilder::REQUESTTYPE_BE
            && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RECORD_EDIT_HEADER_INFO)
            && $request->getAttribute('module') !== null
            && $request->getAttribute('module')->getIdentifier() === 'web_list';
    }
}
