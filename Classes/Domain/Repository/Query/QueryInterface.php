<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository\Query;

interface QueryInterface
{
    public function buildSql(string $additionalWhere = ''): string;
}
