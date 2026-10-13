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
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{AssignmentHistoryRepository, BackendUserRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Data\ContentUtility;

use function is_array;

/**
 * AssigneeContextService.
 *
 * Describes a record's current assignee for the assignee tab of the record modal: who it is and,
 * when the history knows it, since when and by whom the assignment was made.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class AssigneeContextService
{
    public function __construct(
        private readonly BackendUserRepository $backendUserRepository,
        private readonly AssignmentHistoryRepository $assignmentHistoryRepository,
    ) {}

    /**
     * @return array{uid: int, name: string, since: int|null, by: string}|null
     *
     * @throws Exception
     */
    public function describe(string $table, int $uid, int $assignee): ?array
    {
        if ($assignee <= 0) {
            return null;
        }

        $user = $this->backendUserRepository->findByUid($assignee);
        if (!is_array($user)) {
            return null;
        }

        $assignment = $this->assignmentHistoryRepository->findLatest($table, $uid, $assignee);
        $author = null === $assignment ? false : $this->backendUserRepository->findByUid($assignment['userUid']);

        return [
            'uid' => $assignee,
            'name' => ContentUtility::generateDisplayName($user),
            'since' => $assignment['timestamp'] ?? null,
            'by' => is_array($author) ? ContentUtility::generateDisplayName($author) : '',
        ];
    }
}
