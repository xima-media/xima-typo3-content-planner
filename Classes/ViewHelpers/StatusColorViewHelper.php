<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

class StatusColorViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly StatusRepository $statusRepository)
    {
    }

    /**
    * @var bool
    */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'statusId',
            'integer',
            '',
            true
        );
        $this->registerArgument(
            'colorName',
            'boolean',
            '',
            false,
            true
        );
    }

    public function render(): string
    {
        $status = $this->statusRepository->findByUid($this->arguments['statusId']);

        if (!$status instanceof Status) {
            return '';
        }
        if ((bool)$this->arguments['colorName']) {
            return $status->getColor();
        }
        return Configuration\Colors::get($status->getColor());
    }
}
