# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Wp-testing is a WordPress plugin for building psychological tests/quizzes: questions with answers, scored against scales, and formulas (e.g. `extraversion > 50%`) that resolve to results. Tests behave like WordPress posts (custom post type), with scales/results/global-answers managed like taxonomies.

## Commands

```bash
composer install                # install PHP dependencies (PHP ^8.3 required)
vendor/bin/phpunit               # run the full PHPUnit suite
vendor/bin/phpunit tests/phpunit/Model/TestTest.php                       # run one test file
vendor/bin/phpunit --filter testAddQuestion tests/phpunit/Model/TestTest.php  # run one test method
```

CI (`.github/workflows/tests.yml`) runs exactly `composer install --prefer-dist --no-progress` then `vendor/bin/phpunit` on PHP 8.3. Treat that as the source of truth for what "passing" means.

Note: `.travis.yml` and `tests/mocha/*` (CasperJS/PhantomJS end-to-end tests, Node 0.10-era) are legacy holdovers from when the plugin supported PHP 5.2–7.3 and old WordPress versions; they are not run by current CI and their tooling is largely unrunnable on a modern machine. Don't treat them as the current test story.

PHPUnit tests need a real MySQL/MariaDB database (configured via `db/ruckusing.conf.php`, overridable with `db/ruckusing.conf.local.php`) — `tests/phpunit/TestCase.php` builds a real `WpTesting_WordPressFacade`/`WpTesting_Facade` against it, not a fully mocked stack. The devcontainer (`.devcontainer/`) provisions a MariaDB service for this.

## Architecture

**Entry point:** `wp-testing.php` loads `vendor/autoload.php`, then `src/bootstrap.php` (which hand-requires a handful of core interfaces before the classmap autoloader is fully available), then instantiates `WpTesting_Facade` wrapping a `WpTesting_WordPressFacade`.

**Naming/autoloading:** classes are `WpTesting_<Path>_<To>_<Class>` mapping 1:1 to `src/<Path>/<To>/<Class>.php` (PSR-0-style, predates namespaces). Composer classmaps `src/` and `db/migrations/wp_testing/` directly — there's no PSR-4 prefix logic to worry about, just match the directory path to the class name.

**Layering** (a request generally flows top to bottom):
- `WpTesting_Facade` (`src/Facade.php`) — the composition root. Registers all WordPress hooks/filters in `registerWordPressHooks()`, wires up addons, and is the object passed around as the plugin's context.
- `WpTesting_WordPressFacade` (`src/WordPressFacade.php`, ~1750 lines) — the *only* boundary that should call raw `wp_*`/WordPress global functions. Everything else in the plugin goes through this facade instead of touching WordPress core API directly, which is what makes `tests/phpunit/Mock/WordPressFacade.php` possible for testing.
- `Doer/` — action/controller-like classes that perform a unit of work in response to a hook (e.g. `Installer`, `TestPasser`, `PostBrowser`, `ShortcodesRegistrator`). Named for what they *do*, not what they represent.
- `Model/` — ORM entities (`Test`, `Question`, `Answer`, `Scale`, `Result`, `Passing`, `Formula`, ...), all extending `WpTesting_Model_AbstractModel` which itself extends `fActiveRecord` from the **Flourish** ORM (`flourish/flourish`, a fork of the abandoned flourishlib.com project). Column aliasing, deadlock-retry storage, and cached column-to-setter reflection live in `AbstractModel`.
- `Query/` — query-builder-ish helpers (`WpTesting_Query_AbstractQuery` and subclasses) that wrap Flourish's `fRecordSet`/`fORMDatabase` for read paths.
- `Template/` — view rendering, split by concern (`Test/`, `Passing/`, `Feedback/`, `Shortcode/`, `Abstract/`, `AbstractEditor/`).
- `Component/` — cross-cutting infrastructure: `Loader` (the addon-prefix autoloader), `Database/` (Flourish DB adapter + Ruckusing migration runner), `Formatter/`, `StepStrategy/`.
- `Addon/` — the interfaces (`IAddon`, `IFacade`, `IWordPressFacade`) and `Updater` that let paid add-on plugins hook into this plugin. `WpTesting_Facade::registerAddon()` contains deliberately obfuscated code that self-checks addon files — this is intentional (anti-tampering for the paid-addon business model); don't "clean it up" or try to deobfuscate/refactor it.

**Database migrations:** managed by `ruckusing/ruckusing-migrations` under `db/migrations/wp_testing/`, configured in `db/ruckusing.conf.php`. Ad-hoc/manual SQL used during development lives in `db/sql/` — these are not migrations, don't run them as part of normal setup.

**Testing pattern:** `WpTesting_Tests_TestCase` (`tests/phpunit/TestCase.php`) builds a shared `WpTesting_Mock_WordPressFacade` + `WpTesting_Mock_Facade` once per test class (`setUpBeforeClass`) — these mocks (`tests/phpunit/Mock/`) stub the handful of WordPress-specific calls but still hit a real database through Flourish. Most model tests wrap each test in a DB transaction (`BEGIN` in `setUp`, `ROLLBACK` in `tearDown`) via `fORMDatabase::retrieve(...)->translatedExecute(...)` rather than relying on fixtures/factories.

**Formulas:** `Model/Formula.php` + `Model/FormulaVariable/` implement the scale/answer comparison DSL described in the README (`<`, `>`, `<=`, `>=`, `<>`, `AND`, `OR`, parens, arithmetic) that determines which `Result` a respondent gets.
