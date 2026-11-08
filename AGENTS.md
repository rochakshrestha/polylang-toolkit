# Agent rules: Toolkit for Polylang

Free GPL companion plugin that fills gaps in **free** Polylang. Not affiliated with WP SYNTEX / Polylang. Read `docs/PLAN.md` for the why; this file is the how.

## Ground rules (reviewed on every PR)
1. **Clean-room.** Never read, copy or port Polylang Pro code. Use only free Polylang's public API (`pll_*` in `api.php`, `pll_init`, `pll_language_defined`, `pll_rewrite_rules`, `PLL()->model`, `PLL()->links_model`). Hooks and behaviour must be listed in `docs/polylang-api.md` before you rely on them. Polylang internals are touched only in `src/Polylang/Adapter.php`.
2. **Extend, don't duplicate.** Every module spec opens with `Delta over free Polylang:`. Never re-copy what Polylang's sync already copies. Never print a second hreflang set (filter `pll_rel_hreflang_attributes`).
3. **Never break the site.** Polylang missing or too old: admin notice, nothing loads, no fatal. `POLYLANG_PRO` defined: overlapping modules switch off with a notice.
4. **Respectful wording.** Never "Pro features for free" or "unlock Pro". Keep the non-affiliation disclaimer.
5. Prefix `tkpll_`, namespace `ToolkitForPolylang\`, text domain `toolkit-for-polylang`. All strings translatable, admin UI accessible.
6. Every module has an on/off switch; all off = zero behaviour change. Rewrite rules are flushed once on change, never per request.
7. Writes need a nonce and a capability check; input sanitized, output escaped.

## Workflow: spec, tests, code (three PRs)
1. **Spec.** `docs/specs/<module>.md` with `Status: draft|approved|implemented` on its own line and criteria written as `- **DUP-01** …`. The owner approves it by merging a PR that sets `Status: approved`.
2. **Tests (PR stays green).** Write tests for every criterion, marked **pending** so CI does not run them yet: `@spec DUP-01` plus `@group pending` in the PHPUnit docblock, and `test.fixme( 'DUP-01 …' )` in Playwright. Gate 5 requires every ID of an `approved` spec to be cited, and pending citations count.
3. **Code.** Implement, then remove the pending markers (delete the `@group pending` line, turn `test.fixme(` into `test(`), set the spec to `Status: implemented`, and make everything pass. Removing pending markers is the **only** test change a code PR may make; the protected-path check allows exactly that. For an `implemented` spec, gate 5 requires a non-pending test for every ID and rejects any pending one. If a test or criterion looks wrong, stop and say so in the PR.

Citations count only in a test's own docblock (PHPUnit) or test title (Playwright); comments elsewhere do not. `test.skip(` counts as pending, never as coverage. PHPUnit fails on skipped or incomplete tests.

Protected paths (CODEOWNERS): `docs/specs/`, `docs/polylang-api.md`, `tests/`, `bin/`, `.github/`, `phpstan.neon`, `phpcs.xml.dist`, `phpunit.xml.dist`, `playwright.config.ts`, `AGENTS.md`. A PR touching these and product code fails unless labelled `spec-change` (or `bootstrap`).

## Commands
| Task | Command |
|---|---|
| Install | `composer install && npm ci` |
| Start env (needs Docker) | `npm run env:start:coverage` |
| Everything (definition of done) | `npm run benchmark` (or `bin/benchmark`) |
| Static gates only | `bin/benchmark --quick` |
| Lint / fix | `composer lint` / `composer lint:fix` |
| Static analysis | `composer analyse` |
| PHPUnit | `npm run test:php` |
| E2E | `npm run test:e2e` |
| Spec traceability | `npm run check:specs` |

**Tests and languages.** `tests/phpunit/bootstrap.php` defines `PLL_ADMIN` so Polylang builds its admin context (otherwise a PHPUnit run gets no `PLL()`, no `pll_*` API and no `pll_init`). The WordPress test library reinstalls the test database, so no Polylang languages exist in PHPUnit. Create the languages a test needs in `set_up()` (`PLL()->model->add_language()`); the dev environment from `bin/wp-env-after-start.sh` has en/fr/ne for Playwright.

## Definition of done
`bin/benchmark` passes (see `docs/PLAN.md` for the 15 gates). Paste `build/benchmark-summary.md` into the PR description along with the spec IDs covered.

## Layout
`src/Plugin.php` boot; `src/Dependency.php` pure environment check; `src/Modules/` registry + one folder per module implementing `ModuleInterface`; `src/Admin/` settings and notices; `src/Polylang/Adapter.php` the only Polylang-internals touchpoint; `uninstall.php` removes `tkpll_*` data only.
