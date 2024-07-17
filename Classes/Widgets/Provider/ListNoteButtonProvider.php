<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\ElementAttributesInterface;

class ListNoteButtonProvider implements ButtonProviderInterface, ElementAttributesInterface
{
    public function __construct(private readonly string $title, private readonly string $target = '')
    {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getLink(): string
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $params = [
            'id' => 0,
            'table' => 'tx_ximatypo3contentplanner_note',
            'returnUrl' => (string)$uriBuilder->buildUriFromRoute('dashboard'),
        ];

        return (string)$uriBuilder->buildUriFromRoute('web_list', $params);
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getElementAttributes(): array
    {
        return [];
    }
}
