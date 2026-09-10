import { existsSync } from 'node:fs';
import { typo3BinPath, typo3Cli } from '@konradmichalik/ptu';

/**
 * Re-seeds deterministic demo content (see #312) before every suite run, so
 * specs assert against a known state rather than whatever a previous local
 * run left behind.
 *
 * Fails fast with a clear message when this instance was never provisioned
 * via `ddev install` - the compiled TYPO3 CLI binary is missing in that case,
 * and letting execFileSync surface its own "ENOENT" would be far less
 * obvious about the fix.
 */
export default function globalSetup(): void {
  const binPath = typo3BinPath();
  if (!existsSync(binPath)) {
    throw new Error(
      `TYPO3 CLI binary not found at "${binPath}". This instance was not provisioned via `
        + '`ddev install` - run `ddev install 14` (or the matching TYPO3_VERSION) before '
        + 'running the e2e suite.',
    );
  }

  typo3Cli(['content-planner:seed-demo-content']);
}
