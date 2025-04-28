<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\ContentModifier;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

abstract class AbstractModifier
{
    private ?StatusRepository $statusRepository = null;
    private ?RecordRepository $recordRepository = null;

    protected function getStatusRepository(): StatusRepository
    {
        if ($this->statusRepository === null) {
            $this->statusRepository = GeneralUtility::makeInstance(StatusRepository::class);
        }
        return $this->statusRepository;
    }

    protected function getRecordRepository(): RecordRepository
    {
        if ($this->recordRepository === null) {
            $this->recordRepository = GeneralUtility::makeInstance(RecordRepository::class);
        }
        return $this->recordRepository;
    }
}
