# AGENTS.md

Guidance for coding agents working in this repository.

## Project overview

`xima/xima-typo3-content-planner` is a TYPO3 extension (`xima_typo3_content_planner`) for content planning and migration workflows. It adds status management, user assignment and comments (with single-level replies) to pages and other records.

- PHP: `~8.2 || ~8.3 || ~8.4 || ~8.5`
- TYPO3: `^13.4 || ^14.3`
- Namespace: `Xima\XimaTypo3ContentPlanner\` maps to `Classes/`

## Structure

- `Classes/Domain/`: models (Status, BackendUser, DTOs such as StatusItem, CommentItem, HistoryItem) and repositories
- `Classes/Controller/`, `Classes/Manager/`: backend controllers and business logic (StatusChangeManager, StatusSelectionManager)
- `Classes/Service/`: services by concern (ContentModifier, SelectionBuilder, Header, FileList)
- `Classes/EventListener/`, `Classes/Hooks/`, `Classes/Middleware/`: integration into the TYPO3 backend
- `Classes/Event/`: PSR-14 events (StatusChangeEvent, PrepareStatusSelectionEvent, CommentCreatedEvent, CommentResolvedEvent)
- `Classes/Utility/`: helpers (`Compatibility/`, `Data/`, `Rendering/`, `Routing/`, `Security/`); `ExtensionUtility.php` is public API and stays in place
- `Classes/Widgets/`, `Classes/ViewHelpers/`, `Classes/Form/`, `Classes/Command/`, `Classes/Backend/`, `Classes/Integration/`, `Classes/Configuration.php`
- `Configuration/`: backend routes, TCA, dashboard, services
- `Resources/`: templates, language files, public assets
- `Tests/Unit/`, `Tests/Functional/`: PHPUnit suites
- `Tests/CGL/`: separate Composer project with code style and static analysis tools. Its `composer.lock` is committed and updated by Renovate, so tool releases only reach CI through a Renovate PR
- `Documentation/`: TYPO3 documentation source
- `.ddev/`: DDEV setup with TYPO3 13 and 14 instances

Some files are TYPO3 v14 only (for example `AfterFileStorageTreeItemsPreparedListener.php`). The v13 PHPStan config excludes them, the v14 config analyses them.

## Development commands

```bash
ddev start
ddev composer install
ddev install 13                # or 14, or all
ddev launch                    # open the development site
ddev 13 typo3 cache:flush      # or 14
ddev all typo3 database:updateschema
```

The DDEV project name is fixed in `.ddev/config.yaml`. Starting DDEV from a second checkout (for example a git worktree) takes over the name and stops the first instance. Give such a checkout its own name with `ddev config --project-name=<name>`.

## Testing

```bash
ddev composer test             # unit + functional
ddev composer test:unit        # phpunit.xml, no TYPO3 bootstrap
ddev composer test:functional  # phpunit.functional.xml, real TYPO3 on SQLite
ddev composer test:coverage    # both suites, merged with phpcov into .Build/coverage
```

- Functional tests extend `Tests\Functional\AbstractFunctionalTestCase` (loads the extension, provides `loginBackendUser()` and `setUpBackendRequest()`). CSV fixtures live in `Fixtures/` subfolders.
- `RecordRepository::findAllByFilter()` builds raw UNION SQL that is invalid on SQLite. Its tests skip on SQLite and run in CI against MySQL and MariaDB. Locally against the DDEV MariaDB: `ddev exec env typo3DatabaseDriver=mysqli typo3DatabaseHost=db typo3DatabaseUsername=root typo3DatabasePassword=root typo3DatabaseName=ft vendor/bin/phpunit -c phpunit.functional.xml`
- The testing framework sets `ONLY_FULL_GROUP_BY` on MySQL and MariaDB, so `SELECT table.* ... GROUP BY uid` passes on SQLite but fails on MariaDB.
- CI (`.github/workflows/tests.yml`, reusable workflow) runs unit and functional tests on SQLite with PHP 8.2 and 8.5, TYPO3 13.4 and 14.3, highest and lowest dependencies, plus the functional suite on MySQL 8.4 and MariaDB 11.4 with PHP 8.5 and highest dependencies.
- CI runs on pushes to `main` and `renovate/**` and on pull requests, so a feature branch is checked once it has a pull request.

## Code style and static analysis

Tools live in `Tests/CGL/` and run through `ddev cgl`:

```bash
ddev cgl lint                  # composer, editorconfig, language, php, typoscript
ddev cgl fix                   # composer, editorconfig, php
ddev cgl sca                   # PHPStan level 6 against TYPO3 v13
ddev cgl migration             # Rector
ddev cgl analyze               # composer-dependency-analyser
```

- Individual targets: `lint:composer`, `lint:editorconfig`, `lint:language`, `lint:php`, `lint:typoscript`, `fix:composer`, `fix:editorconfig`, `fix:php`, `sca:php`, `sca:php:v14`, `migration:rector`, `analyze:dependencies`
- PHPStan analyses `Classes/`, `Configuration/`, `Resources/` and `Tests/` against whatever TYPO3 the root `vendor/` holds. `phpstan.neon` with `phpstan-baseline.neon` is for v13 (the locked version), `phpstan-v14.neon` with `phpstan-baseline-v14.neon` for v14, both share `phpstan.base.neon`. Unmatched baseline entries fail the run, so a baseline only holds errors of its own TYPO3 version
- `sca:php:v14` needs a root `vendor/` installed with `composer update --with=typo3/cms-core:^14.3`. The local root `vendor/` is v13, so regenerate the v14 baseline in a copy of the repository or let CI report it
- Config files: `Tests/CGL/phpstan.neon`, `Tests/CGL/phpstan-v14.neon`, `Tests/CGL/rector.php`, `Tests/CGL/.php-cs-fixer.php`
- Strict types are required (`declare(strict_types=1);`)
- Use PSR-7 request and response objects, no superglobals and no `header()`
- No `var_dump()` or `debug()`
- The CGL workflow (`.github/workflows/cgl.yml`) also runs PHPStan against TYPO3 v14 (`sca:php:v14`)

## Extension points

Comments are threaded through `parent_uid`. Reply-to-reply is flattened to one level in `DataHandlerHook::flattenNestedReply()`, and deleting a root comment deletes its replies. In Fluid, `{comment.replies}` resolves through `getReplies()`, not the public property.

To add status tracking to another record type, add the TCA tab and register the table:

```php
// Configuration/TCA/Overrides/tx_news_domain_model_news.php
\Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility::addContentPlannerTabToTCA('tx_news_domain_model_news');

// ext_localconf.php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['xima_typo3_content_planner']['registerAdditionalRecordTables'][] = 'tx_news_domain_model_news';
```

## Git workflow

- Commit format: `<type>: <description>` with type one of `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf`, `ci`
- Do not add co-author trailers
- Run lint, static analysis and tests before opening a pull request
