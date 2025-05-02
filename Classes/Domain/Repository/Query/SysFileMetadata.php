<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository\Query;

class SysFileMetadata extends AbstractQuery implements QueryInterface
{
    protected array $selects = ['title' => 'sys_file.name'];

    protected function getJoin(): string
    {
        return sprintf('LEFT JOIN sys_file ON %s.file = sys_file.uid', $this->getAlias());
    }
}
