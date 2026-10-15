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

namespace Xima\XimaTypo3ContentPlanner\Controller;

use Doctrine\DBAL\Exception;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;
use Xima\XimaTypo3ContentPlanner\Utility\Data\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\{AssetUtility, IconUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function count;
use function is_array;

/**
 * RecordModalController.
 *
 * Feeds the context row of the record modal (record-modal.js): which record the modal is about,
 * its status with the statuses the user may switch to, and the counts shown on the tabs. Changing
 * the status goes through the DataHandler like every other status change, so the hook applies the
 * same permission checks, auto-assignment and notifications.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class RecordModalController
{
    public function __construct(
        private RecordRepository $recordRepository,
        private StatusRepository $statusRepository,
        private CommentRepository $commentRepository,
        private WatcherService $watcherService,
    ) {}

    /**
     * @throws Exception
     */
    public function contextAction(ServerRequestInterface $request): ResponseInterface
    {
        $table = (string) ($request->getQueryParams()['table'] ?? '');
        $uid = (int) ($request->getQueryParams()['uid'] ?? 0);

        $record = $this->resolveRecord($table, $uid);

        if ($record instanceof ResponseInterface) {
            return $record;
        }

        return new JsonResponse([
            ...$this->buildContext($table, $uid, $record),
            // The frame is built before any tab loads, so its stylesheet has to be there first.
            'stylesheets' => [AssetUtility::getPublicResourcePath('EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/RecordModal.css')],
        ]);
    }

    /**
     * @throws Exception
     */
    public function statusAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $table = (string) ($body['table'] ?? '');
        $uid = (int) ($body['uid'] ?? 0);
        $statusUid = (int) ($body['status'] ?? 0);

        $record = $this->resolveRecord($table, $uid);
        if ($record instanceof ResponseInterface) {
            return $record;
        }

        if (!$this->mayApply($statusUid)) {
            return new JsonResponse(['error' => 'Status not allowed'], 400);
        }

        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([$table => [$uid => [Configuration::FIELD_STATUS => $statusUid]]], []);
        $dataHandler->process_datamap();

        if ([] !== $dataHandler->errorLog) {
            return new JsonResponse(['error' => 'Failed to change the status'], 500);
        }

        return new JsonResponse($this->buildContext($table, $uid, $this->recordRepository->findByUid($table, $uid, true) ?: []));
    }

    /**
     * @return array<string, mixed>|ResponseInterface the record row, or the error response
     *
     * @throws Exception
     */
    private function resolveRecord(string $table, int $uid): array|ResponseInterface
    {
        if ('' === $table || 0 === $uid) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $record = $this->recordRepository->findByUid($table, $uid, true);
        if (!$record) {
            return new JsonResponse(['error' => 'Record not found'], 404);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        return $record;
    }

    private function mayApply(int $statusUid): bool
    {
        if (0 === $statusUid) {
            return PermissionUtility::canUnsetStatus();
        }

        return PermissionUtility::canChangeStatus() && PermissionUtility::isStatusAllowedForUser($statusUid) && null !== $this->statusRepository->findByUid($statusUid);
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function buildContext(string $table, int $uid, array $record): array
    {
        $currentUid = (int) ($record[Configuration::FIELD_STATUS] ?? 0);
        $current = ContentUtility::getStatus($currentUid);

        $statuses = [];
        if (PermissionUtility::canChangeStatus()) {
            foreach ($this->statusRepository->findAll() as $status) {
                if (PermissionUtility::isStatusAllowedForUser($status->getUid())) {
                    $statuses[] = [
                        'uid' => $status->getUid(),
                        'title' => $status->getTitle(),
                        'icon' => IconUtility::getIconByStatus($status, true),
                        'color' => $status->getColor(),
                        'current' => $status->getUid() === $currentUid,
                    ];
                }
            }
        }

        return [
            'title' => BackendUtility::getRecordTitle($table, BackendUtility::getRecord($table, $uid) ?? []),
            'typeLabel' => $this->getLanguageService()->sL($GLOBALS['TCA'][$table]['ctrl']['title'] ?? $table),
            'reference' => $table.':'.$uid,
            'recordIcon' => IconUtility::getIconByRecord($table, $record, true),
            'status' => null === $current ? null : [
                'uid' => $current->getUid(),
                'title' => $current->getTitle(),
                'icon' => IconUtility::getIconByStatus($current, true),
                'color' => $current->getColor(),
            ],
            'canChangeStatus' => PermissionUtility::canChangeStatus(),
            'canUnsetStatus' => PermissionUtility::canUnsetStatus(),
            'statuses' => $statuses,
            'counts' => [
                'comments' => $this->commentRepository->countAllByRecord($uid, $table),
                'assignee' => $this->getAssigneeName((int) ($record[Configuration::FIELD_ASSIGNEE] ?? 0)),
                'watchers' => $this->watcherService->isWatchable($table) ? count($this->watcherService->getActiveWatchers($table, $uid)) : 0,
            ],
        ];
    }

    private function getAssigneeName(int $assignee): string
    {
        $user = ContentUtility::getBackendUserById($assignee);
        if (!is_array($user)) {
            return '';
        }

        return (string) ($user['realName'] ?: $user['username']);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
