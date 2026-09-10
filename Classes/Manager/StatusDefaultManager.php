<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Manager;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

use function array_key_exists;

/**
 * StatusDefaultManager.
 *
 * CP-27 (#326): enforces that at most one {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\Status}
 * record carries the "is_default" flag (the initial status applied by the comment-first
 * one-click flow). Extracted out of DataHandlerHook, which is already at its cognitive
 * complexity ceiling, to keep that class focused.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class StatusDefaultManager
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private FrontendInterface $cache,
    ) {}

    /**
     * Called from DataHandlerHook::processDatamap_afterDatabaseOperations() for the status
     * table. When the just-saved record turned "is_default" on, every other status row has
     * it cleared via a single raw UPDATE - deterministic even for a bulk import that sets the
     * flag on two rows within the same process_datamap() call: each write clears every other
     * row again, so whichever row is processed last is the one left with the flag.
     *
     * @param array<string, mixed> $fieldArray
     */
    public function enforceUniqueDefaultAfterSave(string|int $id, array $fieldArray, DataHandler $dataHandler): void
    {
        if (!array_key_exists(Configuration::FIELD_STATUS_IS_DEFAULT, $fieldArray) || 1 !== (int) $fieldArray[Configuration::FIELD_STATUS_IS_DEFAULT]) {
            return;
        }

        $resolvedId = $this->resolveId($id, $dataHandler);
        if ($resolvedId <= 0) {
            return;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_STATUS);
        $queryBuilder
            ->update(Configuration::TABLE_STATUS)
            ->set(Configuration::FIELD_STATUS_IS_DEFAULT, 0)
            ->where($queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($resolvedId, Connection::PARAM_INT)))
            ->executeStatement()
        ;

        // Bypasses the DataHandler for every row but the one just saved, so the per-record
        // cache tags core would normally emit never fire for them - flush wholesale, the same
        // reasoning DataHandlerHook already applies to a deleted status (a rare admin action).
        $this->cache->flush();
    }

    private function resolveId(string|int $id, DataHandler $dataHandler): int
    {
        if (MathUtility::canBeInterpretedAsInteger($id)) {
            return (int) $id;
        }

        return (int) ($dataHandler->substNEWwithIDs[$id] ?? 0);
    }
}
