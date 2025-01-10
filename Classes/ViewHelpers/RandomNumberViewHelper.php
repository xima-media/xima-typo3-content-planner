<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class RandomNumberViewHelper extends AbstractViewHelper
{
    /**
    * @var bool
    */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'min',
            'integer',
            '',
            false,
            1
        );
        $this->registerArgument(
            'max',
            'integer',
            '',
            false,
            10
        );
    }

    /**
    * @throws \Random\RandomException
    */
    public function render()
    {
        return random_int($this->arguments['min'], $this->arguments['max']);
    }
}
