# .ddev/.setup/hooks/post-install.sh — sourced by utils.sh's run_hook()
# after every `ddev install <version>` (composer and classic alike), right
# after update_typo3 (schema update + cache flush). See #312.
#
# Re-seeds deterministic e2e demo content (a page tree carrying
# content-planner status, assignee and comment fixtures) so a fresh install
# always ends up in the same known state Tests/Playwright specs assert
# against. Also re-run by Tests/Playwright/global-setup.ts before every
# suite start, so this hook mainly saves a manual seed step right after
# provisioning.

if [ "${MODE:-composer}" = "classic" ]; then
    # demo_content is registered via FIXTURE_EXTENSION_DIRS/ADDITIONAL_PACKAGES
    # (.ddev/.setup/project.sh), both Composer-mode-only mechanisms - classic
    # mode never symlinks or requires it, so the command does not exist there.
    message yellow "Skipping demo content seeding: demo_content fixture extension is Composer-mode only."
else
    $TYPO3_BIN content-planner:seed-demo-content
fi
