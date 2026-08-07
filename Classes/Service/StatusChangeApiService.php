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

namespace Xima\XimaTypo3ContentPlanner\Service;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;

/**
 * StatusChangeApiService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusChangeApiService
{
    /*
     * Outcomes of a change attempt. "Stripped" is its own case because the pipeline
     * silently drops the fields rather than reporting an error.
     */
    public const OUTCOME_APPLIED = 'applied';
    public const OUTCOME_STRIPPED = 'stripped';
    public const OUTCOME_UNKNOWN_RECORD = 'unknown-record';
    public const OUTCOME_TABLE_NOT_ALLOWED = 'table-not-allowed';

    public function __construct(private readonly RecordRepository $recordRepository) {}

    /**
     * Applies a status change through the DataHandler, so the whole backend pipeline runs:
     * StatusChangeManager's permission stripping, the status reset handling, auto
     * assignment, the comment relation sync and StatusChangeEvent. Writing the field
     * directly would skip all of it and let the API drift from the backend.
     *
     * Whether the change took effect is decided by **re-reading the record**, not by
     * re-implementing the permission checks: StatusChangeManager unsets the fields
     * silently, so the only honest signal is what actually landed in the database.
     *
     * @return array{outcome: string, statusBefore: int|null, statusAfter: int|null}
     *
     * @throws Exception
     */
    public function apply(string $table, int $uid, ?int $requestedStatus): array
    {
        if (!$this->isTableUsable($table)) {
            return ['outcome' => self::OUTCOME_TABLE_NOT_ALLOWED, 'statusBefore' => null, 'statusAfter' => null];
        }

        $record = $this->recordRepository->findByUid($table, $uid, ignoreVisibilityRestriction: true);
        if (!is_array($record) || !$this->isRecordAccessible($table, $record)) {
            return ['outcome' => self::OUTCOME_UNKNOWN_RECORD, 'statusBefore' => null, 'statusAfter' => null];
        }

        $before = $this->normalizeStatus($record[Configuration::FIELD_STATUS] ?? null);

        $this->processDatamap($table, $uid, $requestedStatus);

        $after = $this->currentStatus($table, $uid);

        return [
            'outcome' => $after === $requestedStatus ? self::OUTCOME_APPLIED : self::OUTCOME_STRIPPED,
            'statusBefore' => $before,
            'statusAfter' => $after,
        ];
    }

    protected function isTableUsable(string $table): bool
    {
        return ExtensionUtility::isRegisteredRecordTable($table) && PermissionUtility::isTableAllowedForUser($table);
    }

    /**
     * @param array<string, mixed> $record
     */
    protected function isRecordAccessible(string $table, array $record): bool
    {
        return PermissionUtility::checkAccessForRecord($table, $record);
    }

    protected function processDatamap(string $table, int $uid, ?int $requestedStatus): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            [$table => [$uid => [Configuration::FIELD_STATUS => $requestedStatus]]],
            [],
        );
        $dataHandler->process_datamap();
    }

    /**
     * @throws Exception
     */
    private function currentStatus(string $table, int $uid): ?int
    {
        $record = $this->recordRepository->findByUid($table, $uid, ignoreVisibilityRestriction: true);

        return is_array($record) ? $this->normalizeStatus($record[Configuration::FIELD_STATUS] ?? null) : null;
    }

    /**
     * The field is nullable, but a reset can land as either NULL or 0 depending on the
     * path taken, and both mean "no status".
     */
    private function normalizeStatus(mixed $value): ?int
    {
        if (null === $value || 0 === (int) $value) {
            return null;
        }

        return (int) $value;
    }
}
