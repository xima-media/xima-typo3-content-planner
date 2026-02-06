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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility\Security;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * PermissionUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class PermissionUtilityTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset BE_USER global for each test
        $GLOBALS['BE_USER'] = $this->createMockBackendUser();
    }

    protected function tearDown(): void
    {
        PermissionUtility::resetCache();
        unset($GLOBALS['BE_USER']);
    }

    // ==================== isOwnComment Tests ====================

    public function testIsOwnCommentReturnsTrueForOwnComment(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5]);

        $comment = ['author' => 5];

        self::assertTrue(PermissionUtility::isOwnComment($comment));
    }

    public function testIsOwnCommentReturnsFalseForForeignComment(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5]);

        $comment = ['author' => 10];

        self::assertFalse(PermissionUtility::isOwnComment($comment));
    }

    public function testIsOwnCommentReturnsFalseWhenNoAuthorSet(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5]);

        $comment = [];

        self::assertFalse(PermissionUtility::isOwnComment($comment));
    }

    public function testIsOwnCommentReturnsFalseWhenUserIdIsZero(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 0]);

        $comment = ['author' => 0];

        self::assertFalse(PermissionUtility::isOwnComment($comment));
    }

    // ==================== Admin Bypass Tests ====================

    public function testAdminHasFullAccess(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(true);

        self::assertTrue(PermissionUtility::canChangeStatus());
        self::assertTrue(PermissionUtility::canUnsetStatus());
        self::assertTrue(PermissionUtility::canResolveComment());
        self::assertTrue(PermissionUtility::canReassign());
        self::assertTrue(PermissionUtility::canAssignOtherUser());
        self::assertTrue(PermissionUtility::isStatusAllowedForUser(1));
        self::assertTrue(PermissionUtility::isTableAllowedForUser('pages'));
    }

    // ==================== checkContentStatusVisibility Tests ====================

    public function testCheckContentStatusVisibilityReturnsFalseWhenHidden(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(true, [
            'tx_ximatypo3contentplanner_hide' => 1,
        ]);

        self::assertFalse(PermissionUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityReturnsTrueForAdminNotHidden(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(true, [
            'tx_ximatypo3contentplanner_hide' => 0,
        ]);

        self::assertTrue(PermissionUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityReturnsFalseWithoutPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, [], []);

        self::assertFalse(PermissionUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityReturnsTrueWithPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, [], [
            'custom_options:tx_ximatypo3contentplanner:content-status' => true,
        ]);

        self::assertTrue(PermissionUtility::checkContentStatusVisibility());
    }

    // ==================== hasFullAccess Tests ====================

    public function testHasFullAccessReturnsTrueWithPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, [], [
            'custom_options:tx_ximatypo3contentplanner:full-access' => true,
        ]);

        self::assertTrue(PermissionUtility::hasFullAccess());
    }

    public function testHasFullAccessReturnsFalseWithoutPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, [], []);

        self::assertFalse(PermissionUtility::hasFullAccess());
    }

    // ==================== Full Access Tests ====================

    public function testFullAccessPermissionGrantsAllPermissions(): void
    {
        // User has content-status (now labeled "Full Access") -> full access to everything
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5], [
            'custom_options:tx_ximatypo3contentplanner:content-status' => true,
        ]);

        self::assertTrue(PermissionUtility::hasFullAccess());
        self::assertTrue(PermissionUtility::canChangeStatus());
        self::assertTrue(PermissionUtility::canUnsetStatus());
        self::assertTrue(PermissionUtility::canResolveComment());
        self::assertTrue(PermissionUtility::canEditComment(['author' => 5]));
        self::assertTrue(PermissionUtility::canEditComment(['author' => 10]));
        self::assertTrue(PermissionUtility::canDeleteComment(['author' => 5]));
    }

    public function testViewOnlyPermissionGrantsVisibilityButNotActions(): void
    {
        // User has only view-only -> can see but no actions
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
        ]);

        self::assertTrue(PermissionUtility::checkContentStatusVisibility());
        self::assertFalse(PermissionUtility::hasFullAccess());
        self::assertFalse(PermissionUtility::canChangeStatus());
        self::assertFalse(PermissionUtility::canUnsetStatus());
    }

    public function testViewOnlyWithGranularPermissions(): void
    {
        // User has view-only AND a granular permission -> can see and perform specific action
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'custom_options:tx_ximatypo3contentplanner:status-change' => true,
        ]);

        self::assertTrue(PermissionUtility::checkContentStatusVisibility());
        self::assertFalse(PermissionUtility::hasFullAccess());
        self::assertTrue(PermissionUtility::canChangeStatus()); // Has this permission
        self::assertFalse(PermissionUtility::canUnsetStatus()); // Does NOT have this permission
    }

    // ==================== canEditComment Tests ====================

    public function testCanEditOwnCommentWithPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'custom_options:tx_ximatypo3contentplanner:comment-edit-own' => true,
        ]);

        $comment = ['author' => 5];

        self::assertTrue(PermissionUtility::canEditComment($comment));
    }

    public function testCanNotEditOwnCommentWithoutPermission(): void
    {
        // User has view-only and status-change, but NOT comment-edit-own
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'custom_options:tx_ximatypo3contentplanner:status-change' => true,
        ]);

        $comment = ['author' => 5];

        self::assertFalse(PermissionUtility::canEditComment($comment));
    }

    public function testCanEditForeignCommentWithPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'custom_options:tx_ximatypo3contentplanner:comment-edit-foreign' => true,
        ]);

        $comment = ['author' => 10];

        self::assertTrue(PermissionUtility::canEditComment($comment));
    }

    public function testCanNotEditForeignCommentWithoutPermission(): void
    {
        // User has view-only and comment-edit-own, but NOT comment-edit-foreign
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'custom_options:tx_ximatypo3contentplanner:comment-edit-own' => true,
        ]);

        $comment = ['author' => 10];

        self::assertFalse(PermissionUtility::canEditComment($comment));
    }

    // ==================== canDeleteComment Tests ====================

    public function testCanDeleteOwnCommentWithPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'custom_options:tx_ximatypo3contentplanner:comment-delete-own' => true,
        ]);

        $comment = ['author' => 5];

        self::assertTrue(PermissionUtility::canDeleteComment($comment));
    }

    public function testCanNotDeleteOwnCommentWithoutPermission(): void
    {
        // User has view-only and status-change, but NOT comment-delete-own
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'custom_options:tx_ximatypo3contentplanner:status-change' => true,
        ]);

        $comment = ['author' => 5];

        self::assertFalse(PermissionUtility::canDeleteComment($comment));
    }

    public function testCanDeleteForeignCommentWithPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, ['uid' => 5], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'custom_options:tx_ximatypo3contentplanner:comment-delete-foreign' => true,
        ]);

        $comment = ['author' => 10];

        self::assertTrue(PermissionUtility::canDeleteComment($comment));
    }

    // ==================== canCreateComment Tests ====================

    public function testCanCreateCommentReturnsTrueForAdmin(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(true);

        self::assertTrue(PermissionUtility::canCreateComment());
    }

    public function testCanCreateCommentReturnsTrueWithFullAccess(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, [], [
            'custom_options:tx_ximatypo3contentplanner:content-status' => true,
            'tables_modify:tx_ximatypo3contentplanner_comment' => true,
        ]);

        self::assertTrue(PermissionUtility::canCreateComment());
    }

    public function testCanCreateCommentReturnsTrueWithPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, [], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'custom_options:tx_ximatypo3contentplanner:comment-create' => true,
            'tables_modify:tx_ximatypo3contentplanner_comment' => true,
        ]);

        self::assertTrue(PermissionUtility::canCreateComment());
    }

    public function testCanCreateCommentReturnsFalseWithoutPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, [], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'tables_modify:tx_ximatypo3contentplanner_comment' => true,
        ]);

        self::assertFalse(PermissionUtility::canCreateComment());
    }

    public function testCanCreateCommentReturnsFalseWithoutTablesModify(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, [], [
            'custom_options:tx_ximatypo3contentplanner:view-only' => true,
            'custom_options:tx_ximatypo3contentplanner:comment-create' => true,
        ]);

        self::assertFalse(PermissionUtility::canCreateComment());
    }

    // ==================== canAssignSelf Tests ====================

    public function testCanAssignSelfReturnsTrueWithVisibility(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, [], [
            'custom_options:tx_ximatypo3contentplanner:content-status' => true,
        ]);

        self::assertTrue(PermissionUtility::canAssignSelf());
    }

    public function testCanAssignSelfReturnsFalseWithoutVisibility(): void
    {
        $GLOBALS['BE_USER'] = $this->createMockBackendUser(false, [], []);

        self::assertFalse(PermissionUtility::canAssignSelf());
    }

    /**
     * @return object
     */
    private function createMockBackendUser(bool $isAdmin = false, array $user = [], array $permissions = [])
    {
        return new class($isAdmin, $user, $permissions) {
            /** @var array<string, mixed> */
            public array $user;

            /**
             * @param array<string, mixed> $user
             * @param array<string, bool>  $permissions
             */
            public function __construct(private readonly bool $isAdmin, array $user, private array $permissions)
            {
                $this->user = array_merge([
                    'uid' => 1,
                    'username' => 'test_user',
                    'usergroup' => '1,2',
                    'tx_ximatypo3contentplanner_hide' => 0,
                ], $user);
            }

            public function isAdmin(): bool
            {
                return $this->isAdmin;
            }

            public function check(string $type, string $permission): bool
            {
                if ($this->isAdmin) {
                    return true;
                }

                return $this->permissions[$type.':'.$permission] ?? false;
            }

            public function getUserId(): int
            {
                return (int) ($this->user['uid'] ?? 0);
            }
        };
    }
}
