/**
 * Stable fixture identifiers for the demo content seeded by
 * `content-planner:seed-demo-content` (Tests/Functional/Fixtures/Extensions/demo_content,
 * see #312).
 *
 * The seed command deletes-by-title and recreates on every run, so page
 * uids are never stable across a re-seed - specs must reference fixtures by
 * these titles, not by uid. Keep this file's values in lockstep with the
 * constants of the same name in SeedDemoContentCommand.php; nothing enforces
 * that automatically.
 */

/** Root of the seeded demo tree, kept out of any real content the instance might carry. */
export const DEMO_ROOT_PAGE_TITLE = 'Content Planner E2E Demo';

/** Carries a status, an assignee and one comment - covers all three tracking fields. */
export const DEMO_STATUS_PAGE_TITLE = 'Demo Page With Status';

/** Carries a status only, no assignee and no comments. */
export const DEMO_DRAFT_PAGE_TITLE = 'Demo Page Draft';

export const DEMO_STATUS_TITLE_FOR_STATUS_PAGE = 'Needs review';
export const DEMO_STATUS_TITLE_FOR_DRAFT_PAGE = 'Pending';

/** Matches the ddev addon's admin bootstrap (TYPO3_SETUP_ADMIN_USERNAME). */
export const DEMO_ASSIGNEE_USERNAME = 'admin';

/**
 * Plain text on purpose, unlike the PHP constant of the same name: the
 * comment partial renders the stored value with `f:format.raw()`, so a DOM
 * assertion (`toHaveText()`/`textContent()`) against the rendered comment
 * sees this text with the `<p>` wrapper stripped, not the raw markup.
 */
export const DEMO_COMMENT_CONTENT = 'Demo comment seeded for e2e tests.';
