# .ddev/.setup/project.sh — repo-owned customizations for `ddev install`.
#
# Sourced by utils.sh on every install; survives `ddev add-on get` upgrades
# since it isn't managed by the ddev-typo3-multi-version-extension add-on
# itself. See .setup/project.sh.example in that add-on for the full option
# reference.

# Registers Tests/Functional/Fixtures/Extensions/demo_content as an
# additional Composer package discoverable during install (see #312).
FIXTURE_EXTENSION_DIRS=(
    'Tests/Functional/Fixtures/Extensions'
)

# Symlinking alone only makes demo_content discoverable - it still needs to
# be required by name to actually get installed and activated.
ADDITIONAL_PACKAGES=(
    'xima/xima-typo3-content-planner-demo-content:*@dev'
)
