# Toolkit for Polylang — build plan + agent benchmark

## Context
Free Polylang leaves painful gaps (translated slugs, content duplication, sync, media) that push small businesses toward Pro (€99+/yr). We are building a **free, GPL companion plugin** that makes free Polylang more complete — "Essential extras for free Polylang" — while staying respectful of WP Syntex: no Pro code, no "unlock Pro" language, clear non-affiliation, links to official Polylang Pro where relevant. It must also coexist with AI translation add-ons (AutoPoly, EPICWP AI, PolyTranslate AI). It is built mostly by other AI agents, so the repo holds specs, rules and an automated benchmark that tells an agent whether its work is correct.

This plan follows the product brief; where it differs from earlier answers, the brief wins (see "Changes from the earlier draft"). It records decisions; `AGENTS.md` is the how.

**Status:** M0 (foundation) is done and CI is green. M0.5 (hardening the benchmark and the specs, below) is in progress. No module is built yet.

### Decisions
- **Name:** display "Toolkit for Polylang", slug `toolkit-for-polylang`. WordPress.org does not accept plugin names/slugs that *start* with another product's trademark, so "Polylang Toolkit" would be renamed in review; the "… for Polylang" form is accepted and still credits Polylang.
- **Release:** WordPress.org (per brief) → code follows the plugin guidelines and Plugin Check from day one; updates come from wordpress.org (no custom updater). GitHub hosts code, issues and the public roadmap.
- **Floor:** PHP 8.1+, WordPress 6.8+, free Polylang (minimum = current stable minor at M0, one constant). Runtime dependency check, not the `Requires Plugins` header (that header is tied to the free slug and would block sites that move to Pro).
- **Free forever** core; monetization decided later.

### Changes from the earlier draft
- **Machine translation (Google/Gemini)** moves out of Phase 1 to "Phase 2 – optional": the brief positions us as complementary to AI translation add-ons, so Phase 1 instead guarantees compatibility with them. Re-promote if you still want it in the MVP.
- **Block-theme templates, REST, ACF** → Phase 2/3 per brief. **SEO** in Phase 1 is limited to what slugs need: correct translated URLs in hreflang, canonical and Yoast/Rank Math sitemaps.
- **Media handling** added to Phase 1.

## Ground rules (go in AGENTS.md, enforced in review)
1. **Clean-room.** Never read, copy or port Polylang Pro code. Use only free Polylang's public API (`pll_*` in `src/api.php`, `pll_init`, `pll_language_defined`, `pll_rewrite_rules`, `PLL()->model`, `PLL()->links_model`). Other hooks (e.g. `pll_copy_post_metas`, `pll_copy_taxonomies`, `pll_translate_post_meta`, `pll_rel_hreflang_attributes`) are **"verify in inventory"** until `docs/polylang-api.md` confirms them. Any internal Polylang class goes through `src/Polylang/Adapter.php` only.
2. **Extend, don't duplicate.** Free Polylang already syncs taxonomies, custom fields, featured image, page template, menu order, parent, date; supports per-language media; prints hreflang. Every module spec opens with **"Delta over free Polylang:"** naming exactly what it adds and which Polylang hook it extends. Never re-copy what Polylang's sync already copies; never print a second hreflang set.
3. **Never break the site.** Polylang missing/too old → admin notice, nothing loads, no fatal. `POLYLANG_PRO` defined → overlapping modules switch off with a notice pointing to Pro's own feature.
4. **Respectful wording.** "Not affiliated with WP Syntex / Polylang." Never "Pro features for free". Settings page and readme link to official Polylang docs and Pro.
5. Prefix `tkpll_`, namespace `ToolkitForPolylang\`, text domain `toolkit-for-polylang`; all strings translatable; admin UI accessible (labels, keyboard, contrast).
6. Every module has an on/off switch; all off = zero behaviour change. Rewrite rules flushed once on change, never per request.

## Architecture
```
toolkit-for-polylang.php          bootstrap + header
src/Plugin.php                    dependency/Pro check, module registry, boot on pll_init
src/Polylang/Adapter.php          only place touching Polylang internals
src/Admin/Settings.php            Languages → "Toolkit" (tab per module)
src/Modules/<Name>/Module.php     one folder per module, implements ModuleInterface
src/Compat/                       Yoast, Rank Math, AI-addon compatibility shims
uninstall.php                     removes our options/meta only
docs/polylang-api.md              inventory of free Polylang hooks/behaviour (pinned version)
docs/specs/*.md                   one spec per module with acceptance IDs
docs/ROADMAP.md                   public roadmap (Phases 1–3)
tests/phpunit/  tests/e2e/  tests/stubs/
.github/workflows/                ci.yml (matrix), release.yml (wp.org SVN deploy on tag)
AGENTS.md, CLAUDE.md→AGENTS.md    agent rules, commands, definition of done
```

## Phase 1 modules (MVP)
| ID | Module | Delta over free Polylang | Must be spec'd |
|---|---|---|---|
| SLUG | Translated URL slugs | Translate post-type and taxonomy **base** slugs per language (`/en/products/` ↔ `/fr/produits/`), translatable archive/author/search/feed bases; allow translations to **share the same post/page/term slug** | Per-language rewrite rules via `pll_rewrite_rules`; `get_permalink`, term links, language switcher, hreflang, canonical and Yoast/Rank Math sitemaps all emit translated URLs; old URLs 301 to new; conflict rules for shared vs unique slugs |
| DUP | Content duplication | One-click "Duplicate content to translation" when creating a language version: title, content, excerpt (+ featured image and selected meta only where Polylang's sync is off) | Media/linked-post IDs inside blocks swapped for their translations; block + classic editor; no double copy with Polylang sync |
| SYNC | Basic synchronization | Per-field rules after the initial copy: choose individual custom fields (and optionally per post type) to keep synced, beyond Polylang's all-or-nothing options | Clear UI showing what syncs and from where; recursion guard; never touches status/slug/language |
| MEDIA | Improved media | Copy/translate title, alt, caption, description to media translations; option to auto-create media translations on upload; bulk "create missing media translations" | Only when Polylang media support is on; no duplicate files on disk |
| COMPAT | Compatibility | Works alongside AutoPoly, EPICWP AI, PolyTranslate AI, Yoast SEO, Rank Math | Duplication/sync don't overwrite AI-translated content; documented hooks so AI add-ons can trigger after our duplicate |

**Phase 2:** string translation management, basic XLIFF export/import, bulk create translations for CPTs/taxonomies, translation status / missing-translation report, page builder + ACF compatibility, optional machine translation (Google/Gemini, own API key).
**Phase 3:** advanced sync rules, advanced XLIFF, workflow (assignments, notes), REST and Site Editor enhancements.
**Out of scope:** DeepL integration, WooCommerce suite, anything mirroring Pro's proprietary logic.

### Milestones (each task = one PR)
- **M0 Foundation (done):** free-Polylang capability inventory (`docs/polylang-api.md`), scaffold, tooling, wp-env, CI, AGENTS.md, spec drafts, settings page, module registry, dependency/Pro detection, uninstall.
- **M0.5 Hardening (in progress):** see the checklist below.
- **M1 DUP → M2a SLUG base slugs → M2b SLUG shared slugs → M3 MEDIA → M4 SYNC → M5 COMPAT pass.** MEDIA comes before SYNC: it is simpler, and DUP's media replacement is only useful once media translations exist. Translatable archive/author/search/feed bases move to Phase 2.
- **Releases:** tag `0.1.0` on GitHub after M1 so test users have something to install. Submit to WordPress.org after M2a, not at 1.0: review takes weeks and the name is the known risk.
- **M6 Release 1.0:** readme with FAQ + non-affiliation disclaimer, screenshots, `.pot`.
- In parallel (you, not agents): validate Phase 1 with 5–10 free-Polylang users before M6.

### M0.5 checklist
- [x] Gate tags: a criterion tagged `[seo]`, `[coexistence]`, `[performance]` or `[security]` needs a test in that group (gate 5); an empty group gate reports N/A instead of PASS.
- [x] Tests for the spec checker itself (`npm run test:tools`).
- [x] Spec template (`docs/specs/_template.md`); DUP spec rewritten to it and approved by the owner (2026-11-11).
- [ ] Red-first CI job: every new pending test must fail against `main`.
- [ ] `bin/benchmark-selftest`: seeded bad patches, each must fail its declared gate.
- [ ] Playwright fixture failing on console errors and new `debug.log` lines; `classic` project (Classic Editor + classic theme).
- [ ] Module settings contract (`HasSettings`, one settings tab per module), as CORE-11/12.
- [ ] Task queue (`docs/TASKS.md`), decision log (`docs/decisions/`), role separation in AGENTS.md.
- [ ] SLUG, SYNC, MEDIA and COMPAT specs rewritten to the template.
- [ ] Coverage baseline vs `main`; multisite PHPUnit leg; weekly run against Polylang latest.

## How agents work
1. **Spec first.** `docs/specs/<module>.md`, written from `docs/specs/_template.md`: "Delta over free Polylang", hooks used, settings, data stored, then numbered Given/When/Then criteria with gate tags (`SLUG-03 [seo] …`), fixtures, edge cases and non-goals. You approve before code.
2. **Tests second, own PR.** Tests tagged with the ID (`@spec SLUG-03` in PHPUnit, `test('SLUG-03 …')` in Playwright) and marked pending so CI stays green.
3. **Code third.** Separate PR removes the pending markers and makes them pass; it may not edit specs or tests otherwise. If one looks wrong, stop and say so in the PR.
4. **Protected paths** (CODEOWNERS = you): `docs/specs/`, `tests/`, `phpstan.neon`, `phpcs.xml.dist`, `.github/`, `bin/`. CI fails if a PR changes protected paths and `src/` together unless labelled `spec-change`. Exemption: the M0 bootstrap PR.
5. PR description lists spec IDs covered and pastes the `bin/benchmark` summary.

## The benchmark (`bin/benchmark` = definition of done)
Gates 7, 8, 11 and 12 are PHPUnit groups. A group with no test reports **N/A**, never PASS, and gate 5 forces every gate-tagged criterion to have a test in its group.

| # | Gate | Tool | Pass bar |
|---|---|---|---|
| 1 | Coding standards | PHPCS + WPCS 3 + PHPCompatibilityWP (8.1-) | 0 errors/warnings |
| 2 | Static analysis | PHPStan + phpstan-wordpress + Polylang/Yoast stubs (hand-written minimal stubs in `tests/stubs/` allowed, e.g. Rank Math) | level 8, no baseline growth |
| 3 | Integration tests | PHPUnit in wp-env with real free Polylang (+ Yoast / Rank Math where needed) | 100% pass |
| 4 | Coverage | PHPUnit on `src/` | ≥ 80% lines (enforced from M0); "never drops vs `main`" enforced from M1 via `check-coverage.mjs --baseline` |
| 5 | Spec traceability | `bin/check-spec-coverage.mjs` | every `XXX-NN` has ≥1 test; no test cites a missing ID; every gate-tagged criterion has a test in that gate's group |
| 6 | End-to-end | Playwright on wp-env: block theme + classic theme, block + classic editor | 100% pass; no PHP notices, no JS console errors |
| 7 | URL/SEO output | PHPUnit/Playwright | every translated URL resolves (200), old URL 301s; hreflang reciprocal, absolute, unique, one set per page; canonical self-references; sitemaps valid XML with translated URLs |
| 8 | Coexistence | PHPUnit | with Polylang sync options on: no duplicate meta; `POLYLANG_PRO` defined → overlapping modules off; Polylang inactive → no fatal (M0); no duplicate meta and "all modules off → output identical to no plugin" enforced from M1, when the first module ships |
| 9 | AI add-on compat | CI job per listed AI add-on (free versions) + PHPUnit hook contract | activates next to the toolkit with no PHP notice from us; `tkpll_after_duplicate` fires with the documented arguments. Their own translate flows need paid API keys and are not run. |
| 10 | Compatibility matrix | GitHub Actions | PHP 8.1/8.3/8.4 × WP 6.8/latest/trunk × Polylang min/latest |
| 11 | Performance | PHPUnit | front page adds ≤ 2 DB queries; no autoloaded option > 10 KB |
| 12 | Security | PHPCS security sniffs + tests | nonce + capability on every write; output escaped; input sanitized |
| 13 | i18n + a11y | `wp i18n make-pot` diff + axe in Playwright on settings pages | no untranslatable strings; 0 serious axe issues |
| 14 | Plugin Check | `wp plugin check` | 0 errors, 0 warnings |
| 15 | Packaging | `release.yml` dry run | zip installs on fresh site; uninstall leaves no `tkpll_` rows |

## Verification
- `npx wp-env start && bin/benchmark` passes on the M0 skeleton (all modules off) before feature work.
- Seed check: `bin/benchmark-selftest` (M0.5) applies known-bad patches and confirms each fails its declared gate.
- Per milestone manual smoke on wp-env: Polylang + toolkit, EN/FR/NE, run the module's flow in block and classic editors; check URLs, hreflang and sitemaps with Yoast, then Rank Math.
- Before 1.0: release zip on clean WP 6.8/PHP 8.1 and latest WP/PHP 8.4; Plugin Check clean; feedback from 5–10 real users addressed.
