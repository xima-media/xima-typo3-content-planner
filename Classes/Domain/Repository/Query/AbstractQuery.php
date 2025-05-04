<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository\Query;

abstract class AbstractQuery
{
    protected array $defaultSelects = [
        'uid',
        'pid',
        'tstamp',
        'tx_ximatypo3contentplanner_status',
        'tx_ximatypo3contentplanner_assignee',
        'tx_ximatypo3contentplanner_comments',
        'perms_userid' => 0,
        'perms_user' => 0,
        'perms_groupid' => 0,
        'perms_group' => 0,
        'perms_everybody' => 0,
    ];
    protected array $selects = [];
    protected array $defaultWhere = ['tx_ximatypo3contentplanner_status IS NOT NULL', 'tx_ximatypo3contentplanner_status != 0'];
    protected array $where = [];

    public function __construct(protected string $table)
    {
    }

    public function buildSql(string $additionalWhere = ''): string
    {
        return sprintf(
            'SELECT %s FROM %s %s %s WHERE %s',
            implode(',', $this->getSelects()),
            $this->getTable(),
            $this->getAlias(),
            $this->getJoin(),
            implode(' AND ', $this->getWhere()) . ' ' . $additionalWhere,
        );
    }

    protected function getSelects(): array
    {
        $mergedSelects = [];
        foreach (array_merge($this->defaultSelects, $this->getDynamicSelects(), $this->selects) as $key => $value) {
            if (is_int($key)) {
                $mergedSelects[$value] = $value;
            } else {
                $mergedSelects[$key] = $value;
            }
        }

        $resultSelect = [];
        foreach ($mergedSelects as $key => $value) {
            $alias = $this->getAlias() . '.';
            if (is_int($key) || $key === $value) {
                $resultSelect[] = $alias . $value;
            } else {
                if (is_int($value) || str_starts_with($value, '"') || str_contains($value, '.')) {
                    $alias = '';
                }
                $resultSelect[] = $alias . $value . ' AS ' . $key;
            }
        }

        return $resultSelect;
    }

    protected function getDynamicSelects(): array
    {
        return ['title' => $this->getTitleField(), 'tablename' => '"' . $this->getTable() . '"'];
    }

    protected function getAlias(): string
    {
        return $this->table . substr(md5($this->table), 0, 3);
    }

    protected function getTable(): string
    {
        return ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $this->table)), '_');
    }

    protected function getJoin(): string
    {
        return '';
    }

    protected function getWhere(): array
    {
        return array_map(fn ($where) => $this->getAlias() . '.' . $where, array_merge($this->defaultWhere, $this->where));
    }

    protected function getTitleField(): string
    {
        return $GLOBALS['TCA'][$this->getTable()]['ctrl']['label'] ?? 'uid';
    }
}
