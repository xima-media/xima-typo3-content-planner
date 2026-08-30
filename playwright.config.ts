/**
 * The e2e suite is the only one that needs a running DDEV instance, and the hostname below
 * is tied to the project name in .ddev/config.yaml.
 *
 * DDEV keys projects by name globally. A second checkout of this repository (a git worktree,
 * for example) that runs `ddev start` therefore takes the name over and stops the main
 * checkout's instance; if that worktree is later removed, the global registry keeps pointing
 * at the missing directory and every `ddev` command fails with
 * `stat .../worktrees/...: no such file or directory`.
 *
 * So before running DDEV outside the main checkout, give it its own name and adjust the
 * hostname accordingly:
 *
 *   ddev config --project-name=cp-e2e-<issue>
 *   ddev stop --unlist <name>      # to clean up a stale registration
 */
import { defineTypo3PlaywrightConfig } from '@konradmichalik/ptu';

export default defineTypo3PlaywrightConfig({
  hostname: 'xima-typo3-content-planner.ddev.site',
  defaultVersion: '14',
  testDir: './Tests/Playwright',
});
