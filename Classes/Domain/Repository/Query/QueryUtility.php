<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository\Query;

class QueryUtility
{
    public static function getQueryObjectByTable(string $table): QueryInterface
    {
        $className = __NAMESPACE__ . '\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));

        if (class_exists($className)) {
            return new $className($table);
        }

        return new Record($table);
    }
}
